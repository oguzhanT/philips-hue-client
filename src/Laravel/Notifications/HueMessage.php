<?php

namespace OguzhanTogay\HueClient\Laravel\Notifications;

use OguzhanTogay\HueClient\Signal\SignalType;

/**
 * What a notification shows on the lamps. Return one from toHue().
 *
 *   public function toHue($notifiable): HueMessage
 *   {
 *       return HueMessage::error()->lights('Desk')->restoreAfter(30);
 *   }
 */
class HueMessage
{
    /** @var int|string|array<int|string>|null */
    public int|string|array|null $lights = null;
    public ?int $restoreAfter = null;

    public function __construct(public SignalType $type = SignalType::Attention)
    {
    }

    public static function make(SignalType|string $type): self
    {
        return new self($type instanceof SignalType ? $type : SignalType::fromName($type));
    }

    public static function attention(): self
    {
        return new self(SignalType::Attention);
    }

    public static function success(): self
    {
        return new self(SignalType::Success);
    }

    public static function error(): self
    {
        return new self(SignalType::Error);
    }

    public static function warning(): self
    {
        return new self(SignalType::Warning);
    }

    /**
     * @param int|string|array<int|string> $lights light ids or names. Default: the notifiable's
     *                                             routeNotificationForHue(), then the notifier's lamps
     */
    public function lights(int|string|array $lights): self
    {
        $this->lights = $lights;
        return $this;
    }

    /**
     * Put the lamps back after this many seconds. It blocks, so send the
     * notification through the queue (ShouldQueue) when you use it.
     */
    public function restoreAfter(int $seconds): self
    {
        $this->restoreAfter = $seconds;
        return $this;
    }
}
