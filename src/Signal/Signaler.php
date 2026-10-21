<?php

namespace OguzhanTogay\HueClient\Signal;

use OguzhanTogay\HueClient\Exceptions\HueException;
use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Resources\Light;

/**
 * Shows a developer-notification state on one or more lamps.
 *
 *   $client->signal()->send('success');                 // the notifier's lamps
 *   $client->signal()->send('error', ['Desk', 3]);      // by name or id
 *   $client->signal()->send('attention', 'Desk', restoreAfter: 10);
 *
 * It honours the pause file that the Lampsignal plugin and Lampsignal for Mac use, and can
 * put the lamps back the way they were afterwards.
 */
class Signaler
{
    /** Light state fields worth putting back after a signal. */
    private const RESTORABLE = ['on', 'bri', 'hue', 'sat', 'ct', 'xy'];

    /** What a lamp without color still understands. */
    private const BASIC = ['on', 'bri', 'alert'];

    /** A saved state older than this belongs to a signal that never finished. */
    private const STALE_AFTER = 600;

    private string $notifierConfigPath;

    /**
     * @param HueClient|null $client             null is enough for pause(), resume() and isPaused()
     * @param string|null    $notifierConfigPath the Lampsignal plugin's config.json; its directory
     *                                           also holds the pause file
     */
    public function __construct(private readonly ?HueClient $client, ?string $notifierConfigPath = null)
    {
        $path = $notifierConfigPath
            ?? (getenv('HUE_NOTIFY_CONFIG') ?: '~/.config/hue-notify/config.json');

        // Same as Python's expanduser in the Lampsignal plugin.
        $this->notifierConfigPath = str_starts_with($path, '~/') ? self::home() . substr($path, 1) : $path;
    }

    /**
     * Every lamp gets its turn even if another one fails, and with
     * $restoreAfter every lamp that changed is put back. Failures are
     * reported together at the end.
     *
     * @param SignalType|string           $type         attention, success, error, warning, working, off
     *                                                  (or the Lampsignal plugin's waiting, done)
     * @param int|string|Light|array|null $lights       light ids, names or objects; null means the notifier's lamps
     * @param int|null                    $restoreAfter seconds before restoring the previous state; it blocks,
     *                                                  so keep it under your queue worker's timeout
     * @return bool false when the notifier is paused and nothing was changed
     * @throws HueException naming the lamps that could not be changed or restored
     */
    public function send(
        SignalType|string $type,
        int|string|Light|array|null $lights = null,
        ?int $restoreAfter = null
    ): bool {
        if ($this->client === null) {
            throw new \LogicException('Signaler needs a HueClient to change lights.');
        }

        if ($this->isPaused()) {
            return false;
        }

        $type = $type instanceof SignalType ? $type : SignalType::fromName($type);
        $ids = $this->resolve($lights);
        $errors = [];
        $changed = [];

        try {
            foreach ($ids as $id) {
                try {
                    if ($restoreAfter !== null) {
                        $this->rememberState($id);
                    }
                    $this->apply($id, $type->state());
                    $changed[] = $id;
                } catch (HueException $e) {
                    $errors[] = "light {$id}: {$e->getMessage()}";
                }
            }

            if ($restoreAfter !== null && $changed !== []) {
                sleep(max(0, $restoreAfter));
            }
        } finally {
            if ($restoreAfter !== null) {
                foreach ($ids as $id) {
                    try {
                        $this->restoreState($id);
                    } catch (HueException $e) {
                        $errors[] = "light {$id} (restore): {$e->getMessage()}";
                    }
                }
            }
        }

        if ($errors !== []) {
            throw new HueException('Signal incomplete: ' . implode('; ', $errors));
        }

        return true;
    }

    /**
     * Paused while the pause file exists: empty means until removed, a unix
     * timestamp means until then.
     */
    public function isPaused(): bool
    {
        $path = $this->pausePath();
        if (!is_file($path)) {
            return false;
        }

        $raw = trim((string) @file_get_contents($path));
        if ($raw === '') {
            return true;
        }

        return !is_numeric($raw) || time() < (float) $raw;
    }

    /**
     * @param int|null $seconds pause for this long, or until resume() when null
     */
    public function pause(?int $seconds = null): void
    {
        $this->ensureDirectory();
        file_put_contents($this->pausePath(), $seconds === null ? '' : (string) (time() + $seconds));
    }

