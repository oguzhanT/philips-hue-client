<?php

namespace OguzhanTogay\HueClient\V2;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use OguzhanTogay\HueClient\Exceptions\HueException;

/**
 * The bridge's server-sent event stream (/eventstream/clip/v2): one event
 * per change, as it happens.
 *
 *   $hue->events()->listen(function (array $event) {
 *       // $event = ['type' => 'update', 'creationtime' => ...,
 *       //           'data' => [['id' => ..., 'type' => 'light', 'on' => [...]]]]
 *       return true; // keep listening; return false to stop
 *   }, seconds: 60);
 */
class EventStream
{
    /** @var \Closure(string): string */
    private \Closure $redact;

    /**
     * @param string|null $peerFingerprint SHA-256 of the certificate the bridge must present; checked
     *                                     during the TLS handshake, before any header is sent
     */
    public function __construct(
        private readonly ClientInterface $http,
        private readonly string $applicationKey,
        callable $redact,
        private readonly ?string $peerFingerprint = null
    ) {
        $this->redact = \Closure::fromCallable($redact);
    }

    /**
     * Blocks, calling $onEvent for every event until it returns false, the
     * time is up, or the bridge closes the stream.
     *
     * @param callable(array): (bool|null) $onEvent
     * @param float|null $seconds     stop after this long; null listens until the stream ends
     * @param float      $idleTimeout the bridge sends a heartbeat regularly; nothing at all for
     *                                this long means the connection is gone (bridge reboot,
     *                                network drop) and a HueException tells you to reconnect
     * @return int number of events delivered
     */
    public function listen(callable $onEvent, ?float $seconds = null, float $idleTimeout = 120.0): int
    {
        $deadline = $seconds !== null ? microtime(true) + $seconds : null;
        $lastData = microtime(true);

        try {
            $response = $this->http->request('GET', 'eventstream/clip/v2', [
                'headers' => ['hue-application-key' => $this->applicationKey, 'Accept' => 'text/event-stream'],
                'stream' => true,
                'timeout' => 0,
                // Wake up regularly so the deadline is honoured on a quiet stream.
                'read_timeout' => $seconds !== null ? min(5.0, max(0.5, $seconds)) : 60.0,
            ] + ($this->peerFingerprint !== null ? [
                'stream_context' => ['ssl' => ['peer_fingerprint' => ['sha256' => $this->peerFingerprint]]],
            ] : []));
        } catch (GuzzleException $e) {
            throw new HueException('Event stream failed: ' . ($this->redact)($e->getMessage()));
        }

        if ($response->getStatusCode() >= 400) {
            throw new HueException("Event stream refused: HTTP {$response->getStatusCode()}");
        }

        $body = $response->getBody();
        $buffer = '';
        $delivered = 0;

        while (!$body->eof()) {
            if ($deadline !== null && microtime(true) >= $deadline) {
                break;
            }

            try {
                $chunk = $body->read(8192);
            } catch (\RuntimeException) {
                $chunk = ''; // read timeout on a quiet stream
            }

            if ($chunk === '') {
                if (microtime(true) - $lastData > $idleTimeout) {
                    $body->close();
                    throw new HueException(
                        "Event stream silent for {$idleTimeout} s; the bridge connection was probably lost. Reconnect."
                    );
                }
                continue;
            }

            $lastData = microtime(true);

            $buffer .= str_replace("\r\n", "\n", $chunk);

            // Events are separated by a blank line.
            while (($end = strpos($buffer, "\n\n")) !== false) {
                $block = substr($buffer, 0, $end);
                $buffer = substr($buffer, $end + 2);

                foreach ($this->eventsIn($block) as $event) {
                    $delivered++;
                    if ($onEvent($event) === false) {
                        $body->close();
                        return $delivered;
                    }
                }
            }
        }

        $body->close();

        return $delivered;
    }

    /**
     * @return array<int, array>
     */
    private function eventsIn(string $block): array
    {
        $data = '';
        foreach (explode("\n", $block) as $line) {
            if (str_starts_with($line, 'data:')) {
                $data .= ltrim(substr($line, 5));
            }
        }

        $decoded = $data !== '' ? json_decode($data, true) : null;

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }
}
