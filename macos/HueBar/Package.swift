// swift-tools-version:5.9
import PackageDescription

let package = Package(
    name: "HueBar",
    platforms: [.macOS(.v13)],
    targets: [
        .executableTarget(name: "HueBar", path: "Sources/HueBar")
    ]
)
