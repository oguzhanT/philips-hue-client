# Lampsignal for Mac

macOS menu bar app for Philips Hue: turn lights and rooms on/off, change brightness and color, and control the [Lampsignal plugin](https://github.com/oguzhanT/lampsignal)'s notifier (pause/resume it and choose its lamp).

<img src="docs/menu-bar.png" alt="Lampsignal menu: notifier switch, lamp picker, pause and test buttons, light controls" width="356">

Requires macOS 13+ and Swift 5.9+.

**Coming from HueBar?** `./build-app.sh --install` removes `/Applications/HueBar.app` too. The app has a new identity, so macOS asks for local network access again and the "Launch at login" choice has to be made again; the bridge, lamps and pause state live in the shared config and carry over.

```bash
./build-app.sh            # builds build/Lampsignal.app
./build-app.sh --install  # also copies it to /Applications and launches it
```

The app shares the notifier's config in `~/.config/hue-notify/config.json` (`bridge_ip`, `token`, `light_id`). You can pick several notifier lamps; they're saved as `light_ids`, and `light_id` is kept equal to the first one for older scripts. If that file doesn't exist yet, the app pairs with the bridge itself.

Pausing creates `~/.config/hue-notify/paused`. An empty file pauses until you resume. A unix timestamp pauses until that time. `hue-notify.py` makes no light changes while the pause is active.
