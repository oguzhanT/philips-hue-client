<?php

namespace OguzhanTogay\HueClient\Api\Webhooks;

/**
 * Remembers signed webhook deliveries for a day, so a captured delivery cannot
 * be replayed. The key is a hash of the signed body: delivery-id headers are
 * not covered by the signature, so an attacker could drop or change them.
 * Only deliveries whose signature checked out are recorded.
 *
 * One JSON file, locked while read and written (PHP's built-in server and
 * php-fpm run requests in separate processes), in a directory only this user
 * can use.
 */
final class DeliveryLog
{
    private string $path;

    public function __construct(?string $directory = null, private readonly int $ttl = 86400)
    {
        $directory ??= self::defaultDirectory();
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException("Cannot create the webhook state directory {$directory}.");
        }
        $this->path = rtrim($directory, '/') . '/hue-webhook-deliveries.json';
    }

    /**
     * Records the delivery and says whether it had been seen before.
     *
     * @param string $signedBody the raw body whose signature was verified
     * @throws \RuntimeException when the log cannot be used: refusing beats
     *                           silently accepting replays
     */
    public function seenBefore(string $source, string $signedBody): bool
    {
        $key = $source . ':' . hash('sha256', $signedBody);

        if (is_link($this->path)) {
            throw new \RuntimeException("{$this->path} is a symlink; refusing to use it.");
        }

        $handle = @fopen($this->path, 'c+');
        if ($handle === false) {
            throw new \RuntimeException(
                "Cannot open {$this->path}; set HUE_WEBHOOK_STATE_DIR to a writable directory."
            );
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

    /**
     * Per user, never a shared directory: ~/.cache/philips-hue-client, or a
     * user-specific directory under the temp dir when there is no home.
     */
    private static function defaultDirectory(): string
    {
        $home = getenv('HOME') ?: '';
        if ($home !== '' && is_dir($home) && is_writable($home)) {
            return $home . '/.cache/philips-hue-client';
        }

        $user = function_exists('posix_geteuid') ? (string) posix_geteuid() : get_current_user();

        return sys_get_temp_dir() . '/philips-hue-client-' . $user;
    }
}
