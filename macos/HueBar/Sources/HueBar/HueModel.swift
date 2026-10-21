import Foundation
import ServiceManagement
import SwiftUI

struct HueLight: Identifiable, Equatable {
    let id: String
    var name: String
    var on: Bool
    var bri: Int
    var reachable: Bool
    var supportsColor: Bool
    var supportsCT: Bool
    var colormode: String?
    var xy: [Double]?
    var ct: Int?

    var displayColor: Color { HueColor.display(colormode: colormode, xy: xy, ct: ct) }
}

struct HueGroup: Identifiable, Equatable {
    let id: String
    var name: String
    var type: String
    var lightIDs: [String]
    var anyOn: Bool
    var allOn: Bool
    var bri: Int
    var colormode: String?
    var xy: [Double]?
    var ct: Int?

    var displayColor: Color { HueColor.display(colormode: colormode, xy: xy, ct: ct) }
}

@MainActor
final class HueModel: ObservableObject {
    @Published var lights: [HueLight] = []
    @Published var groups: [HueGroup] = []
    @Published var errorMessage: String?
    @Published var bridgeIP: String?
    /// Active notifier lights (config "light_ids", falling back to "light_id").
    @Published var notifierLightIDs: [String] = []
    @Published var notifierPaused = false
    @Published var pausedUntil: Date?
    @Published var pluginEnabled: Bool?
    @Published var launchAtLogin = SMAppService.mainApp.status == .enabled

    private var api: HueAPI?
    private var timer: Timer?

    var isConfigured: Bool { api != nil }
    var rooms: [HueGroup] { groups.filter { $0.type == "Room" || $0.type == "Zone" } }
    var anyOn: Bool { lights.contains { $0.on && $0.reachable } }

    var menuIcon: String {
        if notifierPaused { return "lightbulb.slash" }
        return anyOn ? "lightbulb.fill" : "lightbulb"
    }

    var statusText: String {
        guard let bridgeIP else { return "Köprü eşleştirilmedi" }
        if errorMessage != nil { return "\(bridgeIP) · bağlantı sorunu" }
        let reachable = lights.filter(\.reachable)
        return "\(bridgeIP) · \(reachable.filter(\.on).count)/\(reachable.count) açık"
    }

    var notifierStatusText: String {
        let names = notifierLightIDs.compactMap { id in lights.first { $0.id == id }?.name }
        let lamp = names.isEmpty ? "lamba seçilmedi" : (names.count > 2 ? "\(names.count) lamba" : names.joined(separator: ", "))
        guard notifierPaused else { return "Aktif · \(lamp)" }
        guard let pausedUntil else { return "Duraklatıldı · \(lamp)" }
        return "\(pausedUntil.formatted(date: .omitted, time: .shortened))'e kadar duraklatıldı"
    }

    init() {
        loadConfig()
        timer = Timer.scheduledTimer(withTimeInterval: 10, repeats: true) { [weak self] _ in
            Task { await self?.refresh() }
        }
        Task { await refresh() }
    }

    private func loadConfig() {
        let config = NotifierFiles.loadConfig()
        if let ip = config["bridge_ip"] as? String, let token = config["token"] as? String, !ip.isEmpty, !token.isEmpty {
            api = HueAPI(ip: ip, token: token)
            bridgeIP = ip
        } else {
            api = nil
            bridgeIP = nil
        }
        if let ids = config["light_ids"] as? [Any], !ids.isEmpty {
            notifierLightIDs = ids.map { "\($0)" }
        } else if let id = config["light_id"] {
            notifierLightIDs = ["\(id)"]
        } else {
            notifierLightIDs = []
        }
    }

    // MARK: - Refresh

