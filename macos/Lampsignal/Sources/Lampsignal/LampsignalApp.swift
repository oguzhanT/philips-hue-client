import AppKit
import SwiftUI

@main
struct LampsignalApp: App {
    @StateObject private var model = HueModel()

    var body: some Scene {
        MenuBarExtra {
            ContentView()
                .environmentObject(model)
        } label: {
            Image(systemName: model.menuIcon)
        }
        .menuBarExtraStyle(.window)
    }
}

struct ContentView: View {
    @EnvironmentObject private var model: HueModel

    var body: some View {
        VStack(spacing: 0) {
            HeaderView()
            Divider()
            if model.isConfigured {
                ScrollView {
                    VStack(alignment: .leading, spacing: 14) {
                        NotifierCard()
                        if let error = model.errorMessage {
                            Label(error, systemImage: "exclamationmark.triangle.fill")
                                .font(.caption)
                                .foregroundStyle(.orange)
                        }
                        SectionTitle("Işıklar")
                        VStack(spacing: 2) {
                            ForEach(model.lights) { LightRow(light: $0) }
                        }
                        if !model.rooms.isEmpty {
                            SectionTitle("Odalar ve Bölgeler")
                            VStack(spacing: 2) {
                                ForEach(model.rooms) { GroupRow(group: $0) }
                            }
                        }
                    }
                    .padding(12)
                }
                .frame(minHeight: 240, idealHeight: 480, maxHeight: 560)
            } else {
                PairingView()
                    .padding(12)
            }
            Divider()
            FooterView()
        }
        .frame(width: 340)
        .task { await model.refresh() }
    }
}

private struct SectionTitle: View {
    let text: String
    init(_ text: String) { self.text = text }

    var body: some View {
        Text(text.uppercased())
            .font(.caption2.weight(.semibold))
            .foregroundStyle(.secondary)
            .padding(.horizontal, 8)
    }
}

private struct HeaderView: View {
    @EnvironmentObject private var model: HueModel

    var body: some View {
        HStack(spacing: 10) {
            Image(systemName: model.anyOn ? "lightbulb.fill" : "lightbulb")
                .font(.title2)
                .foregroundStyle(model.anyOn ? Color.yellow : Color.secondary)
                .frame(width: 24)
            VStack(alignment: .leading, spacing: 1) {
                Text("Hue").font(.headline)
                Text(model.statusText).font(.caption).foregroundStyle(.secondary)
            }
            Spacer()
            if model.isConfigured {
                Button { Task { await model.setGroup("0", ["on": true]) } } label: {
                    Image(systemName: "sun.max.fill")
                }
                .help("Tümünü aç")
                Button { Task { await model.setGroup("0", ["on": false]) } } label: {
                    Image(systemName: "moon.fill")
                }
                .help("Tümünü kapat")
                Button { Task { await model.refresh() } } label: {
                    Image(systemName: "arrow.clockwise")
                }
                .help("Yenile")
            }
        }
        .buttonStyle(.borderless)
        .padding(12)
    }
}

private struct NotifierCard: View {
    @EnvironmentObject private var model: HueModel

