<?php

namespace OguzhanTogay\HueClient\Signal;

use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Resources\Light;

/**
 * Shows a developer-notification state on one or more lamps.
 *
 *   $client->signal()->send('success');                 // the notifier's lamps
 *   $client->signal()->send('error', ['Desk', 3]);      // by name or id
 *   $client->signal()->send('attention', 'Desk', restoreAfter: 10);
 *
 * It honours the pause file that claude-hue-notify and HueBar use, and can
 * put the lamps back the way they were afterwards.
 */
class Signaler
{
    /** Light state fields worth putting back after a signal. */
    private const RESTORABLE = ['on', 'bri', 'hue', 'sat', 'ct', 'xy'];

    private string $notifierConfigPath;

    /**
     * @param HueClient|null $client             null is enough for pause(), resume() and isPaused()
     * @param string|null    $notifierConfigPath claude-hue-notify's config.json; its directory
     *                                           also holds the pause file
     */
    public function __construct(private readonly ?HueClient $client, ?string $notifierConfigPath = null)
    {
        $this->notifierConfigPath = $notifierConfigPath
            ?? (getenv('HUE_NOTIFY_CONFIG') ?: self::home() . '/.config/hue-notify/config.json');
    }

    /**
     * @param SignalType|string           $type         attention, success, error, warning, working, off
     *                                                  (or claude-hue-notify's waiting, done)
     * @param int|string|Light|array|null $lights       light ids, names or objects; null means the notifier's lamps
     * @param int|null                    $restoreAfter seconds to wait before restoring the previous state (blocks)
     * @return bool false when the notifier is paused and nothing was changed
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
        $previous = $restoreAfter !== null ? $this->capture($ids) : [];

        foreach ($ids as $id) {
            $this->client->request('PUT', "lights/{$id}/state", ['json' => $type->state()]);
        }

        if ($restoreAfter !== null) {
            sleep(max(0, $restoreAfter));
            foreach ($previous as $id => $state) {
                $this->client->request('PUT', "lights/{$id}/state", ['json' => $state + ['alert' => 'none']]);
            }
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
        $path = $this->pausePath();
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        file_put_contents($path, $seconds === null ? '' : (string) (time() + $seconds));
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
     * @return string[] light ids
     */
    private function resolve(int|string|Light|array|null $lights): array
    {
        if ($lights === null) {
            return $this->notifierLights();
        }

        $ids = [];
        foreach (is_array($lights) ? $lights : [$lights] as $light) {
            $ids[] = match (true) {
                $light instanceof Light => (string) $light->getId(),
                is_int($light), ctype_digit((string) $light) => (string) $light,
                default => (string) $this->client->lights()->getByName($light)->getId(),
            };
        }

        return array_values(array_unique($ids));
    }

    /**
     * The lamps claude-hue-notify drives: light_ids, else light_id.
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

    /**
     * @param string[] $ids
     * @return array<string, array> restorable state per light
     */
    private function capture(array $ids): array
    {
        $states = [];
        foreach ($ids as $id) {
            $state = $this->client->request('GET', "lights/{$id}")['state'] ?? [];
            $restore = array_intersect_key($state, array_flip(self::RESTORABLE));

            // Restore only the color the lamp was actually showing.
            $mode = $state['colormode'] ?? null;
            if ($mode === 'ct') {
                unset($restore['hue'], $restore['sat'], $restore['xy']);
            } elseif ($mode === 'xy') {
                unset($restore['hue'], $restore['sat'], $restore['ct']);
            } elseif ($mode === 'hs') {
                unset($restore['xy'], $restore['ct']);
            }

            // A lamp that was off only needs switching off again; the bridge
            // refuses color changes for a lamp that is off.
            $states[$id] = ($state['on'] ?? true) === false ? ['on' => false] : $restore;
        }

        return $states;
    }

    private static function home(): string
    {
        return getenv('HOME') ?: ($_SERVER['HOME'] ?? sys_get_temp_dir());
    }
}