    func refresh() async {
        readPauseState()
        pluginEnabled = NotifierFiles.pluginEnabled()
        guard let api else { return }
        do {
            async let lightData = api.get("lights")
            async let groupData = api.get("groups")
            let (l, g) = try await (lightData, groupData)
            lights = l.compactMap(Self.parseLight).sorted { (Int($0.id) ?? 0) < (Int($1.id) ?? 0) }
            groups = g.compactMap(Self.parseGroup).sorted { $0.name.localizedCompare($1.name) == .orderedAscending }
            errorMessage = nil
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    private static func parseLight(_ entry: (key: String, value: Any)) -> HueLight? {
        guard let dict = entry.value as? [String: Any], let state = dict["state"] as? [String: Any] else { return nil }
        return HueLight(
            id: entry.key,
            name: dict["name"] as? String ?? "Lamba \(entry.key)",
            on: state["on"] as? Bool ?? false,
            bri: state["bri"] as? Int ?? 254,
            reachable: state["reachable"] as? Bool ?? true,
            supportsColor: state["xy"] != nil,
            supportsCT: state["ct"] != nil,
            colormode: state["colormode"] as? String,
            xy: state["xy"] as? [Double],
            ct: state["ct"] as? Int
        )
    }

    private static func parseGroup(_ entry: (key: String, value: Any)) -> HueGroup? {
        guard let dict = entry.value as? [String: Any] else { return nil }
        let action = dict["action"] as? [String: Any] ?? [:]
        let state = dict["state"] as? [String: Any] ?? [:]
        return HueGroup(
            id: entry.key,
            name: dict["name"] as? String ?? "Grup \(entry.key)",
            type: dict["type"] as? String ?? "",
            lightIDs: dict["lights"] as? [String] ?? [],
            anyOn: state["any_on"] as? Bool ?? false,
            allOn: state["all_on"] as? Bool ?? false,
            bri: action["bri"] as? Int ?? 254,
            colormode: action["colormode"] as? String,
            xy: action["xy"] as? [Double],
            ct: action["ct"] as? Int
        )
    }

    // MARK: - Control

    func setLight(_ id: String, _ body: [String: Any]) async {
        if let index = lights.firstIndex(where: { $0.id == id }) {
            if let on = body["on"] as? Bool { lights[index].on = on }
            if let bri = body["bri"] as? Int { lights[index].bri = bri }
        }
        await send("lights/\(id)/state", body)
    }

    func setGroup(_ id: String, _ body: [String: Any]) async {
        if let on = body["on"] as? Bool {
            if let index = groups.firstIndex(where: { $0.id == id }) {
                groups[index].anyOn = on
                groups[index].allOn = on
            }
            let members = id == "0" ? Set(lights.map(\.id)) : Set(groups.first { $0.id == id }?.lightIDs ?? [])
            for index in lights.indices where members.contains(lights[index].id) {
                lights[index].on = on
            }
        }
        await send("groups/\(id)/action", body)
    }

    func supportsColor(_ group: HueGroup) -> Bool {
        lights.contains { group.lightIDs.contains($0.id) && $0.supportsColor }
    }

    private func send(_ path: String, _ body: [String: Any]) async {
        guard let api else { return }
        do {
            try await api.put(path, body)
            errorMessage = nil
        } catch {
            errorMessage = error.localizedDescription
        }
        try? await Task.sleep(nanoseconds: 300_000_000)
        await refresh()
    }

    // MARK: - Notifier

    func isNotifierLight(_ id: String) -> Bool { notifierLightIDs.contains(id) }

    /// Adds/removes a light from the active notifier set. The last light can't be removed (pause instead).
    func toggleNotifierLight(_ id: String) {
        var ids = notifierLightIDs
        if let index = ids.firstIndex(of: id) {
            guard ids.count > 1 else { return }
            ids.remove(at: index)
        } else {
            ids.append(id)
        }
        ids.sort { (Int($0) ?? 0) < (Int($1) ?? 0) }
        do {
            // "light_id" stays in sync for older hue-notify.py versions that only know a single light.
            try NotifierFiles.updateConfig(["light_ids": ids, "light_id": ids[0]])
            notifierLightIDs = ids
        } catch {
            errorMessage = "Config yazılamadı: \(error.localizedDescription)"
        }
    }

    func pauseNotifier(minutes: Int?) {
        let content = minutes.map { String(Int(Date().addingTimeInterval(Double($0) * 60).timeIntervalSince1970)) } ?? ""
        do {
            try FileManager.default.createDirectory(at: NotifierFiles.directory, withIntermediateDirectories: true)
            try content.write(to: NotifierFiles.pause, atomically: true, encoding: .utf8)
        } catch {
            errorMessage = "Duraklatılamadı: \(error.localizedDescription)"
        }
        readPauseState()
    }

    func resumeNotifier() {
        try? FileManager.default.removeItem(at: NotifierFiles.pause)
        readPauseState()
    }

    private func readPauseState() {
        guard let raw = try? String(contentsOf: NotifierFiles.pause, encoding: .utf8) else {
            notifierPaused = false
            pausedUntil = nil
            return
        }
        let trimmed = raw.trimmingCharacters(in: .whitespacesAndNewlines)
        guard let timestamp = Double(trimmed) else {
            notifierPaused = true
            pausedUntil = nil
            return
        }
        let until = Date(timeIntervalSince1970: timestamp)
        if until > Date() {
            notifierPaused = true
            pausedUntil = until
        } else {
            try? FileManager.default.removeItem(at: NotifierFiles.pause)
            notifierPaused = false
            pausedUntil = nil
        }
    }

    /// Blinks the notifier lights blue for a few seconds, then restores their previous state.
    func testNotifier() async {
        guard let api, !notifierLightIDs.isEmpty else { return }
        let targets = notifierLightIDs.compactMap { id in lights.first { $0.id == id && $0.reachable } }
        do {
            for light in targets {
                try await api.put("lights/\(light.id)/state", ["on": true, "bri": 200, "hue": 46920, "sat": 254, "alert": "lselect"])
            }
            try await Task.sleep(nanoseconds: 3_000_000_000)
            for light in targets {
                var restore: [String: Any] = ["alert": "none", "on": light.on]
                if light.on {
                    restore["bri"] = light.bri
                    if light.colormode == "ct", let ct = light.ct {
                        restore["ct"] = ct
                    } else if let xy = light.xy {
                        restore["xy"] = xy
                    }
                }
                try await api.put("lights/\(light.id)/state", restore)
            }
        } catch {
            errorMessage = error.localizedDescription
        }
        await refresh()
    }

    // MARK: - Pairing & app

    func pair(ip: String) async {
        do {
            let token = try await HueAPI.pair(ip: ip)
            try NotifierFiles.updateConfig(["bridge_ip": ip, "token": token])
            loadConfig()
            errorMessage = nil
            await refresh()
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    func setLaunchAtLogin(_ enabled: Bool) {
        do {
            if enabled {
                try SMAppService.mainApp.register()
            } else {
                try SMAppService.mainApp.unregister()
            }
        } catch {
            errorMessage = "Girişte başlatma ayarlanamadı: \(error.localizedDescription)"
        }
        launchAtLogin = SMAppService.mainApp.status == .enabled
    }
}