    private var lampsLabel: String {
        switch model.notifierLightIDs.count {
        case 0: return "Lamba seç"
        case 1: return model.lights.first { $0.id == model.notifierLightIDs[0] }?.name ?? "1 lamba"
        default: return "\(model.notifierLightIDs.count) lamba"
        }
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack(spacing: 10) {
                Image(systemName: model.notifierPaused ? "bell.slash.fill" : "bell.badge.fill")
                    .foregroundStyle(model.notifierPaused ? Color.secondary : Color.blue)
                    .frame(width: 24)
                VStack(alignment: .leading, spacing: 1) {
                    Text("Claude bildirimci").font(.subheadline.weight(.semibold))
                    Text(model.notifierStatusText).font(.caption).foregroundStyle(.secondary)
                }
                Spacer()
                Toggle("", isOn: Binding(
                    get: { !model.notifierPaused },
                    set: { $0 ? model.resumeNotifier() : model.pauseNotifier(minutes: nil) }
                ))
                .toggleStyle(.switch)
                .labelsHidden()
                .controlSize(.small)
            }
            HStack(spacing: 8) {
                Menu {
                    ForEach(model.lights) { light in
                        Button {
                            model.toggleNotifierLight(light.id)
                        } label: {
                            if model.isNotifierLight(light.id) {
                                Label(light.name, systemImage: "checkmark")
                            } else {
                                Text(light.name)
                            }
                        }
                    }
                } label: {
                    Text(lampsLabel)
                }
                .help("Bildirim için yanacak lambalar (birden fazla seçilebilir)")
                Menu("Duraklat") {
                    Button("30 dakika") { model.pauseNotifier(minutes: 30) }
                    Button("1 saat") { model.pauseNotifier(minutes: 60) }
                    Button("3 saat") { model.pauseNotifier(minutes: 180) }
                    Divider()
                    Button("Ben açana kadar") { model.pauseNotifier(minutes: nil) }
                }
                .fixedSize()
                Button("Test") { Task { await model.testNotifier() } }
                    .disabled(model.notifierLightIDs.isEmpty)
            }
            .controlSize(.small)
            if model.pluginEnabled == false {
                Label("hue-notify plugin'i Claude Code'da kapalı (/plugin ile açın)", systemImage: "info.circle")
                    .font(.caption)
                    .foregroundStyle(.secondary)
            }
        }
        .padding(10)
        .background(RoundedRectangle(cornerRadius: 10).fill(Color.primary.opacity(0.05)))
    }
}

private struct LightRow: View {
    @EnvironmentObject private var model: HueModel
    let light: HueLight

    private var isNotifier: Bool { model.isNotifierLight(light.id) }

    private var subtitle: String {
        if !light.reachable { return "Erişilemiyor" }
        return light.on ? "%\(Int((Double(light.bri) / 254 * 100).rounded()))" : "Kapalı"
    }

    var body: some View {
        ControlRow(
            title: light.name,
            subtitle: subtitle,
            color: light.displayColor,
            isOn: light.on,
            enabled: light.reachable,
            showsBell: isNotifier,
            bri: light.bri,
            supportsColor: light.supportsColor,
            supportsWhite: light.supportsCT,
            onChange: { body in Task { await model.setLight(light.id, body) } }
        ) {
            if !isNotifier {
                Button("Bildirimciye ekle") { model.toggleNotifierLight(light.id) }
                    .buttonStyle(.link)
                    .font(.caption)
            } else if model.notifierLightIDs.count > 1 {
                Button("Bildirimciden çıkar") { model.toggleNotifierLight(light.id) }
                    .buttonStyle(.link)
                    .font(.caption)
            }
        }
    }
}

private struct GroupRow: View {
    @EnvironmentObject private var model: HueModel
    let group: HueGroup

    private var subtitle: String {
        let kind = group.type == "Zone" ? "Bölge" : "Oda"
        let count = "\(group.lightIDs.count) lamba"
        guard group.anyOn else { return "\(kind) · \(count) · Kapalı" }
        return "\(kind) · \(count) · %\(Int((Double(group.bri) / 254 * 100).rounded()))"
    }

    var body: some View {
        ControlRow(
            title: group.name,
            subtitle: subtitle,
            color: group.displayColor,
            isOn: group.anyOn,
            enabled: true,
            showsBell: false,
            bri: group.bri,
            supportsColor: model.supportsColor(group),
            supportsWhite: true,
            onChange: { body in Task { await model.setGroup(group.id, body) } }
        ) { EmptyView() }
    }
}

/// A light/group row: color dot, name, switch; click to expand brightness and color controls.
private struct ControlRow<Extra: View>: View {
    let title: String
    let subtitle: String
    let color: Color
    let isOn: Bool
    let enabled: Bool
    let showsBell: Bool
    let bri: Int
    let supportsColor: Bool
    let supportsWhite: Bool
    let onChange: ([String: Any]) -> Void
    @ViewBuilder let extra: () -> Extra

    @State private var expanded = false
    @State private var sliderValue: Double = 254
    @State private var dragging = false
    @State private var hovering = false

