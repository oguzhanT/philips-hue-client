<?php

namespace OguzhanTogay\HueClient\Tests\V2;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use OguzhanTogay\HueClient\Exceptions\HueException;
use OguzhanTogay\HueClient\V2\HueV2Client;
use PHPUnit\Framework\TestCase;

class HueV2ClientTest extends TestCase
{
    private array $history = [];

    private const LIGHT = [
        'id' => 'l-1', 'type' => 'light', 'metadata' => ['name' => 'Desk'],
        'on' => ['on' => false], 'dimming' => ['brightness' => 40.0],
        'color' => [
            'xy' => ['x' => 0.3, 'y' => 0.3],
            'gamut' => ['red' => ['x' => 0.6915, 'y' => 0.3083], 'green' => ['x' => 0.17, 'y' => 0.7], 'blue' => ['x' => 0.1532, 'y' => 0.0475]],
        ],
        'color_temperature' => ['mirek' => 300, 'mirek_schema' => ['mirek_minimum' => 153, 'mirek_maximum' => 454]],
        'effects' => ['effect_values' => ['no_effect', 'candle', 'fire']],
    ];

    private function hue(Response ...$responses): HueV2Client
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new HueV2Client('192.168.1.2', 'APPKEY', [], new Client(['handler' => $stack, 'http_errors' => false, 'base_uri' => 'https://192.168.1.2/']));
    }

    private static function ok(array $data = []): Response
    {
        return new Response(200, [], (string) json_encode(['errors' => [], 'data' => $data]));
    }

    private function sent(int $i): array
    {
        return json_decode((string) $this->history[$i]['request']->getBody(), true) ?? [];
    }

    public function testFindsALightByNameAndSendsTheKeyHeader(): void
    {
        $light = $this->hue(self::ok([self::LIGHT]))->lights()->byName('desk');

        $this->assertSame('l-1', $light->id());
        $request = $this->history[0]['request'];
        $this->assertSame('APPKEY', $request->getHeaderLine('hue-application-key'));
        $this->assertSame('/clip/v2/resource/light', $request->getUri()->getPath());
    }

    public function testColorIsClampedIntoTheLampsGamut(): void
    {
        $hue = $this->hue(self::ok([self::LIGHT]), self::ok());
        $hue->lights()->get('l-1')->color('#00FF00', transitionMs: 500);

        $body = $this->sent(1);
        $this->assertSame('PUT', $this->history[1]['request']->getMethod());
        $this->assertSame(500, $body['dynamics']['duration']);
        // Pure sRGB green lies outside gamut C; it must land on the triangle.
        $this->assertEqualsWithDelta(0.17, $body['color']['xy']['x'], 0.05);
        $this->assertTrue($body['on']['on']);
    }

    public function testColorTemperatureIsClampedToTheLampsRange(): void
    {
        $hue = $this->hue(self::ok([self::LIGHT]), self::ok());
        $hue->lights()->get('l-1')->colorTemperature(2000);

        $this->assertSame(454, $this->sent(1)['color_temperature']['mirek']);
    }

    public function testOnlyOfferedEffects(): void
    {
        $light = $this->hue(self::ok([self::LIGHT]))->lights()->get('l-1');

        $this->expectException(\InvalidArgumentException::class);
        $light->effect('disco');
    }

    public function testBridgeErrorsBecomeExceptionsWithoutTheKey(): void
    {
        $hue = $this->hue(new Response(403, [], (string) json_encode([
            'errors' => [['description' => 'unauthorized user']], 'data' => [],
        ])));

        $this->expectException(HueException::class);
        $this->expectExceptionMessage('unauthorized user');
        $hue->lights()->all();
    }

    public function testRoomSwitchesItsGroupedLight(): void
    {
        $room = ['id' => 'r-1', 'type' => 'room', 'metadata' => ['name' => 'Office'], 'children' => [],
            'services' => [['rid' => 'g-1', 'rtype' => 'grouped_light']]];
        $grouped = ['id' => 'g-1', 'type' => 'grouped_light', 'on' => ['on' => true]];

        $hue = $this->hue(self::ok([$room]), self::ok([$grouped]), self::ok());
        $hue->rooms()->byName('office')->groupedLight()->off();

        $this->assertSame('/clip/v2/resource/grouped_light/g-1', $this->history[2]['request']->getUri()->getPath());
        $this->assertSame(['on' => ['on' => false]], $this->sent(2));
    }

    public function testSceneRecallDynamic(): void
    {
        $scene = ['id' => 's-1', 'type' => 'scene', 'metadata' => ['name' => 'Relax'], 'group' => ['rid' => 'r-1', 'rtype' => 'room']];

        $hue = $this->hue(self::ok([$scene]), self::ok());
        $hue->scenes()->byName('Relax')->recall(dynamic: true);

        $this->assertSame(['recall' => ['action' => 'dynamic_palette']], $this->sent(1));
    }

    public function testEventStreamParsesServerSentEvents(): void
    {
        $sse = ": hi\n\n"
            . "id: 1:0\ndata: [{\"type\":\"update\",\"data\":[{\"id\":\"l-1\",\"type\":\"light\",\"on\":{\"on\":true}}]}]\n\n"
            . "id: 2:0\ndata: [{\"type\":\"update\",\"data\":[{\"id\":\"l-1\",\"type\":\"light\",\"on\":{\"on\":false}}]}]\n\n";

        $seen = [];
        $count = $this->hue(new Response(200, ['Content-Type' => 'text/event-stream'], $sse))
            ->events()
            ->listen(function (array $event) use (&$seen) {
                $seen[] = $event['data'][0]['on']['on'];
            });

        $this->assertSame(2, $count);
        $this->assertSame([true, false], $seen);
        $this->assertSame('/eventstream/clip/v2', $this->history[0]['request']->getUri()->getPath());
    }

    public function testEventStreamStopsWhenTheCallbackSaysSo(): void
    {
        $sse = str_repeat("data: [{\"type\":\"update\",\"data\":[]}]\n\n", 5);

        $count = $this->hue(new Response(200, [], $sse))->events()->listen(fn () => false);

        $this->assertSame(1, $count);
    }
}
