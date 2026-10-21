<?php

namespace OguzhanTogay\HueClient\Tests\Signal;

use Mockery;
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

        $this->client = Mockery::mock(HueClient::class);
        $this->client->shouldReceive('request')->andReturnUsing(function (string $method, string $path, array $options = []) {
            $this->requests[] = [$method, $path, $options['json'] ?? []];
            return $method === 'GET'
                ? ['state' => ['on' => true, 'bri' => 80, 'ct' => 300, 'hue' => 1, 'sat' => 2, 'xy' => [0.4, 0.4], 'colormode' => 'ct']]
                : [];
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/paused');
        @unlink($this->dir . '/config.json');
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
