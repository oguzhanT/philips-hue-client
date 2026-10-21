<?php

namespace OguzhanTogay\HueClient\Tests\Security;

use OguzhanTogay\HueClient\HueClient;
use OguzhanTogay\HueClient\Security\BridgeCertificate;
use PHPUnit\Framework\TestCase;

class BridgeCertificateTest extends TestCase
{
    private static function selfSigned(string $commonName): \OpenSSLCertificate
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        $csr = openssl_csr_new(['commonName' => $commonName], $key, ['digest_alg' => 'sha256']);

        return openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);
    }

    public function testReadsTheBridgeIdAndAPin(): void
    {
        $certificate = BridgeCertificate::fromCertificate(self::selfSigned('ecb5fafffe98498c'));

        $this->assertSame('ecb5fafffe98498c', $certificate->commonName);
        $this->assertTrue($certificate->matchesBridgeId('ECB5FAFFFE98498C'));
        $this->assertFalse($certificate->matchesBridgeId('001788fffe000000'));
        $this->assertMatchesRegularExpression('#^sha256//[A-Za-z0-9+/]{43}=$#', $certificate->pin);
    }

    public function testDifferentKeysGiveDifferentPins(): void
    {
        $this->assertNotSame(
            BridgeCertificate::fromCertificate(self::selfSigned('a'))->pin,
            BridgeCertificate::fromCertificate(self::selfSigned('a'))->pin
        );
    }

    public function testPinOptions(): void
    {
        $this->assertSame([], HueClient::pinOptions(null));
        $this->assertSame(['curl' => [CURLOPT_PINNEDPUBLICKEY => 'sha256//abc=']], HueClient::pinOptions('sha256//abc='));

        $this->expectException(\InvalidArgumentException::class);
        HueClient::pinOptions('abc');
    }
}
