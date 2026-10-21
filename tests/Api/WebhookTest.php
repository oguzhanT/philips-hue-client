<?php

namespace OguzhanTogay\HueClient\Tests\Api;

use Mockery;
use OguzhanTogay\HueClient\Api\RestApi;
use OguzhanTogay\HueClient\Api\Webhooks\WebhookSignals;
use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Signal\Signaler;
use OguzhanTogay\HueClient\Signal\SignalType;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Headers;
use Slim\Psr7\Request;
use Slim\Psr7\Uri;

class WebhookTest extends TestCase
{
    private array $sent = [];

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testGithubMapping(): void
    {
        $run = static fn (string $action, ?string $conclusion = null, string $branch = 'main') => [
            'action' => $action,
            'workflow_run' => ['conclusion' => $conclusion, 'head_branch' => $branch],
        ];

        $this->assertSame(SignalType::Working, WebhookSignals::github('workflow_run', $run('in_progress')));
        $this->assertSame(SignalType::Success, WebhookSignals::github('workflow_run', $run('completed', 'success')));
        $this->assertSame(SignalType::Error, WebhookSignals::github('workflow_run', $run('completed', 'failure')));
        $this->assertSame(SignalType::Warning, WebhookSignals::github('workflow_run', $run('completed', 'cancelled')));
        $this->assertNull(WebhookSignals::github('workflow_run', $run('completed', 'skipped')));
        $this->assertNull(WebhookSignals::github('workflow_run', $run('completed', 'failure', 'feature/x'), ['main']));
        $this->assertSame(SignalType::Error, WebhookSignals::github('deployment_status', ['deployment_status' => ['state' => 'failure']]));
        $this->assertNull(WebhookSignals::github('push', []));

        $fromFork = $run('completed', 'failure') + ['repository' => ['full_name' => 'me/app']];
        $fromFork['workflow_run']['head_repository'] = ['full_name' => 'stranger/app'];
        $this->assertNull(WebhookSignals::github('workflow_run', $fromFork), 'forks are ignored');
        $fromFork['workflow_run']['head_repository'] = ['full_name' => 'me/app'];
        $this->assertSame(SignalType::Error, WebhookSignals::github('workflow_run', $fromFork));
    }

    public function testSentryMapping(): void
    {
        $this->assertSame(SignalType::Error, WebhookSignals::sentry('issue', ['action' => 'created']));
        $this->assertSame(SignalType::Success, WebhookSignals::sentry('issue', ['action' => 'resolved']));
        $this->assertSame(SignalType::Warning, WebhookSignals::sentry('metric_alert', ['action' => 'warning']));
        $this->assertSame(SignalType::Error, WebhookSignals::sentry('event_alert', ['action' => 'triggered']));
        $this->assertNull(WebhookSignals::sentry('installation', ['action' => 'created']));
    }

    public function testSignedGithubDeliveryLightsTheLampsWithoutAnApiKey(): void
    {
        $body = (string) json_encode(['action' => 'completed', 'workflow_run' => ['conclusion' => 'failure', 'head_branch' => 'main']]);
        $response = $this->post('/hooks/github', $body, [
            'X-GitHub-Event' => 'workflow_run',
            'X-Hub-Signature-256' => 'sha256=' . hash_hmac('sha256', $body, 'gh-secret'),
        ]);

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame([[SignalType::Error, ['Desk']]], $this->sent);
    }

    public function testRejectsABadSignature(): void
    {
        $response = $this->post('/hooks/github', '{"action":"completed"}', [
            'X-GitHub-Event' => 'workflow_run',
            'X-Hub-Signature-256' => 'sha256=' . str_repeat('0', 64),
        ]);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame([], $this->sent);
    }

    public function testSignedSentryDelivery(): void
    {
        $body = '{"action":"created","data":{"issue":{"title":"boom"}}}';
        $response = $this->post('/hooks/sentry', $body, [
            'Sentry-Hook-Resource' => 'issue',
            'Sentry-Hook-Signature' => hash_hmac('sha256', $body, 'sentry-secret'),
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(SignalType::Error, $this->sent[0][0]);
    }

    public function testWebhookWithoutASecretIsOff(): void
    {
        $response = $this->post('/hooks/sentry', '{}', ['Sentry-Hook-Resource' => 'issue'], ['github_secret' => 'x']);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testOtherRoutesStillNeedTheKey(): void
    {
        $response = $this->post('/api/groups', '{}', []);

        $this->assertSame(401, $response->getStatusCode());
    }

    private function post(string $path, string $body, array $headers, ?array $webhooks = null): ResponseInterface
    {
        $signaler = Mockery::mock(Signaler::class);
        $signaler->shouldReceive('send')->andReturnUsing(function ($type, $lights) {
            $this->sent[] = [$type, $lights];
            return true;
        });
        $client = Mockery::mock(HueClient::class);
        $client->shouldReceive('signal')->andReturn($signaler);

        $api = new RestApi($client, [
            'api_key' => 'key',
            'webhooks' => $webhooks ?? ['github_secret' => 'gh-secret', 'sentry_secret' => 'sentry-secret', 'lights' => ['Desk']],
        ]);

        $request = new Request(
            'POST',
            new Uri('http', 'localhost', 8080, $path),
            new Headers($headers + ['Content-Type' => 'application/json']),
            [],
            ['REMOTE_ADDR' => '127.0.0.1'],
            (new StreamFactory())->createStream($body)
        );

        return $api->getApp()->handle($request);
    }
}
