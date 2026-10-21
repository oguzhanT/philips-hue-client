# Changelog

## [2.2.0] - 2026-10-21

### Security
- **Webhook replay protection**: each signed delivery is accepted once per day, keyed by a hash of its signed body (delivery-id headers are not signed, so they cannot be the key); repeats get 200 and change nothing. Only deliveries whose signature checked out are recorded. State in `HUE_WEBHOOK_STATE_DIR`, by default a per-user directory (`~/.cache/philips-hue-client`), never a symlink; an unusable state answers 503 instead of silently accepting replays.
- **HueBar talks to the bridge over HTTPS** and pins its public key (`pinned_public_key`, the same `sha256//` SPKI pin as this library and claude-hue-notify 1.3.0); before, the token went over plain HTTP. Pairing pins the key the token is received over; older configs pin on first use, without overwriting a pin another process saved.

### Changed
- `docs/signals.md`: `pinned_public_key` is now part of the shared config, so the PHP library, claude-hue-notify and HueBar share one pin.


## [2.1.0] - 2026-10-21

### Added
- **Webhooks**: `POST /hooks/github` (workflow runs, check suites, deployments) and `POST /hooks/sentry` (issues, alerts) show signals; workflow runs from forks are ignored. Each delivery's HMAC signature is checked against `HUE_GITHUB_WEBHOOK_SECRET` / `HUE_SENTRY_WEBHOOK_SECRET`; lamps from `HUE_WEBHOOK_LIGHTS`, branches from `HUE_GITHUB_BRANCHES`.
- **Certificate pinning**: `Security\BridgeCertificate::fetch()` reads the bridge's certificate (its common name is the bridge id) and its public key pin; `pinned_public_key` makes `HueClient`, `HueV2Client` and the event stream refuse any other key. `hue setup` checks the certificate against the discovered bridge id and pins it; `hue pin` pins an existing setup. The event stream, which Guzzle runs through PHP's stream wrapper where curl's pin does not apply, checks the certificate first and ties the connection to its fingerprint. `ConnectionPool` (and Laravel, via `HUE_PINNED_PUBLIC_KEY`) passes pins to its clients, and its health check now goes through them instead of a separate unpinned client.

### Changed
- HueBar writes the shared config owner-only (it reverted to 0644 on every save) and, like every sender that does not restore, drops a lamp's pending `signal-restore` file when it changes the lamp.
- `docs/signals.md`: all senders now follow the restore-file rule (claude-hue-notify 1.2.0 joined).


## [2.0.0] - 2026-10-21

### Added
- **CLIP v2 client**: `HueV2Client` (or `$client->v2()` with the same bridge and key). Lights with gamut-aware colors, color temperature clamped to the lamp's range, effects (candle, fire, ...), identify and transitions; rooms and zones with their grouped light, lights and scenes; scenes with static or dynamic recall; devices; any other resource type through `resources()`.
- **Real-time events**: `$hue->events()->listen()` reads the bridge's v2 event stream and reports a lost connection after a silent `idleTimeout`; `hue events` prints it.
- **Local discovery over mDNS** (`MdnsDiscovery`, needs ext-sockets). `BridgeDiscovery` tries it before Philips' discovery service, so `hue discover` works without internet.
- `Color` and `Gamut` value objects.
- Laravel binds `HueV2Client` next to `HueClient`.

### Changed
- **BC:** hex colors are converted with the wide-gamut formula Philips documents (via `Color`) instead of plain sRGB, so v1 colors shift slightly towards what the lamps actually show. The four copies of the conversion are gone.
- **BC:** `setColor()` and the color effects throw `InvalidArgumentException` for a malformed hex color instead of sending a meaningless color.
- Bridge ids from different discovery methods are compared case-insensitively.

### Security note
- Discovery answers are not authenticated and the bridge's certificate is not checked by default (`verify` => false). On an untrusted network, configure the bridge IP yourself rather than trusting discovery; certificate pinning to the bridge id is planned for 2.x.

### Removed
- **BC:** `HueClient::events()` and `Resources\Events`, a placeholder that never delivered events. Use `$client->v2()->events()`.
- The SSDP discovery stub; bridges answer mDNS and the discovery service.


## [1.2.0] - 2026-10-21