    public function resume(): void
    {
        @unlink($this->pausePath());
    }

    public function pausePath(): string
    {
        return dirname($this->notifierConfigPath) . '/paused';
    }

    /**
     * Sends the state; a lamp without color refuses hue/sat, so it gets the
     * parts it understands instead.
     */
    private function apply(string $id, array $state): void
    {
        try {
            $this->client->request('PUT', "lights/{$id}/state", ['json' => $state]);
        } catch (HueException $e) {
            $basic = array_intersect_key($state, array_flip(self::BASIC));
            if ($basic === $state || !$this->isUnsupportedParameter($e)) {
                throw $e;
            }
            $this->client->request('PUT', "lights/{$id}/state", ['json' => $basic]);
        }
    }

    private function isUnsupportedParameter(HueException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'not available') || str_contains($message, 'not modifiable');
    }

    /**
     * Saves the lamp's state before the signal, unless an earlier signal that
     * has not been restored yet already did: then its state is the real one.
     */
    private function rememberState(string $id): void
    {
        $file = $this->stateFile($id);
        if (is_file($file) && time() - (int) filemtime($file) < self::STALE_AFTER) {
            return;
        }

        $state = $this->client->request('GET', "lights/{$id}", ['cache' => false])['state'] ?? [];
        $this->ensureDirectory();
        file_put_contents($file, (string) json_encode(self::restorable($state)));
    }

    private function restoreState(string $id): void
    {
        $file = $this->stateFile($id);
        if (!is_file($file)) {
            return;
        }

        $state = json_decode((string) file_get_contents($file), true);
        @unlink($file);

        if (is_array($state) && $state !== []) {
            $this->apply($id, $state + ['alert' => 'none']);
        }
    }

    /**
     * Only the color the lamp was actually showing; a lamp that was off is
     * just switched off again (the bridge refuses color for a lamp that is off).
     */
    private static function restorable(array $state): array
    {
        if (($state['on'] ?? true) === false) {
            return ['on' => false];
        }

        $restore = array_intersect_key($state, array_flip(self::RESTORABLE));

        match ($state['colormode'] ?? null) {
            'ct' => $restore = array_diff_key($restore, array_flip(['hue', 'sat', 'xy'])),
            'xy' => $restore = array_diff_key($restore, array_flip(['hue', 'sat', 'ct'])),
            'hs' => $restore = array_diff_key($restore, array_flip(['xy', 'ct'])),
            default => null,
        };

        return $restore;
    }

    private function stateFile(string $id): string
    {
        $safeId = preg_replace('/[^A-Za-z0-9_-]/', '', $id);

        return dirname($this->notifierConfigPath) . "/signal-restore-{$safeId}.json";
    }

    private function ensureDirectory(): void
    {
        $dir = dirname($this->notifierConfigPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
    }

    /**
     * @return string[] light ids
     */
    private function resolve(int|string|Light|array|null $lights): array
    {
        if ($lights === null) {
            return $this->notifierLights();
        }

        $ids = [];
        foreach (is_array($lights) ? $lights : [$lights] as $light) {
            if ($light instanceof Light) {
                $ids[] = (string) $light->getId();
            } elseif (is_int($light) || ctype_digit((string) $light)) {
                $ids[] = (string) $light;
            } else {
                $found = $this->client->lights()->getByName((string) $light)
                    ?? throw new \InvalidArgumentException("Unknown light '{$light}'.");
                $ids[] = (string) $found->getId();
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * The lamps the Lampsignal plugin drives: light_ids, else light_id.
     *
     * @return string[]
     */
    private function notifierLights(): array
    {
        $config = is_file($this->notifierConfigPath)
            ? json_decode((string) file_get_contents($this->notifierConfigPath), true)
            : null;

        $ids = is_array($config['light_ids'] ?? null) && $config['light_ids'] !== []
            ? $config['light_ids']
            : (isset($config['light_id']) ? [$config['light_id']] : []);

        if ($ids === []) {
            throw new \InvalidArgumentException(
                "No lights given and no notifier lamps configured in {$this->notifierConfigPath}."
            );
        }

        return array_map('strval', $ids);
    }

    private static function home(): string
    {
        return getenv('HOME') ?: ($_SERVER['HOME'] ?? sys_get_temp_dir());
    }
}
