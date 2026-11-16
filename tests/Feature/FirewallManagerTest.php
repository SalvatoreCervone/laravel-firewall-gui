<?php

namespace FirewallGui\LaravelFirewallGui\Tests\Feature;

use FirewallGui\LaravelFirewallGui\Facades\Firewall;
use FirewallGui\LaravelFirewallGui\Models\FirewallGroup;
use FirewallGui\LaravelFirewallGui\Models\FirewallRule;
use FirewallGui\LaravelFirewallGui\Tests\TestCase;

class FirewallManagerTest extends TestCase
{
    public function test_allows_unlisted_ip_in_blacklist_only_mode(): void
    {
        $decision = Firewall::check('203.0.113.10');

        $this->assertTrue($decision->allowed);
        $this->assertSame('allowed', $decision->action);
    }

    public function test_blocks_blacklisted_single_ip_cidr_and_range(): void
    {
        Firewall::block('203.0.113.10', 'Bad IP');
        Firewall::block('198.51.100.0/24', 'Bad CIDR');
        Firewall::block('192.0.2.10-192.0.2.20', 'Bad Range');

        $this->assertTrue(Firewall::check('203.0.113.10')->isBlocked());
        $this->assertTrue(Firewall::check('198.51.100.42')->isBlocked());
        $this->assertTrue(Firewall::check('192.0.2.15')->isBlocked());
        $this->assertTrue(Firewall::check('192.0.2.25')->allowed);
    }

    public function test_whitelist_overrides_blacklist_by_default(): void
    {
        Firewall::block('198.51.100.0/24', 'Broad block');
        Firewall::allow('198.51.100.5', 'Trusted partner');

        $this->assertTrue(Firewall::check('198.51.100.5')->allowed);
        $this->assertTrue(Firewall::check('198.51.100.6')->isBlocked());
    }

    public function test_whitelist_only_mode_blocks_unknown_ips(): void
    {
        config()->set('firewall-gui.mode', 'whitelist_only');

        Firewall::allow('10.0.0.5', 'Office');

        $this->assertTrue(Firewall::check('10.0.0.5')->allowed);
        $this->assertTrue(Firewall::check('10.0.0.6')->isBlocked());
    }

    public function test_inactive_group_disables_all_its_rules(): void
    {
        $group = FirewallGroup::query()->create([
            'name' => 'Scrapers',
            'type' => 'blacklist',
            'is_active' => true,
        ]);

        Firewall::block('203.0.113.50', 'Scraper 1', $group->id);

        $this->assertTrue(Firewall::check('203.0.113.50')->isBlocked());

        $group->update(['is_active' => false]);

        $this->assertTrue(Firewall::check('203.0.113.50')->allowed);
    }

    public function test_expired_rules_are_ignored(): void
    {
        Firewall::block('203.0.113.77', 'Temp ban', expiresAt: now()->subMinute());

        $this->assertTrue(Firewall::check('203.0.113.77')->allowed);

        Firewall::block('203.0.113.78', 'Active temp ban', expiresAt: now()->addHour());

        $this->assertTrue(Firewall::check('203.0.113.78')->isBlocked());
    }

    public function test_unblock_deactivates_matching_blacklist_rules(): void
    {
        Firewall::block('203.0.113.99');
        $this->assertTrue(Firewall::check('203.0.113.99')->isBlocked());

        $deactivated = Firewall::unblock('203.0.113.99');

        $this->assertSame(1, $deactivated);
        $this->assertTrue(Firewall::check('203.0.113.99')->allowed);
        $this->assertFalse(FirewallRule::query()->where('value', '203.0.113.99')->firstOrFail()->is_active);
    }

    public function test_bypass_ips_always_pass(): void
    {
        config()->set('firewall-gui.bypass_ips', ['127.0.0.1']);
        Firewall::block('127.0.0.1');

        $this->assertTrue(Firewall::check('127.0.0.1')->allowed);
    }

    public function test_higher_priority_group_wins_over_lower_priority_group(): void
    {
        $highBlacklist = FirewallGroup::query()->create([
            'name' => 'High Priority Block',
            'type' => 'blacklist',
            'priority' => 100,
            'is_active' => true,
        ]);

        $lowWhitelist = FirewallGroup::query()->create([
            'name' => 'Low Priority Allow',
            'type' => 'whitelist',
            'priority' => 10,
            'is_active' => true,
        ]);

        Firewall::block('198.51.100.50', 'Blocked IP', $highBlacklist->id);
        Firewall::allow('198.51.100.0/24', 'Subnet', $lowWhitelist->id);

        $this->assertTrue(Firewall::check('198.51.100.50')->isBlocked());
        $this->assertTrue(Firewall::check('198.51.100.51')->allowed);
    }
}
