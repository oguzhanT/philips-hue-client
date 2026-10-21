<?php

namespace OguzhanTogay\HueClient\Signal;

/**
 * The developer-notification states shared with claude-hue-notify and HueBar
 * (see docs/signals.md): same names, colors and blink behaviour everywhere.
 */
enum SignalType: string
{
    /** Needs you: blue, blinking for about 15 seconds. */
    case Attention = 'attention';
    /** Finished well: solid green. */
    case Success = 'success';
    /** Something broke: solid red. */
    case Error = 'error';
    /** Heads-up: solid amber. */
    case Warning = 'warning';
    /** Busy: dim solid blue. */
    case Working = 'working';
    /** Lamp off, any running blink cancelled. */
    case Off = 'off';

    /**
     * Accepts the names claude-hue-notify uses too (waiting, done).
     */
    public static function fromName(string $name): self
    {
        $name = strtolower(trim($name));

        return match ($name) {
            'waiting' => self::Attention,
            'done' => self::Success,
            default => self::tryFrom($name) ?? throw new \InvalidArgumentException(
                "Unknown signal '{$name}'. Use one of: "
                . implode(', ', array_map(static fn (self $t) => $t->value, self::cases()))
            ),
        };
    }

    /**
     * The light state body the bridge receives.
     */
    public function state(): array
    {
        return match ($this) {
            self::Attention => ['on' => true, 'bri' => 200, 'hue' => 46920, 'sat' => 254, 'alert' => 'lselect'],
            self::Success => ['on' => true, 'bri' => 254, 'hue' => 25500, 'sat' => 254, 'alert' => 'none'],
            self::Error => ['on' => true, 'bri' => 254, 'hue' => 0, 'sat' => 254, 'alert' => 'none'],
            self::Warning => ['on' => true, 'bri' => 254, 'hue' => 8000, 'sat' => 254, 'alert' => 'none'],
            self::Working => ['on' => true, 'bri' => 150, 'hue' => 46920, 'sat' => 254, 'alert' => 'none'],
            self::Off => ['on' => false, 'alert' => 'none'],
        };
    }
}
