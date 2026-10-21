# Changelog

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
