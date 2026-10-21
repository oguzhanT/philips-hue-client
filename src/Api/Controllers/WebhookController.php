<?php

namespace OguzhanTogay\HueClient\Api\Controllers;

use OguzhanTogay\HueClient\Api\Webhooks\WebhookSignals;
use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Signal\SignalType;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * POST /hooks/github and /hooks/sentry: build, deploy and error state on the
 * lamps. These endpoints skip the API key; each delivery must carry a valid
 * signature for the secret configured for it, or it is refused.
 */
class WebhookController extends BaseController
{
    /**
     * @param array{github_secret?: ?string, sentry_secret?: ?string, lights?: string[], branches?: string[]} $options
     */
    public function __construct(HueClient $hueClient, private readonly array $options = [])
    {
        parent::__construct($hueClient);
    }

    public function github(Request $request, Response $response): Response
    {
        $secret = (string) ($this->options['github_secret'] ?? '');
        if ($secret === '') {
            return $this->errorResponse($response, 'GitHub webhooks are off: set HUE_GITHUB_WEBHOOK_SECRET.', 404);
        }

        $body = (string) $request->getBody();
        if (!WebhookSignals::verifyGithub($body, $request->getHeaderLine('X-Hub-Signature-256'), $secret)) {
            return $this->errorResponse($response, 'Invalid signature.', 401);
        }

        $event = $request->getHeaderLine('X-GitHub-Event');
        if ($event === 'ping') {
            return $this->successResponse($response, null, 'pong');
        }

        $signal = WebhookSignals::github($event, $this->decode($body), $this->options['branches'] ?? []);

        return $this->show($response, $signal, "github {$event}");
    }

    public function sentry(Request $request, Response $response): Response
    {
        $secret = (string) ($this->options['sentry_secret'] ?? '');
        if ($secret === '') {
            return $this->errorResponse($response, 'Sentry webhooks are off: set HUE_SENTRY_WEBHOOK_SECRET.', 404);
        }

        $body = (string) $request->getBody();
        if (!WebhookSignals::verifySentry($body, $request->getHeaderLine('Sentry-Hook-Signature'), $secret)) {
            return $this->errorResponse($response, 'Invalid signature.', 401);
        }

        $resource = $request->getHeaderLine('Sentry-Hook-Resource');
        $signal = WebhookSignals::sentry($resource, $this->decode($body));

        return $this->show($response, $signal, "sentry {$resource}");
    }

    private function show(Response $response, ?SignalType $signal, string $what): Response
    {
        if ($signal === null) {
            return $this->successResponse($response, ['signal' => null], "Ignored {$what}");
        }

        $lights = $this->options['lights'] ?? [];

        try {
            $shown = $this->hueClient->signal()->send($signal, $lights !== [] ? $lights : null);
        } catch (\InvalidArgumentException | \OguzhanTogay\HueClient\Exceptions\HueException $e) {
            return $this->errorResponse($response, $e->getMessage(), 502);
        }

        return $this->successResponse(
            $response,
            ['signal' => $signal->value, 'paused' => !$shown],
            $shown ? "Showed {$signal->value} for {$what}" : 'Signals are paused'
        );
    }

    private function decode(string $body): array
    {
        $data = json_decode($body, true);

        return is_array($data) ? $data : [];
    }
}
