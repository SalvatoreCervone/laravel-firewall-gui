<?php

namespace FirewallGui\LaravelFirewallGui\Support;

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\IpUtils;

class IpMatcher
{
    public const TYPE_IP = 'ip';
    public const TYPE_CIDR = 'cidr';
    public const TYPE_RANGE = 'range';

    /**
     * Detect whether a given value is a single IP, CIDR block, or IP range.
     */
    public static function detectType(string $value): string
    {
        $value = trim($value);

        if (str_contains($value, '/')) {
            return self::TYPE_CIDR;
        }

        if (str_contains($value, '-')) {
            return self::TYPE_RANGE;
        }

        return self::TYPE_IP;
    }

    /**
     * Validate an IP, CIDR, or range value.
     */
    public static function isValid(string $value, ?string $expectedType = null): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        $type = $expectedType ?? self::detectType($value);

        return match ($type) {
            self::TYPE_IP => filter_var($value, FILTER_VALIDATE_IP) !== false,
            self::TYPE_CIDR => self::isValidCidr($value),
            self::TYPE_RANGE => self::isValidRange($value),
            default => false,
        };
    }

    /**
     * Validate and normalize a rule value, returning [$ruleType, $normalizedValue].
     *
     * @return array{0: string, 1: string}
     */
    public static function normalizeOrFail(string $value, ?string $ruleType = null): array
    {
        $trimmed = trim($value);
        $detected = $ruleType ?? self::detectType($trimmed);

        if (! self::isValid($trimmed, $detected)) {
            throw new InvalidArgumentException("Invalid firewall {$detected} value: [{$value}].");
        }

        if ($detected === self::TYPE_RANGE) {
            [$start, $end] = array_map('trim', explode('-', $trimmed, 2));
            $trimmed = "{$start}-{$end}";
        }

        return [$detected, $trimmed];
    }

    /**
     * Determine if a client IP matches a rule value.
     */
    public static function matches(string $clientIp, string $ruleValue, ?string $ruleType = null): bool
    {
        $clientIp = trim($clientIp);
        $ruleValue = trim($ruleValue);

        if (filter_var($clientIp, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $type = $ruleType ?? self::detectType($ruleValue);

        return match ($type) {
            self::TYPE_IP, self::TYPE_CIDR => IpUtils::checkIp($clientIp, $ruleValue),
            self::TYPE_RANGE => self::matchesRange($clientIp, $ruleValue),
            default => false,
        };
    }

    /**
     * Describe the exact IP span and count affected by an IP, CIDR, or range.
     *
     * @return array{type: string, first_ip: string, last_ip: string, total_ips: string, summary: string}|null
     */
    public static function describe(string $value, ?string $ruleType = null): ?array
    {
        $value = trim($value);
        $type = $ruleType ?? self::detectType($value);

        if (! self::isValid($value, $type)) {
            return null;
        }

        if ($type === self::TYPE_IP) {
            return [
                'type' => self::TYPE_IP,
                'first_ip' => $value,
                'last_ip' => $value,
                'total_ips' => '1',
                'summary' => "{$value} (1 IP)",
            ];
        }

        if ($type === self::TYPE_CIDR) {
            [$ip, $prefix] = explode('/', $value, 2);
            $prefixInt = (int) $prefix;
            $bin = inet_pton($ip);
            if ($bin === false) {
                return null;
            }

            $byteLen = strlen($bin);
            $hostBits = ($byteLen * 8) - $prefixInt;

            $firstBin = '';
            $lastBin = '';
            $remaining = $prefixInt;

            for ($i = 0; $i < $byteLen; $i++) {
                $byte = ord($bin[$i]);
                if ($remaining >= 8) {
                    $mask = 0xFF;
                    $remaining -= 8;
                } elseif ($remaining > 0) {
                    $mask = (0xFF << (8 - $remaining)) & 0xFF;
                    $remaining = 0;
                } else {
                    $mask = 0x00;
                }

                $firstBin .= chr($byte & $mask);
                $lastBin .= chr(($byte & $mask) | (~$mask & 0xFF));
            }

            $firstIp = (string) inet_ntop($firstBin);
            $lastIp = (string) inet_ntop($lastBin);
            $totalIps = $hostBits <= 53
                ? number_format((int) (2 ** $hostBits))
                : "2^{$hostBits}";

            return [
                'type' => self::TYPE_CIDR,
                'first_ip' => $firstIp,
                'last_ip' => $lastIp,
                'total_ips' => $totalIps,
                'summary' => "{$firstIp} – {$lastIp} ({$totalIps} IPs)",
            ];
        }

        if ($type === self::TYPE_RANGE) {
            [$start, $end] = array_map('trim', explode('-', $value, 2));
            $totalIps = 'Range';

            if (filter_var($start, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                $startUnsigned = (float) sprintf('%u', ip2long($start));
                $endUnsigned = (float) sprintf('%u', ip2long($end));
                $totalIps = number_format((int) ($endUnsigned - $startUnsigned + 1));
            }

            return [
                'type' => self::TYPE_RANGE,
                'first_ip' => $start,
                'last_ip' => $end,
                'total_ips' => $totalIps,
                'summary' => "{$start} – {$end} ({$totalIps} IPs)",
            ];
        }

        return null;
    }

    protected static function isValidCidr(string $cidr): bool
    {
        $parts = explode('/', $cidr);
        if (count($parts) !== 2) {
            return false;
        }

        [$ip, $prefix] = $parts;
        if ($ip === '' || $prefix === '' || ! ctype_digit($prefix)) {
            return false;
        }

        $prefixInt = (int) $prefix;

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $prefixInt >= 0 && $prefixInt <= 32;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return $prefixInt >= 0 && $prefixInt <= 128;
        }

        return false;
    }

    protected static function isValidRange(string $range): bool
    {
        $parts = explode('-', $range);
        if (count($parts) !== 2) {
            return false;
        }

        [$start, $end] = array_map('trim', $parts);

        $isStartV4 = filter_var($start, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        $isEndV4 = filter_var($end, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;

        $isStartV6 = filter_var($start, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        $isEndV6 = filter_var($end, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;

        if (! (($isStartV4 && $isEndV4) || ($isStartV6 && $isEndV6))) {
            return false;
        }

        $startBin = inet_pton($start);
        $endBin = inet_pton($end);

        if ($startBin === false || $endBin === false) {
            return false;
        }

        return strcmp($startBin, $endBin) <= 0;
    }

    protected static function matchesRange(string $clientIp, string $range): bool
    {
        if (! self::isValidRange($range)) {
            return false;
        }

        [$start, $end] = array_map('trim', explode('-', $range, 2));

        $clientBin = inet_pton($clientIp);
        $startBin = inet_pton($start);
        $endBin = inet_pton($end);

        if ($clientBin === false || $startBin === false || $endBin === false) {
            return false;
        }

        if (strlen($clientBin) !== strlen($startBin)) {
            return false;
        }

        return strcmp($clientBin, $startBin) >= 0 && strcmp($clientBin, $endBin) <= 0;
    }
}
