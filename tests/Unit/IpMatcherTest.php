<?php

namespace FirewallGui\LaravelFirewallGui\Tests\Unit;

use FirewallGui\LaravelFirewallGui\Support\IpMatcher;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class IpMatcherTest extends TestCase
{
    public function test_detects_rule_types(): void
    {
        $this->assertSame(IpMatcher::TYPE_IP, IpMatcher::detectType('192.168.1.1'));
        $this->assertSame(IpMatcher::TYPE_IP, IpMatcher::detectType('2001:db8::1'));
        $this->assertSame(IpMatcher::TYPE_CIDR, IpMatcher::detectType('192.168.1.0/24'));
        $this->assertSame(IpMatcher::TYPE_CIDR, IpMatcher::detectType('2001:db8::/32'));
        $this->assertSame(IpMatcher::TYPE_RANGE, IpMatcher::detectType('192.168.1.10-192.168.1.20'));
        $this->assertSame(IpMatcher::TYPE_RANGE, IpMatcher::detectType('2001:db8::1-2001:db8::10'));
    }

    public function test_validates_ipv4_and_ipv6(): void
    {
        $this->assertTrue(IpMatcher::isValid('127.0.0.1'));
        $this->assertTrue(IpMatcher::isValid('::1'));
        $this->assertTrue(IpMatcher::isValid('10.0.0.0/8'));
        $this->assertTrue(IpMatcher::isValid('2001:db8::/32'));
        $this->assertTrue(IpMatcher::isValid('192.168.1.10-192.168.1.50'));
        $this->assertTrue(IpMatcher::isValid('2001:db8::1 - 2001:db8::ff'));

        $this->assertFalse(IpMatcher::isValid(''));
        $this->assertFalse(IpMatcher::isValid('999.999.999.999'));
        $this->assertFalse(IpMatcher::isValid('192.168.1.0/33'));
        $this->assertFalse(IpMatcher::isValid('2001:db8::/129'));
        $this->assertFalse(IpMatcher::isValid('192.168.1.50-192.168.1.10')); // reversed range
        $this->assertFalse(IpMatcher::isValid('192.168.1.1-2001:db8::1')); // mixed family
    }

    public function test_matches_single_ip_cidr_and_range(): void
    {
        $this->assertTrue(IpMatcher::matches('192.168.1.5', '192.168.1.5'));
        $this->assertFalse(IpMatcher::matches('192.168.1.6', '192.168.1.5'));

        $this->assertTrue(IpMatcher::matches('10.20.30.40', '10.20.0.0/16'));
        $this->assertFalse(IpMatcher::matches('10.21.0.1', '10.20.0.0/16'));

        $this->assertTrue(IpMatcher::matches('192.168.1.10', '192.168.1.10-192.168.1.20'));
        $this->assertTrue(IpMatcher::matches('192.168.1.15', '192.168.1.10-192.168.1.20'));
        $this->assertTrue(IpMatcher::matches('192.168.1.20', '192.168.1.10-192.168.1.20'));
        $this->assertFalse(IpMatcher::matches('192.168.1.21', '192.168.1.10-192.168.1.20'));

        $this->assertTrue(IpMatcher::matches('2001:db8::5', '2001:db8::/32'));
        $this->assertTrue(IpMatcher::matches('2001:db8::5', '2001:db8::1-2001:db8::10'));
        $this->assertFalse(IpMatcher::matches('2001:db8::11', '2001:db8::1-2001:db8::10'));
    }

    public function test_normalize_or_fail_throws_on_invalid_input(): void
    {
        [$type, $value] = IpMatcher::normalizeOrFail(' 192.168.1.1 - 192.168.1.10 ');
        $this->assertSame(IpMatcher::TYPE_RANGE, $type);
        $this->assertSame('192.168.1.1-192.168.1.10', $value);

        $this->expectException(InvalidArgumentException::class);
        IpMatcher::normalizeOrFail('not-an-ip');
    }

    public function test_describes_affected_ip_span_for_cidr_and_range(): void
    {
        $cidr = IpMatcher::describe('192.168.0.1/24');
        $this->assertNotNull($cidr);
        $this->assertSame('192.168.0.0', $cidr['first_ip']);
        $this->assertSame('192.168.0.255', $cidr['last_ip']);
        $this->assertSame('256', $cidr['total_ips']);
        $this->assertSame('192.168.0.0 – 192.168.0.255 (256 IPs)', $cidr['summary']);

        $range = IpMatcher::describe('192.168.1.10-192.168.1.50');
        $this->assertNotNull($range);
        $this->assertSame('192.168.1.10', $range['first_ip']);
        $this->assertSame('192.168.1.50', $range['last_ip']);
        $this->assertSame('41', $range['total_ips']);
    }
}
