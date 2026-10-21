#!/usr/bin/env bash
# Builds HueBar.app (menu bar app). Usage: ./build-app.sh [--install]
set -euo pipefail
cd "$(dirname "$0")"

swift build -c release

APP=build/HueBar.app
rm -rf "$APP"
mkdir -p "$APP/Contents/MacOS" "$APP/Contents/Resources"
cp .build/release/HueBar "$APP/Contents/MacOS/HueBar"
cp Info.plist "$APP/Contents/Info.plist"
codesign --force --sign - "$APP"
echo "Built $APP"

if [[ "${1:-}" == "--install" ]]; then
    pkill -x HueBar 2>/dev/null || true
    rm -rf /Applications/HueBar.app
    cp -R "$APP" /Applications/
    open /Applications/HueBar.app
    echo "Installed to /Applications/HueBar.app"
fi
