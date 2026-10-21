import Foundation

enum HueError: LocalizedError {
    case bridge(String)

    var errorDescription: String? {
        switch self {
        case .bridge(let message): return message
        }
    }
}

/// Minimal client for the Hue bridge's local v1 REST API.
struct HueAPI {
    let ip: String
    let token: String

    private func url(_ path: String) -> URL {
        URL(string: "http://\(ip)/api/\(token)/\(path)")!
    }

    func get(_ path: String) async throws -> [String: Any] {
        let request = URLRequest(url: url(path), timeoutInterval: 5)
        let (data, _) = try await URLSession.shared.data(for: request)
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
        let (data, _) = try await URLSession.shared.data(for: request)
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

    /// Creates an app token. The bridge's link button must be pressed first.
    static func pair(ip: String) async throws -> String {
        guard let url = URL(string: "http://\(ip)/api") else { throw HueError.bridge("Geçersiz IP") }
        var request = URLRequest(url: url, timeoutInterval: 8)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.httpBody = try JSONSerialization.data(withJSONObject: ["devicetype": "huebar#mac"])
        let (data, _) = try await URLSession.shared.data(for: request)
        let items = try JSONSerialization.jsonObject(with: data) as? [[String: Any]] ?? []
        if let success = items.first?["success"] as? [String: Any], let token = success["username"] as? String {
            return token
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
        try (data + Data("\n".utf8)).write(to: config, options: .atomic)
        // An atomic write creates a new file with the default umask (0644);
        // the config holds the bridge token, so keep it owner-only.
        try FileManager.default.setAttributes([.posixPermissions: 0o600], ofItemAtPath: config.path)
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
