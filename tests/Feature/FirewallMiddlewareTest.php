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

    public function test_group_notification_cooldown_hours_overrides_global_cooldown(): void
    {
        Mail::fake();

        config()->set('firewall-gui.mode', 'whitelist_only');
        config()->set('firewall-gui.notifications.to', 'security@example.com');
        config()->set('firewall-gui.notifications.cooldown_hours', 24);

        $group = \FirewallGui\LaravelFirewallGui\Models\FirewallGroup::query()->create([
            'name' => 'Consultants 3h',
            'type' => 'notify',
            'is_active' => true,
            'notify_on_hit' => true,
            'notification_cooldown_hours' => 3,
        ]);

        Firewall::notify('198.51.100.10', 'Consultant IP', $group->id);

        // t = 0: 1st email
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->get('/protected-endpoint')
            ->assertOk();

        // t + 2h: still within 3h group cooldown -> still 1 email
        $this->travel(2)->hours();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->get('/protected-endpoint')
            ->assertOk();

        Mail::assertQueued(FirewallAccessNotification::class, 1);

        // t + 4h: past 3h group cooldown (even though global is 24h) -> 2nd email
        $this->travel(2)->hours();
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->get('/protected-endpoint')
            ->assertOk();

        Mail::assertQueued(FirewallAccessNotification::class, 2);
    }

    public function test_recipient_group_filter_sends_to_matching_group_and_empty_group_recipients(): void
    {
        Mail::fake();

        config()->set('firewall-gui.mode', 'whitelist_only');

        $groupA = \FirewallGui\LaravelFirewallGui\Models\FirewallGroup::query()->create([
            'name' => 'Group A',
            'type' => 'notify',
            'is_active' => true,
            'notify_on_hit' => true,
        ]);

        $groupB = \FirewallGui\LaravelFirewallGui\Models\FirewallGroup::query()->create([
            'name' => 'Group B',
            'type' => 'notify',
            'is_active' => true,
            'notify_on_hit' => true,
        ]);

        \FirewallGui\LaravelFirewallGui\Models\FirewallSetting::query()->updateOrCreate(
            ['key' => 'notifications.recipients'],
            ['value' => json_encode([
                ['email' => 'all@example.com', 'group_ids' => []],
                ['email' => 'only-a@example.com', 'group_ids' => [$groupA->id]],
                ['email' => 'only-b@example.com', 'group_ids' => [$groupB->id]],
            ])]
        );

        Firewall::notify('198.51.100.20', 'IP in Group A', $groupA->id);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->get('/protected-endpoint')
            ->assertOk();

        Mail::assertQueued(FirewallAccessNotification::class, function (FirewallAccessNotification $mail): bool {
            return $mail->hasTo('all@example.com')
                && $mail->hasTo('only-a@example.com')
                && ! $mail->hasTo('only-b@example.com');
        });
    }

    public function test_whitelist_only_mode_notifies_on_unmatched_blocked_ip_when_enabled(): void
    {
        Mail::fake();

        config()->set('firewall-gui.mode', 'whitelist_only');
        config()->set('firewall-gui.notifications.to', 'security@example.com');
        config()->set('firewall-gui.notifications.on_unmatched_block', true);

        Firewall::allow('198.51.100.1', 'Allowed office IP');

        // Whitelisted IP -> allowed, no email
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])
            ->get('/protected-endpoint')
            ->assertOk();

        Mail::assertNothingQueued();

        // Unknown IP (not in whitelist) -> blocked twice, 2 logs, 1 email within cooldown
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->getJson('/protected-endpoint')
            ->assertStatus(403);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->getJson('/protected-endpoint')
            ->assertStatus(403);

        Mail::assertQueued(FirewallAccessNotification::class, 1);
        Mail::assertQueued(FirewallAccessNotification::class, function (FirewallAccessNotification $mail): bool {
            return $mail->decisionAction === 'blocked'
                && $mail->ipAddress === '203.0.113.99';
        });
        $this->assertSame(2, FirewallLog::query()->where('ip_address', '203.0.113.99')->where('action', 'blocked')->count());
    }

    public function test_whitelist_only_mode_does_not_notify_on_unmatched_block_when_disabled(): void
    {
        Mail::fake();

        config()->set('firewall-gui.mode', 'whitelist_only');
        config()->set('firewall-gui.notifications.to', 'security@example.com');
        config()->set('firewall-gui.notifications.on_unmatched_block', false);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.88'])
            ->getJson('/protected-endpoint')
            ->assertStatus(403);

        Mail::assertNothingQueued();
        $this->assertSame(1, FirewallLog::query()->where('ip_address', '203.0.113.88')->where('action', 'blocked')->count());
    }
}
