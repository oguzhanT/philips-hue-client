import Foundation
import SwiftUI

enum HueColor {
    static let warmWhite = Color(red: 1.0, green: 0.82, blue: 0.6)

    static func display(colormode: String?, xy: [Double]?, ct: Int?) -> Color {
        if colormode == "ct", let ct { return fromMired(ct) }
        if let xy, xy.count == 2 { return fromXY(xy[0], xy[1]) }
        if let ct { return fromMired(ct) }
        return warmWhite
    }

    /// CIE xy → sRGB (Philips' Wide RGB D65 conversion), normalized to full brightness.
    static func fromXY(_ x: Double, _ y: Double) -> Color {
        guard y > 0 else { return warmWhite }
        let Y = 1.0
        let X = (Y / y) * x
        let Z = (Y / y) * (1 - x - y)
        var r = max(0, X * 1.656492 - Y * 0.354851 - Z * 0.255038)
        var g = max(0, -X * 0.707196 + Y * 1.655397 + Z * 0.036152)
        var b = max(0, X * 0.051713 - Y * 0.121364 + Z * 1.011530)
        let peak = max(r, g, b)
        if peak > 1 { r /= peak; g /= peak; b /= peak }
        func gamma(_ v: Double) -> Double { v <= 0.0031308 ? 12.92 * v : 1.055 * pow(v, 1 / 2.4) - 0.055 }
        return Color(red: gamma(r), green: gamma(g), blue: gamma(b))
    }

    /// Mired color temperature → approximate RGB (Tanner Helland's blackbody fit).
    static func fromMired(_ mired: Int) -> Color {
        let t = 1_000_000.0 / Double(max(mired, 1)) / 100
        let r = t <= 66 ? 255 : 329.698727446 * pow(t - 60, -0.1332047592)
        let g = t <= 66 ? 99.4708025861 * log(t) - 161.1195681661 : 288.1221695283 * pow(t - 60, -0.0755148492)
        let b = t >= 66 ? 255 : (t <= 19 ? 0 : 138.5177312231 * log(t - 10) - 305.0447927307)
        func clamp(_ v: Double) -> Double { min(max(v, 0), 255) / 255 }
        return Color(red: clamp(r), green: clamp(g), blue: clamp(b))
    }
}

struct ColorPreset: Identifiable {
    let id: String
    let color: Color
    let body: [String: Any]
    let isWhite: Bool

    static func white(_ name: String, mired: Int) -> ColorPreset {
        ColorPreset(id: name, color: HueColor.fromMired(mired), body: ["on": true, "ct": mired], isWhite: true)
    }

    static func hue(_ name: String, _ hue: Int) -> ColorPreset {
        ColorPreset(
            id: name,
            color: Color(hue: Double(hue) / 65535, saturation: 1, brightness: 1),
            body: ["on": true, "hue": hue, "sat": 254],
            isWhite: false
        )
    }

    static let all: [ColorPreset] = [
        .white("Sıcak", mired: 454),
        .white("Yumuşak", mired: 370),
        .white("Nötr", mired: 250),
        .white("Soğuk", mired: 153),
        .hue("Kırmızı", 0),
        .hue("Turuncu", 5500),
        .hue("Sarı", 10500),
        .hue("Yeşil", 25500),
        .hue("Turkuaz", 33000),
        .hue("Mavi", 46920),
        .hue("Mor", 50500),
        .hue("Pembe", 56100),
    ]
}
