import CryptoKit
import Foundation

enum HueError: LocalizedError {
    case bridge(String)

    var errorDescription: String? {
        switch self {
        case .bridge(let message): return message
        }
    }
}

/// The `sha256//<base64>` pin of a certificate's SubjectPublicKeyInfo, the same
/// value curl, the PHP library and claude-hue-notify use (`pinned_public_key`).
/// A renewed certificate with the same key keeps the same pin.
enum PublicKeyPin {
    static func of(certificateDER der: [UInt8]) -> String? {
        func element(at index: Int) -> (tag: UInt8, start: Int, end: Int)? {
            guard index + 1 < der.count else { return nil }
            var length = Int(der[index + 1])
            var start = index + 2
            if length & 0x80 != 0 {
                let count = length & 0x7F
                guard count <= 4, start + count <= der.count else { return nil }
                length = der[start..<start + count].reduce(0) { $0 << 8 | Int($1) }
                start += count
            }
            guard start + length <= der.count else { return nil }
            return (der[index], start, start + length)
        }

        guard let certificate = element(at: 0), let tbs = element(at: certificate.start) else { return nil }
        var fields: [(start: Int, end: Int)] = []
        var index = tbs.start
        while index < tbs.end, let field = element(at: index) {
            if field.tag != 0xA0 { fields.append((index, field.end)) } // skip [0] version
            index = field.end
        }
        // serial, signature algorithm, issuer, validity, subject, subjectPublicKeyInfo
        guard fields.count > 5 else { return nil }
        let spki = Data(der[fields[5].start..<fields[5].end])
        return "sha256//" + Data(SHA256.hash(data: spki)).base64EncodedString()
    }
}

/// Accepts the bridge's certificate only if its public key matches the pin
/// (`pinned_public_key`, shared with claude-hue-notify and the PHP library, see
/// docs/signals.md). The bridge's certificate comes from Philips' own CA, which
/// macOS does not trust, so the pin is the check. With no pin yet, the first
/// key seen is accepted and reported back to be saved: trust on first use.
final class BridgePinning: NSObject, URLSessionDelegate {
    let expected: String?
    private(set) var seen: String?

    init(expected: String?) {
        self.expected = expected
    }

    func urlSession(
        _ session: URLSession,
        didReceive challenge: URLAuthenticationChallenge,
        completionHandler: @escaping (URLSession.AuthChallengeDisposition, URLCredential?) -> Void
    ) {
        guard challenge.protectionSpace.authenticationMethod == NSURLAuthenticationMethodServerTrust,
              let trust = challenge.protectionSpace.serverTrust,
              let leaf = (SecTrustCopyCertificateChain(trust) as? [SecCertificate])?.first
        else {
            completionHandler(.cancelAuthenticationChallenge, nil)
            return
        }

        guard let pin = PublicKeyPin.of(certificateDER: [UInt8](SecCertificateCopyData(leaf) as Data)) else {
            completionHandler(.cancelAuthenticationChallenge, nil)
            return
        }
        seen = pin

        if expected == nil || expected == pin {
            completionHandler(.useCredential, URLCredential(trust: trust))
        } else {
            completionHandler(.cancelAuthenticationChallenge, nil)
        }
    }

    /// Runs one request over a session that enforces this pin.
    func data(for request: URLRequest) async throws -> Data {
        let session = URLSession(configuration: .ephemeral, delegate: self, delegateQueue: nil)
        defer { session.finishTasksAndInvalidate() }
        do {
            return try await session.data(for: request).0
        } catch {
            if let expected, let seen, expected != seen {
                throw HueError.bridge("Köprü sabitlenen anahtardan farklı bir anahtar sundu; bağlantı kesildi.")
            }
            throw error
        }
    }
}

/// Minimal client for the Hue bridge's local v1 REST API, over pinned HTTPS.
struct HueAPI {
    let ip: String
    let token: String
    /// The bridge's public key pin (config `pinned_public_key`); nil pins on first use.
    let pin: String?

    private func url(_ path: String) -> URL {
        URL(string: "https://\(ip)/api/\(token)/\(path)")!
    }

    private func send(_ request: URLRequest) async throws -> Data {
        // Another process (claude-hue-notify) may have pinned since this config was loaded.
        let expected = pin ?? NotifierFiles.loadConfig()["pinned_public_key"] as? String
        let pinning = BridgePinning(expected: expected)
        let data = try await pinning.data(for: request)
        if expected == nil, let seen = pinning.seen {
            try NotifierFiles.pinOnFirstUse(seen)
        }
        return data
    }

    func get(_ path: String) async throws -> [String: Any] {
        let request = URLRequest(url: url(path), timeoutInterval: 5)
        let data = try await send(request)
        let object = try JSONSerialization.jsonObject(with: data)
        try Self.throwIfError(object)
        guard let dict = object as? [String: Any] else {
            throw HueError.bridge("Köprüden beklenmeyen yanıt")
        }
        return dict
    }

