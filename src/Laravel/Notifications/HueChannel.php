<?php

namespace OguzhanTogay\HueClient\Laravel\Notifications;

use OguzhanTogay\HueClient\HueClient;

/**
 * Laravel notification channel: `return [HueChannel::class];` in via(), and a
 * toHue($notifiable) method that returns a HueMessage (or a signal name).
 *
 * Lights come from the message, then the notifiable's
 * routeNotificationForHue($notification), then the notifier's lamps.
 */
class HueChannel
{
    public function __construct(private readonly HueClient $client)
    {
    }

    /**
     * @param object $notifiable
     * @param object $notification an Illuminate notification with a toHue() method
     */
    public function send($notifiable, $notification): void
    {
        if (!method_exists($notification, 'toHue')) {
            throw new \InvalidArgumentException(get_class($notification) . ' has no toHue() method.');
        }

        $message = $notification->toHue($notifiable);
        if (is_string($message)) {
            $message = HueMessage::make($message);
        }

        if (!$message instanceof HueMessage) {
            return;
        }

        $lights = $message->lights;
        if ($lights === null && method_exists($notifiable, 'routeNotificationFor')) {
            $lights = $notifiable->routeNotificationFor('hue', $notification);
        }

        $this->client->signal()->send($message->type, $lights, $message->restoreAfter);
    }
}
