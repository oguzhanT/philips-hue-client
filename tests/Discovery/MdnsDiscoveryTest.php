<?php

namespace OguzhanTogay\HueClient\Tests\Discovery;

use OguzhanTogay\HueClient\Discovery\MdnsDiscovery;
use PHPUnit\Framework\TestCase;

class MdnsDiscoveryTest extends TestCase
{
    private static function name(string $name): string
    {
        $out = '';
        foreach (explode('.', $name) as $label) {
            $out .= chr(strlen($label)) . $label;
        }
        return $out . "\0";
    }

    private static function record(string $name, int $type, string $data): string
    {
        return self::name($name) . pack('nnNn', $type, 0x8001, 120, strlen($data)) . $data;
    }

    /** A bridge's answer, the way a Hue bridge sends it. */
    private static function answer(): string
    {
        $instance = 'Hue Bridge - 98498C._hue._tcp.local';
        $txt = chr(strlen('bridgeid=ecb5fafffe98498c')) . 'bridgeid=ecb5fafffe98498c'
            . chr(strlen('modelid=BSB002')) . 'modelid=BSB002';

        return pack('n6', 0, 0x8400, 0, 1, 0, 3)
            . self::record('_hue._tcp.local', 12, self::name($instance))
            . self::record($instance, 16, $txt)
            . self::record($instance, 33, pack('nnn', 0, 0, 443) . self::name('ecb5fafffe98498c.local'))
            . self::record('ecb5fafffe98498c.local', 1, inet_pton('192.168.0.100'));
    }

    public function testQueryAsksForTheHueServiceWithUnicastResponse(): void
    {
        $query = MdnsDiscovery::buildQuery();

        $this->assertStringContainsString("\x04_hue\x04_tcp\x05local\x00", $query);
        $this->assertSame(pack('n2', 12, 0x8001), substr($query, -4));
    }

    public function testParsesABridgeAnswer(): void
    {
        $bridges = MdnsDiscovery::bridgesFrom(MdnsDiscovery::parseRecords(self::answer()));

        $this->assertCount(1, $bridges);
        $this->assertSame('ecb5fafffe98498c', $bridges[0]->getId());
        $this->assertSame('192.168.0.100', $bridges[0]->getIp());
        $this->assertSame(443, $bridges[0]->getPort());
        $this->assertSame('BSB002', $bridges[0]->getModelId());
    }

    public function testIgnoresGarbage(): void
    {
        $this->assertSame([], MdnsDiscovery::parseRecords("\x00\x01garbage"));
        $this->assertSame([], MdnsDiscovery::parseRecords(substr(self::answer(), 0, 40)));
    }
}
