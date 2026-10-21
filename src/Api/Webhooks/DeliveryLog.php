<?php

namespace OguzhanTogay\HueClient\Api\Webhooks;

/**
 * Remembers webhook delivery ids for a day, so a captured delivery cannot be
 * replayed. Only signed deliveries are recorded (the controller checks the
 * signature first), so nobody can fill the log with made-up ids.
 *
 * One JSON file, locked while read and written: PHP's built-in server and
 * php-fpm both run requests in separate processes.
 */
final class DeliveryLog
{
    private string $path;

    public function __construct(?string $directory = null, private readonly int $ttl = 86400)
    {
        $directory ??= sys_get_temp_dir();
        $this->path = rtrim($directory, '/') . '/hue-webhook-deliveries.json';
    }

    /**
     * Records the delivery and says whether it had been seen before.
     */
    public function seenBefore(string $source, string $id): bool
    {
        $key = $source . ':' . $id;
        $handle = fopen($this->path, 'c+');
        if ($handle === false) {
            return false; // no state, no protection, but never block a delivery
        }

        try {
            flock($handle, LOCK_EX);
            $seen = json_decode((string) stream_get_contents($handle), true);
            $seen = is_array($seen) ? $seen : [];

            $now = time();
            $seen = array_filter($seen, fn ($at) => is_int($at) && $now - $at < $this->ttl);

            $duplicate = isset($seen[$key]);
            $seen[$key] = $seen[$key] ?? $now;

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode($seen));
            fflush($handle);
            @chmod($this->path, 0600);

            return $duplicate;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