    var body: some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack(spacing: 10) {
                Circle()
                    .fill(isOn && enabled ? color : Color.secondary.opacity(0.2))
                    .overlay(Circle().stroke(Color.primary.opacity(0.15)))
                    .frame(width: 14, height: 14)
                    .frame(width: 24)
                VStack(alignment: .leading, spacing: 1) {
                    HStack(spacing: 4) {
                        Text(title).lineLimit(1)
                        if showsBell {
                            Image(systemName: "bell.fill").font(.caption2).foregroundStyle(.blue)
                        }
                    }
                    Text(subtitle).font(.caption).foregroundStyle(.secondary)
                }
                Spacer()
                Image(systemName: "chevron.right")
                    .font(.caption2)
                    .foregroundStyle(.tertiary)
                    .rotationEffect(.degrees(expanded ? 90 : 0))
                    .opacity(enabled ? 1 : 0)
                Toggle("", isOn: Binding(get: { isOn }, set: { onChange(["on": $0]) }))
                    .toggleStyle(.switch)
                    .labelsHidden()
                    .controlSize(.small)
                    .disabled(!enabled)
            }
            .contentShape(Rectangle())
            .onTapGesture {
                guard enabled else { return }
                withAnimation(.easeInOut(duration: 0.15)) { expanded.toggle() }
            }

            if expanded && enabled {
                HStack(spacing: 6) {
                    Image(systemName: "sun.min")
                    Slider(value: $sliderValue, in: 1...254) { editing in
                        dragging = editing
                        if !editing { onChange(["on": true, "bri": Int(sliderValue)]) }
                    }
                    Image(systemName: "sun.max")
                }
                .font(.caption)
                .foregroundStyle(.secondary)
                .padding(.leading, 34)

                let presets = ColorPreset.all.filter { $0.isWhite ? supportsWhite : supportsColor }
                if !presets.isEmpty {
                    LazyVGrid(columns: Array(repeating: GridItem(.fixed(20), spacing: 8), count: 8), alignment: .leading, spacing: 8) {
                        ForEach(presets) { preset in
                            Button { onChange(preset.body) } label: {
                                Circle()
                                    .fill(preset.color)
                                    .overlay(Circle().stroke(Color.primary.opacity(0.2)))
                                    .frame(width: 20, height: 20)
                            }
                            .buttonStyle(.plain)
                            .help(preset.id)
                        }
                    }
                    .padding(.leading, 34)
                }
                extra().padding(.leading, 34)
            }
        }
        .padding(.vertical, 6)
        .padding(.horizontal, 8)
        .background(
            RoundedRectangle(cornerRadius: 8)
                .fill(Color.primary.opacity(expanded ? 0.06 : (hovering ? 0.04 : 0)))
        )
        .opacity(enabled ? 1 : 0.5)
        .onHover { hovering = $0 }
        .onAppear { sliderValue = Double(bri) }
        .onChange(of: bri) { newValue in
            if !dragging { sliderValue = Double(newValue) }
        }
    }
}

private struct PairingView: View {
    @EnvironmentObject private var model: HueModel
    @State private var ip = ""
    @State private var busy = false

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            Text("Hue köprüsüne bağlan").font(.headline)
            Text("Köprü IP'sini girin (veya Bul'a basın), köprünün üstündeki yuvarlak butona basın, sonra Eşleştir'e tıklayın.")
                .font(.caption)
                .foregroundStyle(.secondary)
                .fixedSize(horizontal: false, vertical: true)
            HStack {
                TextField("192.168.1.2", text: $ip)
                    .textFieldStyle(.roundedBorder)
                Button("Bul") {
                    Task {
                        busy = true
                        if let found = await HueAPI.discover() { ip = found }
                        busy = false
                    }
                }
            }
            Button {
                Task {
                    busy = true
                    await model.pair(ip: ip.trimmingCharacters(in: .whitespaces))
                    busy = false
                }
            } label: {
                Text("Eşleştir").frame(maxWidth: .infinity)
            }
            .buttonStyle(.borderedProminent)
            .disabled(ip.isEmpty || busy)
            if let error = model.errorMessage {
                Text(error).font(.caption).foregroundStyle(.orange)
            }
        }
    }
}

private struct FooterView: View {
    @EnvironmentObject private var model: HueModel

    var body: some View {
        HStack {
            Toggle("Girişte başlat", isOn: Binding(
                get: { model.launchAtLogin },
                set: { model.setLaunchAtLogin($0) }
            ))
            .toggleStyle(.checkbox)
            Spacer()
            Button("Çıkış") { NSApp.terminate(nil) }
                .keyboardShortcut("q")
        }
        .font(.caption)
        .controlSize(.small)
        .padding(.horizontal, 12)
        .padding(.vertical, 8)
    }
}
