<?php

namespace OguzhanTogay\HueClient\Discovery;

/**
 * Finds bridges on the local network by asking for _hue._tcp.local over
 * multicast DNS. Works without internet access; needs ext-sockets.
 *
 * The query is sent from an ordinary port with the "unicast response" bit
 * set, so bridges answer this socket directly (RFC 6762, 5.4 and 6.7).
 */
class MdnsDiscovery
{
    private const SERVICE = '_hue._tcp.local';
    private const GROUP = '224.0.0.251';
    private const PORT = 5353;

    private const TYPE_A = 1;
    private const TYPE_PTR = 12;
    private const TYPE_TXT = 16;
    private const TYPE_SRV = 33;

    public static function isAvailable(): bool
    {
        return extension_loaded('sockets');
    }

    /**
     * @return Bridge[]
     */
    public function discover(float $timeout = 2.0): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($socket === false) {
            return [];
        }

        try {
            $query = self::buildQuery();
            if (@socket_sendto($socket, $query, strlen($query), 0, self::GROUP, self::PORT) === false) {
                return [];
            }

            $records = [];
            $deadline = microtime(true) + $timeout;

            while (($left = $deadline - microtime(true)) > 0) {
                $read = [$socket];
                $write = $except = null;
                $seconds = (int) $left;
                if (@socket_select($read, $write, $except, $seconds, (int) (($left - $seconds) * 1_000_000)) < 1) {
                    break;
                }

                $packet = '';
                $from = '';
                $port = 0;
                if (@socket_recvfrom($socket, $packet, 9000, 0, $from, $port) > 0) {
                    $records = array_merge($records, self::parseRecords($packet));
                }
            }

            return self::bridgesFrom($records);
        } finally {
            socket_close($socket);
        }
    }

    /**
     * A PTR question for the Hue service, unicast response requested.
     */
    public static function buildQuery(): string
    {
        $header = pack('n6', 0, 0, 1, 0, 0, 0);

        return $header . self::encodeName(self::SERVICE) . pack('n2', self::TYPE_PTR, 0x8001);
    }

    /**
     * Resource records from one DNS message: answers, authority and additional.
     *
     * @return array<int, array{name: string, type: int, data: mixed}>
     */
    public static function parseRecords(string $packet): array
    {
        if (strlen($packet) < 12) {
            return [];
        }

        $counts = unpack('nid/nflags/nqd/nan/nns/nar', substr($packet, 0, 12));
        $offset = 12;

        try {
            for ($i = 0; $i < (int) $counts['qd']; $i++) {
                self::readName($packet, $offset);
                $offset += 4;
            }

            $records = [];
            $total = (int) $counts['an'] + (int) $counts['ns'] + (int) $counts['ar'];
            for ($i = 0; $i < $total; $i++) {
                $name = self::readName($packet, $offset);
                self::need($packet, $offset, 10);
                $fixed = unpack('ntype/nclass/Nttl/nlength', substr($packet, $offset, 10));
                $offset += 10;
                $start = $offset;
                self::need($packet, $offset, (int) $fixed['length']);
                $offset += $fixed['length'];

                $data = match ($fixed['type']) {
                    self::TYPE_A => $fixed['length'] === 4 ? inet_ntop(substr($packet, $start, 4)) : null,
                    self::TYPE_PTR => self::readName($packet, $start),
                    self::TYPE_SRV => self::readSrv($packet, $start),
                    self::TYPE_TXT => self::readTxt(substr($packet, $start, $fixed['length'])),
                    default => null,
                };

                if ($data !== null && $data !== false) {
                    $records[] = ['name' => strtolower($name), 'type' => $fixed['type'], 'data' => $data];
                }
            }

            return $records;
        } catch (\OutOfRangeException) {
            return []; // truncated or malformed packet
        }
    }

    /**
     * @param array<int, array{name: string, type: int, data: mixed}> $records
     * @return Bridge[]
     */
    public static function bridgesFrom(array $records): array
    {
        $byType = static function (int $type) use ($records): array {
            $out = [];
            foreach ($records as $record) {
                if ($record['type'] === $type) {
                    $out[$record['name']][] = $record['data'];
                }
            }
            return $out;
        };

        $ptr = $byType(self::TYPE_PTR);
        $srv = $byType(self::TYPE_SRV);
        $txt = $byType(self::TYPE_TXT);
        $a = $byType(self::TYPE_A);

        $bridges = [];
        foreach ($ptr[strtolower(self::SERVICE)] ?? [] as $instance) {
            $instance = strtolower($instance);
            $service = $srv[$instance][0] ?? null;
            $ip = $service ? ($a[strtolower($service['target'])][0] ?? null) : null;
            if ($ip === null) {
                continue;
            }

            $fields = array_merge(...($txt[$instance] ?? [[]]));
            $id = strtolower($fields['bridgeid'] ?? strtok($instance, '.'));
            $name = preg_replace('/\._hue\._tcp\.local$/', '', $instance);

            $bridges[$id] = new Bridge($id, $ip, (int) $service['port'], $name, $fields['modelid'] ?? null);
        }

        return array_values($bridges);
    }

    private static function encodeName(string $name): string
    {
        $encoded = '';
        foreach (explode('.', $name) as $label) {
            $encoded .= chr(strlen($label)) . $label;
        }

        return $encoded . "\0";
    }

    /**
     * Reads a possibly compressed name and moves $offset past it.
     */
    private static function readName(string $packet, int &$offset, int $depth = 0): string
    {
        if ($depth > 10) {
            throw new \OutOfRangeException('Name pointer loop');
        }

        $labels = [];
        while (true) {
            if (!isset($packet[$offset])) {
                throw new \OutOfRangeException('Truncated name');
            }

            $length = ord($packet[$offset]);
            if ($length === 0) {
                $offset++;
                break;
            }

            if (($length & 0xC0) === 0xC0) {
                if (!isset($packet[$offset + 1])) {
                    throw new \OutOfRangeException('Truncated pointer');
                }
                $pointer = (($length & 0x3F) << 8) | ord($packet[$offset + 1]);
                $offset += 2;
                $labels[] = self::readName($packet, $pointer, $depth + 1);
                break;
            }

            $labels[] = substr($packet, $offset + 1, $length);
            $offset += $length + 1;
        }

        return implode('.', array_filter($labels, static fn ($l) => $l !== ''));
    }

    /**
     * Stops parsing before unpack() reads past the end of a short packet.
     */
    private static function need(string $packet, int $offset, int $bytes): void
    {
        if ($offset + $bytes > strlen($packet)) {
            throw new \OutOfRangeException('Truncated record');
        }
    }

    private static function readSrv(string $packet, int $offset): array
    {
        self::need($packet, $offset, 6);
        $fields = unpack('npriority/nweight/nport', substr($packet, $offset, 6));
        $offset += 6;

        return ['port' => $fields['port'], 'target' => self::readName($packet, $offset)];
    }

    /**
     * @return array<string, string>
     */
    private static function readTxt(string $data): array
    {
        $fields = [];
        $offset = 0;
        while ($offset < strlen($data)) {
            $length = ord($data[$offset]);
            $entry = substr($data, $offset + 1, $length);
            $offset += $length + 1;
            [$key, $value] = array_pad(explode('=', $entry, 2), 2, '');
            if ($key !== '') {
                $fields[strtolower($key)] = $value;
            }
        }

        return $fields;
    }
}
