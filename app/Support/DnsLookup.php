<?php

namespace App\Support;

/**
 * A small, time-bounded DNS check.
 *
 * The system resolver (dns_get_record, gethostbyname) waits up to a minute for
 * a name that does not exist — and a missing name is exactly what a deployment
 * check has to report. This client sends one UDP query to the resolver from
 * /etc/resolv.conf and gives up after a couple of seconds.
 */
class DnsLookup
{
    public const OK = 'ok';

    public const NXDOMAIN = 'nxdomain';

    public const TIMEOUT = 'timeout';

    public const UNAVAILABLE = 'unavailable';

    /**
     * Ask the resolver about an A record.
     *
     * @param  float  $timeout  seconds to wait for one resolver
     * @param  list<string>|null  $resolvers  override the servers (testing)
     * @return array{status: string, addresses: list<string>}
     */
    public static function a(string $host, float $timeout = 2.0, ?array $resolvers = null): array
    {
        $host = rtrim(strtolower(trim($host)), '.');

        if ($host === '') {
            return ['status' => self::NXDOMAIN, 'addresses' => []];
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false || $host === 'localhost') {
            return ['status' => self::OK, 'addresses' => [$host]];
        }

        $query = self::query($host);
        $answered = false;

        foreach ($resolvers ?? self::resolvers() as $resolver) {
            $answer = self::ask($resolver, $query, $timeout);

            if ($answer === null) {
                continue;
            }

            $answered = true;

            if ($answer['status'] === self::OK) {
                return $answer;
            }

            if ($answer['status'] === self::NXDOMAIN) {
                return $answer;
            }
        }

        return $answered
            ? ['status' => self::TIMEOUT, 'addresses' => []]
            : ['status' => self::UNAVAILABLE, 'addresses' => []];
    }

    /**
     * Send one query and parse the reply.
     *
     * @return array{status: string, addresses: list<string>}|null null when the
     *                                                              server could not be reached at all
     */
    protected static function ask(string $resolver, string $query, float $timeout): ?array
    {
        $socket = @stream_socket_client(
            'udp://'.$resolver.':53',
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
        );

        if ($socket === false) {
            return null;
        }

        stream_set_timeout($socket, (int) ceil($timeout));
        $written = @fwrite($socket, $query);

        if ($written === false) {
            fclose($socket);

            return null;
        }

        $response = @fread($socket, 1500);
        fclose($socket);

        if (! is_string($response) || strlen($response) < 12) {
            return null;
        }

        return self::parse($response);
    }

    /**
     * @return array{status: string, addresses: list<string>}
     */
    protected static function parse(string $packet): array
    {
        $header = unpack('nid/nflags/nqd/nan/nns/nar', substr($packet, 0, 12));
        $rcode = $header['flags'] & 0x000F;

        if (in_array($rcode, [2, 5], true)) {
            // Server failure / refused: the resolver answered, but not about
            // this name. Not a verdict on the domain.
            return ['status' => self::TIMEOUT, 'addresses' => []];
        }

        if ($rcode === 3) {
            return ['status' => self::NXDOMAIN, 'addresses' => []];
        }

        if ($rcode !== 0) {
            return ['status' => self::TIMEOUT, 'addresses' => []];
        }

        // Any answer record at all (A, AAAA, CNAME, ALIAS…) means the name
        // exists, which is all a deployment check needs to know.
        return $header['an'] > 0 || $header['ns'] > 0 || $header['ar'] > 0
            ? ['status' => self::OK, 'addresses' => self::addresses($packet)]
            : ['status' => self::NXDOMAIN, 'addresses' => []];
    }

    /**
     * A records of the answer section.
     *
     * @return list<string>
     */
    protected static function addresses(string $packet): array
    {
        $header = unpack('nid/nflags/nqd/nan', substr($packet, 0, 8));
        $offset = 12;

        for ($i = 0; $i < $header['qd']; $i++) {
            $offset = self::skipName($packet, $offset) + 4;
        }

        $addresses = [];

        for ($i = 0; $i < $header['an']; $i++) {
            $offset = self::skipName($packet, $offset);

            if ($offset + 10 > strlen($packet)) {
                break;
            }

            $type = unpack('n', substr($packet, $offset, 2))[1] ?? 0;
            $length = unpack('n', substr($packet, $offset + 8, 2))[1] ?? 0;
            $offset += 10;

            if ($type === 1 && $length === 4) {
                $addresses[] = inet_ntop(substr($packet, $offset, 4));
            }

            $offset += $length;
        }

        return array_values(array_filter($addresses));
    }

    /** Skip a (possibly compressed) name and return the next offset. */
    protected static function skipName(string $packet, int $offset): int
    {
        $length = strlen($packet);

        while ($offset < $length) {
            $byte = ord($packet[$offset]);

            if ($byte === 0) {
                return $offset + 1;
            }

            if (($byte & 0xC0) === 0xC0) {
                return $offset + 2;
            }

            $offset += $byte + 1;
        }

        return $offset;
    }

    /** A minimal A-record query packet. */
    protected static function query(string $host): string
    {
        $id = random_int(0, 0xFFFF);
        $packet = pack('n6', $id, 0x0100, 1, 0, 0, 0);

        foreach (explode('.', $host) as $label) {
            $packet .= chr(strlen($label)).$label;
        }

        return $packet."\0".pack('n2', 1, 1);
    }

    /**
     * Nameservers to ask: the system resolver first, public ones as a fallback.
     *
     * @return list<string>
     */
    public static function resolvers(): array
    {
        $servers = [];

        $config = @file_get_contents('/etc/resolv.conf');

        if (is_string($config)) {
            preg_match_all('/^\s*nameserver\s+([0-9a-fA-F:.]+)/m', $config, $matches);

            foreach ($matches[1] ?? [] as $server) {
                // 127.0.0.53 (systemd-resolved stub) does not answer plain UDP
                // queries from an application on some hosts; keep it anyway,
                // the public servers below are the backup.
                $servers[] = $server;
            }
        }

        return array_values(array_unique(array_merge($servers, ['1.1.1.1', '8.8.8.8'])));
    }
}
