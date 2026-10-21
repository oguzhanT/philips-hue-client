<?php

namespace OguzhanTogay\HueClient\Tests\Signal;

use Mockery;
use OguzhanTogay\HueClient\Exceptions\HueException;
use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Laravel\Notifications\HueChannel;
use OguzhanTogay\HueClient\Laravel\Notifications\HueMessage;
use OguzhanTogay\HueClient\Signal\Signaler;
use OguzhanTogay\HueClient\Signal\SignalType;
use PHPUnit\Framework\TestCase;

class SignalerTest extends TestCase
{
    private string $dir;
    private HueClient $client;
    /** @var array<int, array{string, string, array}> */
    private array $requests = [];

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/hue-signal-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        file_put_contents($this->dir . '/config.json', json_encode(['light_id' => '7', 'light_ids' => ['1', '7']]));

        $this->client = $this->fakeClient();
    }

    /**
     * @param callable(string, string, array): void|null $fail throws to make a request fail
     */
    private function fakeClient(?callable $fail = null): HueClient
    {
        $client = Mockery::mock(HueClient::class);
        $client->shouldReceive('request')->andReturnUsing(function (string $method, string $path, array $options = []) use ($fail) {
            $this->requests[] = [$method, $path, $options['json'] ?? []];
            if ($fail) {
                $fail($method, $path, $options['json'] ?? []);
            }
            return $method === 'GET'
                ? ['state' => ['on' => true, 'bri' => 80, 'ct' => 300, 'hue' => 1, 'sat' => 2, 'xy' => [0.4, 0.4], 'colormode' => 'ct']]
                : [];
        });

        return $client;
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
        Mockery::close();
    }

    public function testDefaultsToTheNotifierLamps(): void
    {
        $this->signaler()->send('success');

        $this->assertSame(['lights/1/state', 'lights/7/state'], array_column($this->requests, 1));
        $this->assertSame(SignalType::Success->state(), $this->requests[0][2]);
    }

    public function testAcceptsNotifierNamesAndIds(): void
    {
        $this->signaler()->send('waiting', [3, '4']);

        $this->assertSame(['lights/3/state', 'lights/4/state'], array_column($this->requests, 1));
        $this->assertSame('lselect', $this->requests[0][2]['alert']);
    }

    public function testRejectsUnknownSignals(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->signaler()->send('disco', 1);
    }

    public function testDoesNothingWhilePaused(): void
    {
        $signaler = $this->signaler();
        $signaler->pause();

        $this->assertTrue($signaler->isPaused());
        $this->assertFalse($signaler->send('error', 1));
        $this->assertSame([], $this->requests);

        $signaler->resume();
        $this->assertTrue($signaler->send('error', 1));
    }

    public function testATimedPauseExpires(): void
    {
        file_put_contents($this->dir . '/paused', (string) (time() - 1));

        $this->assertFalse($this->signaler()->isPaused());
    }

    public function testRestoresOnlyTheColorTheLampWasShowing(): void
    {
        $this->signaler()->send('error', 1, restoreAfter: 0);

        [$get, $put, $restore] = $this->requests;
        $this->assertSame(['GET', 'lights/1'], [$get[0], $get[1]]);
        $this->assertSame(SignalType::Error->state(), $put[2]);
        $this->assertSame(['on' => true, 'bri' => 80, 'ct' => 300, 'alert' => 'none'], $restore[2]);
        $this->assertSame([], glob($this->dir . '/signal-restore-*'), 'saved state is cleaned up');
    }

    public function testOneFailingLampDoesNotStopTheOthersOrTheRestore(): void
    {
        $this->client = $this->fakeClient(function (string $method, string $path, array $body): void {
            if ($method === 'PUT' && $path === 'lights/2/state') {
                throw new HueException('device is unreachable');
            }
        });

        try {
            $this->signaler()->send('error', [1, 2, 3], restoreAfter: 0);
            $this->fail('Expected the failure to be reported');
        } catch (HueException $e) {
            $this->assertStringContainsString('light 2', $e->getMessage());
        }

        $puts = array_values(array_filter($this->requests, static fn ($r) => $r[0] === 'PUT'));
        $this->assertContains('lights/3/state', array_column($puts, 1), 'lamp 3 still got the signal');
        $restored = array_filter($puts, static fn ($r) => ($r[2]['ct'] ?? null) === 300);
        $this->assertCount(3, $restored, 'every lamp was restored, the failed one included');
    }

    public function testALampWithoutColorGetsWhatItUnderstands(): void
    {
        $this->client = $this->fakeClient(function (string $method, string $path, array $body): void {
            if ($method === 'PUT' && isset($body['hue'])) {
                throw new HueException('parameter, hue, is not available');
            }
        });

        $this->assertTrue($this->signaler()->send('success', 4));
        $this->assertSame(['on' => true, 'bri' => 254, 'alert' => 'none'], end($this->requests)[2]);
    }

    public function testAnEarlierUnfinishedSignalKeepsTheRealState(): void
    {
        file_put_contents($this->dir . '/signal-restore-1.json', json_encode(['on' => false]));

        $this->signaler()->send('error', 1, restoreAfter: 0);

        $this->assertNotContains('GET', array_column($this->requests, 0), 'no new capture');
        $this->assertSame(['on' => false, 'alert' => 'none'], end($this->requests)[2]);
    }

    public function testUnknownLightNamesAreReported(): void
    {
        $this->client->shouldReceive('lights->getByName')->with('Nowhere')->andReturnNull();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown light 'Nowhere'");
        $this->signaler()->send('error', 'Nowhere');
    }

    public function testExpandsTildeInTheConfigPath(): void
    {
        putenv('HUE_NOTIFY_CONFIG=~/somewhere/config.json');
        try {
            $path = (new Signaler(null))->pausePath();
        } finally {
            putenv('HUE_NOTIFY_CONFIG');
        }

        $this->assertStringStartsNotWith('~', $path);
        $this->assertStringEndsWith('/somewhere/paused', $path);
    }

    public function testLaravelChannelUsesMessageThenRoute(): void
    {
        $channel = new HueChannel($this->clientWith($this->signaler()));

        $notification = new class {
            public function toHue($notifiable): HueMessage
            {
                return HueMessage::error();
            }
        };
        $notifiable = new class {
            public function routeNotificationFor(string $channel, $notification): array
            {
                return $channel === 'hue' ? [5] : [];
            }
        };

        $channel->send($notifiable, $notification);

        $this->assertSame([['PUT', 'lights/5/state', SignalType::Error->state()]], $this->requests);
    }

    private function signaler(): Signaler
    {
        return new Signaler($this->client, $this->dir . '/config.json');
    }

    private function clientWith(Signaler $signaler): HueClient
    {
        $client = Mockery::mock(HueClient::class);
        $client->shouldReceive('signal')->andReturn($signaler);

        return $client;
    }
}