    func put(_ path: String, _ body: [String: Any]) async throws {
        var request = URLRequest(url: url(path), timeoutInterval: 5)
        request.httpMethod = "PUT"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = try JSONSerialization.data(withJSONObject: body)
        let data = try await send(request)
        try Self.throwIfError(try JSONSerialization.jsonObject(with: data))

        // A change made here is newer than any state another sender (the PHP
        // library) is waiting to restore on this lamp, so drop that restore.
        let parts = path.split(separator: "/")
        if parts.count == 3, parts[0] == "lights", parts[2] == "state" {
            NotifierFiles.dropPendingRestore(lightId: String(parts[1]))
        }
    }

    private static func throwIfError(_ object: Any) throws {
        guard let items = object as? [[String: Any]] else { return }
        for item in items {
            if let error = item["error"] as? [String: Any] {
                throw HueError.bridge(error["description"] as? String ?? "Bilinmeyen köprü hatası")
            }
        }
    }

    /// Finds a bridge IP through Philips' discovery endpoint.
    static func discover() async -> String? {
        guard let url = URL(string: "https://discovery.meethue.com"),
              let (data, _) = try? await URLSession.shared.data(for: URLRequest(url: url, timeoutInterval: 5)),
              let found = try? JSONSerialization.jsonObject(with: data) as? [[String: Any]]
        else { return nil }
        return found.first?["internalipaddress"] as? String
    }

    /// Creates an app token over HTTPS and pins the key it was sent over.
    /// The bridge's link button must be pressed first.
    static func pair(ip: String) async throws -> (token: String, publicKeyPin: String?) {
        guard let url = URL(string: "https://\(ip)/api") else { throw HueError.bridge("Geçersiz IP") }
        var request = URLRequest(url: url, timeoutInterval: 8)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = try JSONSerialization.data(withJSONObject: ["devicetype": "huebar#mac"])
        let pinning = BridgePinning(expected: nil)
        let data = try await pinning.data(for: request)
        let items = try JSONSerialization.jsonObject(with: data) as? [[String: Any]] ?? []
        if let success = items.first?["success"] as? [String: Any], let token = success["username"] as? String {
            return (token, pinning.seen)
        }
        if let error = items.first?["error"] as? [String: Any], error["type"] as? Int == 101 {
            throw HueError.bridge("Köprüdeki butona basılmadı. Basıp tekrar deneyin.")
        }
        throw HueError.bridge("Eşleştirme başarısız")
    }
}

/// Shared with claude-hue-notify: ~/.config/hue-notify/{config.json,paused}
enum NotifierFiles {
    static let directory = URL(fileURLWithPath: NSHomeDirectory()).appendingPathComponent(".config/hue-notify")
    static let config = directory.appendingPathComponent("config.json")
    /// Present = notifier paused. Empty content = indefinitely, otherwise a unix timestamp to resume at.
    static let pause = directory.appendingPathComponent("paused")
    static let claudeSettings = URL(fileURLWithPath: NSHomeDirectory()).appendingPathComponent(".claude/settings.json")

    static func loadConfig() -> [String: Any] {
        guard let data = try? Data(contentsOf: config),
              let dict = try? JSONSerialization.jsonObject(with: data) as? [String: Any]
        else { return [:] }
        return dict
    }

    /// Merges `changes` into the existing config so keys we don't know about survive.
    static func updateConfig(_ changes: [String: Any]) throws {
        var dict = loadConfig()
        dict.merge(changes) { _, new in new }
        try FileManager.default.createDirectory(at: directory, withIntermediateDirectories: true)
        let data = try JSONSerialization.data(withJSONObject: dict, options: [.prettyPrinted, .sortedKeys])
        // The config holds the bridge token: write an owner-only temporary file
        // and move it into place, so it is never readable by others, not even briefly.
        let temporary = directory.appendingPathComponent(".config.json.\(ProcessInfo.processInfo.processIdentifier)")
        guard FileManager.default.createFile(
            atPath: temporary.path,
            contents: data + Data("\n".utf8),
            attributes: [.posixPermissions: 0o600]
        ) else {
            throw CocoaError(.fileWriteUnknown)
        }
        if rename(temporary.path, config.path) != 0 {
            try? FileManager.default.removeItem(at: temporary)
            throw CocoaError(.fileWriteUnknown)
        }
    }

    /// Saves the first pin, unless something else pinned meanwhile: then it must match.
    static func pinOnFirstUse(_ pin: String) throws {
        if let pinned = loadConfig()["pinned_public_key"] as? String, !pinned.isEmpty {
            guard pinned == pin else {
                throw HueError.bridge("Köprü, kayıtlı anahtardan farklı bir anahtar sundu.")
            }
            return
        }
        try updateConfig(["pinned_public_key": pin])
    }

    /// See docs/signals.md: signal-restore-<id>.json next to the config.
    static func dropPendingRestore(lightId: String) {
        let safe = lightId.filter { $0.isLetter || $0.isNumber || $0 == "-" || $0 == "_" }
        try? FileManager.default.removeItem(at: directory.appendingPathComponent("signal-restore-\(safe).json"))
    }

    /// nil when Claude Code settings don't mention the plugin.
    static func pluginEnabled() -> Bool? {
        guard let data = try? Data(contentsOf: claudeSettings),
              let dict = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
              let plugins = dict["enabledPlugins"] as? [String: Any]
        else { return nil }
        return plugins["hue-notify@claude-hue-notify"] as? Bool
    }
}
