<?php

namespace OguzhanTogay\HueClient\Tests;

use OguzhanTogay\HueClient\Exceptions\HueException;
use OguzhanTogay\HueClient\HueClient;
use PHPUnit\Framework\TestCase;

class HueClientRequestTest extends TestCase
{
    public function testRequestWorksWithoutTheOptionalCachePackage(): void
    {
        // Port 9 (discard) refuses at once. Before 1.1.0 this died with
        // "must not be accessed before initialization" instead of a HueException.
        $client = new HueClient('127.0.0.1:9', 'SECRET-TOKEN', ['retry_attempts' => 0, 'timeout' => 1, 'cache_enabled' => false]);

        $this->expectException(HueException::class);
        $client->request('GET', 'lights');
    }

    public function testErrorMessagesDoNotCarryTheToken(): void
    {
        $client = new HueClient('127.0.0.1:9', 'SECRET-TOKEN', ['retry_attempts' => 0, 'timeout' => 1, 'cache_enabled' => false]);

        try {
            $client->request('GET', 'lights');
            $this->fail('Expected a HueException');
        } catch (HueException $e) {
            $this->assertStringNotContainsString('SECRET-TOKEN', $e->getMessage());
            $this->assertNull($e->getPrevious(), 'the Guzzle exception holds the full URI');
        }
    }

    public function testRedact(): void
    {
        $client = new HueClient('192.168.1.2', 'abc123');

        $this->assertSame('GET https://192.168.1.2/api/***/lights', $client->redact('GET https://192.168.1.2/api/abc123/lights'));
    }
}
