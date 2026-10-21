<?php

namespace OguzhanTogay\HueClient\Security;

use OguzhanTogay\HueClient\Exceptions\HueException;

/**
 * The bridge's TLS certificate, for pinning.
 *
 * Bridges present a certificate whose common name is the bridge id, issued by
 * Philips' "root-bridge" CA. Without that CA to verify against, the next best
 * protection is trust on first use, like SSH: read the certificate once on a
 * network you trust, check the id, and pin its public key. From then on every
 * request refuses a different key, so a spoofed discovery answer or a
 * man-in-the-middle cannot collect the bridge token.
 *
 *   $cert = BridgeCertificate::fetch('192.168.1.2');
 *   $cert->matchesBridgeId($bridge->getId()) or die('Not the bridge you expect');
 *   $client = new HueClient($ip, $username, ['pinned_public_key' => $cert->pin]);
 */
final class BridgeCertificate
{
    private function __construct(
        /** The certificate's common name; on a real bridge, its id in lower case. */
        public readonly string $commonName,
        public readonly string $issuer,
        /** sha256//<base64 SPKI hash>, the format curl's CURLOPT_PINNEDPUBLICKEY takes. */
        public readonly string $pin
    ) {
    }

    public static function fetch(string $host, int $port = 443, float $timeout = 5.0): self
    {
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]]);

        $address = "ssl://{$host}:{$port}";
        $socket = @stream_socket_client($address, $errno, $error, $timeout, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            throw new HueException("Cannot read the certificate of {$host}:{$port}: {$error}");
        }

        $certificate = stream_context_get_params($socket)['options']['ssl']['peer_certificate'] ?? null;
        fclose($socket);

        if ($certificate === null) {
            throw new HueException("{$host}:{$port} presented no certificate.");
        }

        return self::fromCertificate($certificate);
    }

    /**
     * @param \OpenSSLCertificate|string $certificate an X.509 certificate or its PEM
     */
    public static function fromCertificate(\OpenSSLCertificate|string $certificate): self
    {
        $info = openssl_x509_parse($certificate);
        $key = openssl_pkey_get_public($certificate);
        $details = $key !== false ? openssl_pkey_get_details($key) : false;

        if ($info === false || $details === false) {
            throw new HueException('Unreadable certificate.');
        }

        // SubjectPublicKeyInfo DER: the PEM public key without armour.
        $der = base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $details['key']));

        return new self(
            strtolower((string) ($info['subject']['CN'] ?? '')),
            (string) ($info['issuer']['CN'] ?? ''),
            'sha256//' . base64_encode(hash('sha256', $der, true))
        );
    }

    /**
     * Whether this is the certificate of the bridge with this id (as discovery
     * or the bridge's config reports it, in any case).
     */
    public function matchesBridgeId(string $bridgeId): bool
    {
        return $this->commonName !== '' && hash_equals($this->commonName, strtolower($bridgeId));
    }
}
