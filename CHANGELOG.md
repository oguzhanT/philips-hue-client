# Changelog

## [1.0.8] - 2026-10-21

### Fixed
- The package installs again. Unused dependencies (`nesbot/carbon`, `clue/buzz-react`, `doctrine/instantiator`, `symfony/finder`, `react/event-loop`) are gone; Composer was refusing every earlier release because of a security advisory on the pinned Carbon version.
- `composer.json` now carries the Packagist name, `oguzhantogay/philips-hue-client`.
- Effects (`ColorLoop`, `Breathing`, `Alert`) no longer fail to load: their `start()` and `stop()` signatures conflicted with `BaseEffect`.
- REST API: setting hue/saturation and renaming a light called methods that did not exist. Added `Light::setName()`.

### Removed
- **BC:** `RetryHandler::executeAsync()`, which nothing in the package called and which depended on the removed ReactPHP packages.

### Notes
- `examples/weather-based-lighting.php` uses ReactPHP; install `react/event-loop` and `react/socket` yourself to run it.
