// swift-tools-version:5.9
import PackageDescription

let package = Package(
    name: "Lampsignal",
    platforms: [.macOS(.v13)],
    targets: [
        .executableTarget(name: "Lampsignal", path: "Sources/Lampsignal")
    ]
)
