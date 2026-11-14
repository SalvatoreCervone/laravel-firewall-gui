<?php

namespace FirewallGui\LaravelFirewallGui\Tests\Feature;

use FirewallGui\LaravelFirewallGui\Models\FirewallGroup;
use FirewallGui\LaravelFirewallGui\Tests\TestCase;

class ArtisanCommandsTest extends TestCase
{
    public function test_allow_block_check_unblock_and_clear_cache_commands(): void
    {
        $group = FirewallGroup::query()->create([
            'name' => 'Office',
            'type' => 'whitelist',
            'is_active' => true,
        ]);

        $this->artisan('firewall:allow', [
            'value' => '10.0.0.1',
            '--label' => 'HQ',
            '--group' => 'office',
        ])->assertSuccessful();

        $this->assertDatabaseHas('firewall_rules', [
            'value' => '10.0.0.1',
            'type' => 'whitelist',
            'group_id' => $group->id,
        ]);

        $this->artisan('firewall:block', [
            'value' => '203.0.113.5',
            '--label' => 'Bot',
        ])->assertSuccessful();

        $this->artisan('firewall:check', ['ip' => '203.0.113.5'])
            ->expectsOutputToContain('blocked')
            ->assertSuccessful();

        $this->artisan('firewall:unblock', ['value' => '203.0.113.5'])
            ->assertSuccessful();

        $this->artisan('firewall:check', ['ip' => '203.0.113.5'])
            ->expectsOutputToContain('allowed')
            ->assertSuccessful();

        $this->artisan('firewall:clear-cache')
            ->assertSuccessful();

        $this->artisan('firewall:notify', [
            'value' => '198.51.100.10',
            '--label' => 'Consultant',
        ])->assertSuccessful();

        $this->assertDatabaseHas('firewall_rules', [
            'value' => '198.51.100.10',
            'type' => 'notify',
        ]);
    }
}
