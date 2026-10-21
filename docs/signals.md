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

claude-hue-notify switches the lamp off five seconds after `done`. The PHP library keeps `success` on until something else changes it; pass `restoreAfter` to put the previous state back instead.

A lamp without color (white or white-ambiance) gets only `on`, `bri` and `alert` from a colored signal. Senders change every lamp they can and report the ones they could not, rather than stopping at the first failure.

## Restoring

A sender that restores the previous state saves it as `signal-restore-<light id>.json` next to the config file and deletes it after restoring. A signal that starts while such a file exists (and is less than ten minutes old) keeps the saved state instead of capturing the first signal's colors, so overlapping signals still end on the lamp's real state.

Every sender that changes a lamp without restoring it (claude-hue-notify, its MCP server, HueBar) deletes that lamp's `signal-restore-<light id>.json` after the change. Its state is newer, so a pending restore must not undo it.

## Configuration file

`~/.config/hue-notify/config.json` (override the path with `HUE_NOTIFY_CONFIG`; a leading `~/` is expanded):

```json
{
  "bridge_ip": "192.168.1.2",
  "token": "<bridge username>",
  "light_id": "7",
  "light_ids": ["7", "12"],
  "pinned_public_key": "sha256//tz8h303qMvOC…"
}
```

- `light_ids` are the notifier's lamps. When it is missing or empty, `light_id` is the only lamp. Writers keep `light_id` equal to the first entry for older readers.
- The file holds the bridge token: create it readable by its owner only (0600), and keep it so when rewriting it.
- `pinned_public_key` is `sha256//` + base64 of the SHA-256 of the certificate's SubjectPublicKeyInfo, the format curl's `CURLOPT_PINNEDPUBLICKEY` takes; a renewed certificate with the same key keeps it. Every reader (the PHP library, claude-hue-notify, its MCP server, HueBar) talks to the bridge over HTTPS and refuses to send the token to any other key. With no pin yet, the first key seen is pinned and written back (trust on first use), but only if no other process pinned meanwhile; a different pin on disk is a mismatch, never overwritten.

## Pause file

`paused` next to the config file (`~/.config/hue-notify/paused`):

- Missing: signals are shown.
- Empty: paused until the file is removed.
- A unix timestamp: paused until that time; afterwards it is ignored.
- Anything else: treated as paused.

While paused, no signal changes a lamp. claude-hue-notify still lets its delayed auto-off run so a lamp is never left lit.
