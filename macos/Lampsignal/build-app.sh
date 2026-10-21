#!/usr/bin/env bash
# Builds Lampsignal.app (menu bar app). Usage: ./build-app.sh [--install]
set -euo pipefail
cd "$(dirname "$0")"

swift build -c release

APP=build/Lampsignal.app
rm -rf "$APP"
mkdir -p "$APP/Contents/MacOS" "$APP/Contents/Resources"
cp .build/release/Lampsignal "$APP/Contents/MacOS/Lampsignal"
cp Info.plist "$APP/Contents/Info.plist"
codesign --force --sign - "$APP"
echo "Built $APP"

if [[ "${1:-}" == "--install" ]]; then
    pkill -x Lampsignal 2>/dev/null || true
    rm -rf /Applications/Lampsignal.app
    cp -R "$APP" /Applications/
    open /Applications/Lampsignal.app
    echo "Installed to /Applications/Lampsignal.app"
fi
