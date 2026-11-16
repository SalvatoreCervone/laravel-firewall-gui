<?php

namespace FirewallGui\LaravelFirewallGui\Tests\Feature;

use FirewallGui\LaravelFirewallGui\Facades\Firewall;
use FirewallGui\LaravelFirewallGui\Mail\FirewallAccessNotification;
use FirewallGui\LaravelFirewallGui\Models\FirewallLog;
use FirewallGui\LaravelFirewallGui\Tests\TestCase;
use Illuminate\Support\Facades\Mail;
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

    public function test_notify_rule_allows_access_and_queues_email_once_per_day(): void
    {
        Mail::fake();

        config()->set('firewall-gui.mode', 'whitelist_only');
        config()->set('firewall-gui.notifications.to', 'security@example.com, admin@example.com');

        Firewall::notify('198.51.100.0/24', 'Partner subnet');

        // First access of the day -> allowed and queues email
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.42'])
            ->get('/protected-endpoint')
            ->assertOk()
            ->assertSee('OK');

        // Second access same day from same IP -> still allowed, no second email
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.42'])
            ->get('/protected-endpoint')
            ->assertOk();

        Mail::assertQueued(FirewallAccessNotification::class, 1);
        Mail::assertQueued(FirewallAccessNotification::class, function (FirewallAccessNotification $mail): bool {
            return $mail->hasTo('security@example.com')
                && $mail->hasTo('admin@example.com')
                && $mail->ipAddress === '198.51.100.42';
        });

        $this->assertDatabaseHas('firewall_logs', [
            'ip_address' => '198.51.100.42',
            'action' => 'notified',
        ]);
        $this->assertSame(2, FirewallLog::query()->where('action', 'notified')->count());
    }

    public function test_notify_cooldown_hours_logs_all_accesses_and_sends_second_email_after_cooldown(): void
    {
        Mail::fake();

        config()->set('firewall-gui.mode', 'whitelist_only');
        config()->set('firewall-gui.notifications.to', 'security@example.com');
        config()->set('firewall-gui.notifications.cooldown_hours', 6);

        Firewall::notify('198.51.100.0/24', 'Partner subnet');

        // t = 0: first access -> 1st email + 1st log
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.42'])
            ->get('/protected-endpoint')
            ->assertOk();

        // t + 5h: within 6h cooldown -> still 1 email, but 2nd log recorded
        $this->travel(5)->hours();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.42'])
            ->get('/protected-endpoint')
            ->assertOk();

        Mail::assertQueued(FirewallAccessNotification::class, 1);
        $this->assertSame(2, FirewallLog::query()->where('action', 'notified')->count());

        // t + 7h: past 6h cooldown -> 2nd email + 3rd log
        $this->travel(2)->hours();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.42'])
            ->get('/protected-endpoint')
            ->assertOk();

        Mail::assertQueued(FirewallAccessNotification::class, 2);
        $this->assertSame(3, FirewallLog::query()->where('action', 'notified')->count());
    }

    public function test_blacklist_with_notify_on_hit_blocks_and_queues_email_once_per_day(): void
    {
        Mail::fake();

        config()->set('firewall-gui.notifications.to', 'security@example.com');

        Firewall::block('203.0.113.55', 'Attacker IP', notifyOnHit: true);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.55'])
            ->getJson('/protected-endpoint')
            ->assertStatus(403);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.55'])
            ->getJson('/protected-endpoint')
            ->assertStatus(403);

        Mail::assertQueued(FirewallAccessNotification::class, 1);
        Mail::assertQueued(FirewallAccessNotification::class, function (FirewallAccessNotification $mail): bool {
            return $mail->decisionAction === 'blocked'
                && $mail->ipAddress === '203.0.113.55';
        });
    }
}
