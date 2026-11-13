<?php

namespace FirewallGui\LaravelFirewallGui\Tests\Feature;

use FirewallGui\LaravelFirewallGui\Facades\Firewall;
use FirewallGui\LaravelFirewallGui\Models\FirewallLog;
use FirewallGui\LaravelFirewallGui\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class FirewallMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('firewall')->get('/protected-endpoint', fn () => response('OK', 200));
    }

    public function test_allows_request_when_not_blocked(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get('/protected-endpoint')
            ->assertOk()
            ->assertSee('OK');
    }

    public function test_blocks_blacklisted_ip_and_logs_request(): void
    {
        Firewall::block('203.0.113.10', 'Blocked test');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->getJson('/protected-endpoint')
            ->assertStatus(403)
            ->assertJson([
                'message' => 'Access denied by firewall.',
                'ip' => '203.0.113.10',
            ]);

        $this->assertDatabaseHas('firewall_logs', [
            'ip_address' => '203.0.113.10',
            'path' => '/protected-endpoint',
            'action' => 'blocked',
        ]);
    }

    public function test_blocks_blacklisted_ip_with_html_view_for_web_requests(): void
    {
        Firewall::block('203.0.113.15', 'Web block test');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.15'])
            ->get('/protected-endpoint')
            ->assertStatus(403)
            ->assertSee('Access denied by firewall.')
            ->assertSee('203.0.113.15');
    }

    public function test_dry_run_mode_logs_but_allows_request(): void
    {
        config()->set('firewall-gui.dry_run', true);
        Firewall::block('203.0.113.20');

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
            ->get('/protected-endpoint')
            ->assertOk()
            ->assertSee('OK');

        $log = FirewallLog::query()->first();
        $this->assertNotNull($log);
        $this->assertSame('dry_run_blocked', $log->action);
        $this->assertSame('203.0.113.20', $log->ip_address);
    }
}
