# Signals: the shared lamp contract

The PHP library (`$client->signal()`, `hue signal`), [claude-hue-notify](https://github.com/oguzhanT/claude-hue-notify) and the HueBar menu bar app all show developer notifications the same way. This page is the contract between them; change it here first.

## States

| Signal | Also accepted | Lamp | Bridge state |
|---|---|---|---|
| `attention` | `waiting` | Blue, blinking ~15 s | `on, bri 200, hue 46920, sat 254, alert lselect` |
| `success` | `done` | Solid green | `on, bri 254, hue 25500, sat 254, alert none` |
| `error` | | Solid red | `on, bri 254, hue 0, sat 254, alert none` |
| `warning` | | Solid amber | `on, bri 254, hue 8000, sat 254, alert none` |
| `working` | | Dim solid blue | `on, bri 150, hue 46920, sat 254, alert none` |
| `off` | | Off, blink cancelled | `on false, alert none` |

`alert: none` is always sent so a new state cancels a running blink.

## Configuration file

`~/.config/hue-notify/config.json` (override the path with `HUE_NOTIFY_CONFIG`):

```json
{
  "bridge_ip": "192.168.1.2",
  "token": "<bridge username>",
  "light_id": "7",
  "light_ids": ["7", "12"]
}
```

- `light_ids` are the notifier's lamps. When it is missing or empty, `light_id` is the only lamp. Writers keep `light_id` equal to the first entry for older readers.
- The file holds the bridge token: create it readable by its owner only (0600).

## Pause file

`paused` next to the config file (`~/.config/hue-notify/paused`):

- Missing: signals are shown.
- Empty: paused until the file is removed.
- A unix timestamp: paused until that time; afterwards it is ignored.
- Anything else: treated as paused.

While paused, no signal changes a lamp. claude-hue-notify still lets its delayed auto-off run so a lamp is never left lit.