### Added
- **Signals**: `$client->signal()->send('success' | 'error' | 'attention' | 'warning' | 'working' | 'off', $lights, restoreAfter: $seconds)`. Defaults to claude-hue-notify's lamps, honours its pause file, and can restore the previous state. Every lamp gets its turn even when one fails, lamps without color get on/brightness only, and overlapping signals still end on the lamp's real state. The shared contract is in [docs/signals.md](docs/signals.md).
- `HueClient::request()` takes `'cache' => false` to skip the read cache.
- **CLI**: `hue signal <type> [--light=...] [--restore=N]`, `hue pause [minutes]`, `hue resume`.
- **Laravel notification channel**: `HueChannel` and `HueMessage` (`toHue()`, `routeNotificationForHue()`).

### Removed
- `hue interactive`, which printed a prompt and did nothing.


## [1.1.0] - 2026-10-21

### Security
- **REST API requires an API key** (`HUE_API_KEY`, sent as `Authorization: Bearer` or `X-API-Key`). Without one it refuses requests unless started with `--insecure` / `HUE_API_INSECURE=1`. `/api/health` and the docs stay public.
- **CORS is closed by default.** Allow browser origins with `HUE_CORS_ORIGINS`.
- **Writes must be `application/json`**, so web pages can no longer post `text/plain` to change lights or schedule bridge commands.
- **The bridge token no longer leaks** into error messages, API responses or logs. Guzzle put the full URI, token included, into its exception messages.
- Error details are hidden unless `HUE_DEBUG=1`. `/api/bridge/config` no longer returns the bridge's list of issued app keys.
- The rate limiter keys on `REMOTE_ADDR` instead of the forgeable `X-Forwarded-For`, counts each request once (it consumed three tokens per request), runs before the API key check so key guessing is slowed too, and keeps its counts in `symfony/cache` when installed (in memory they reset on every request under PHP's built-in server).
- `hue server` passes the bridge username through the environment instead of the command line, where every user could read it with `ps`.
- `hue setup` writes `~/.hue-config.json` readable by the owner only.
- Requires Guzzle 7.15.2 or later (CVE-2026-69246 and related advisories).

### Fixed
- **Every request failed without `symfony/cache` installed**, the default setup, with "must not be accessed before initialization".
- `bin/hue-server` and `hue server` start a server again; it listens on `127.0.0.1` unless `--bind` says otherwise.
- `public/api.php` reads its settings with `getenv()`, so it works whatever `variables_order` is. It no longer prints text into JSON responses.
- `/api/docs` and `/docs` are only served when `zircote/swagger-php` is installed, instead of failing with a 500.
- POST requests are not retried, so a slow bridge can no longer end up with a group, scene or schedule created twice.
- Writes invalidate the read cache instead of serving stale state for ten seconds.
- Errors in any entry of a multi-field bridge response are reported, not just the first one.
- Redis cache invalidation no longer calls a method `RedisAdapter` does not have.

### Changed
- **`HUE_AUTO_DISCOVER` now defaults to off** for the REST front controller: PHP's built-in server runs it per request and Philips rate limits its discovery service. Set `HUE_BRIDGE_IP`.
- `docker compose` requires `HUE_BRIDGE_IP`, `HUE_USERNAME` and `HUE_API_KEY`, runs as `www-data`, installs from `composer.lock`, and no longer publishes Redis's port. The nginx service, which could not start (its config file was missing), was removed.
- CI runs PHP 8.2–8.4, `composer audit`, and phpstan without the catch-all ignore (a baseline holds the one known issue).
- The REST API tests run by default.


## [1.0.8] - 2026-10-21

### Fixed
- The package installs again. Unused dependencies (`nesbot/carbon`, `clue/buzz-react`, `doctrine/instantiator`, `symfony/finder`, `react/event-loop`) are gone; Composer was refusing every earlier release because of a security advisory on the pinned Carbon version.
- `composer.json` now carries the Packagist name, `oguzhantogay/philips-hue-client`.
- Effects (`ColorLoop`, `Breathing`, `Alert`) no longer fail to load: their `start()` and `stop()` signatures conflicted with `BaseEffect`.
- REST API: setting hue/saturation and renaming a light called methods that did not exist. Added `Light::setName()`.
- Three examples (`weather-based-lighting`, `gaming-mood-lighting`, `smart-home-automation`) had PHP syntax errors.

### Documentation
- README matches the code: v1 API (not v2), N-UPnP discovery only, PHP 8.2, working scene/schedule/CLI examples, optional caching and rate limiting, a security warning for the REST server, and a new *Known limitations and roadmap* section. The placeholder showcase lists the real companion projects.

### Removed
- **BC:** `RetryHandler::executeAsync()`, which nothing in the package called and which depended on the removed ReactPHP packages.

### Notes
- `examples/weather-based-lighting.php` uses ReactPHP; install `react/event-loop` and `react/socket` yourself to run it.
