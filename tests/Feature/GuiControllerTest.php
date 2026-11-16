<?php

namespace FirewallGui\LaravelFirewallGui\Tests\Feature;

use FirewallGui\LaravelFirewallGui\Facades\Firewall;
use FirewallGui\LaravelFirewallGui\Mail\FirewallAccessNotification;
use FirewallGui\LaravelFirewallGui\Models\FirewallGroup;
use FirewallGui\LaravelFirewallGui\Models\FirewallLog;
use FirewallGui\LaravelFirewallGui\Models\FirewallRule;
use FirewallGui\LaravelFirewallGui\Tests\TestCase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

class GuiControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Firewall::auth(null);
        parent::tearDown();
    }

    public function test_dashboard_renders_successfully(): void
    {
        $this->get('/firewall')
            ->assertOk()
            ->assertSee('Firewall');
    }

    public function test_auth_callback_restricts_gui_access(): void
    {
        Firewall::auth(fn () => false);

        $this->get('/firewall')->assertStatus(403);

        Firewall::auth(fn () => true);

        $this->get('/firewall')->assertOk();
    }

    public function test_gate_config_restricts_gui_access(): void
    {
        config()->set('firewall-gui.gui.gate', 'manage-firewall');

        Gate::define('manage-firewall', fn ($user = null) => false);
        $this->get('/firewall')->assertStatus(403);

        Gate::define('manage-firewall', fn ($user = null) => true);
        $this->get('/firewall')->assertOk();
    }

    public function test_manage_firewall_gate_is_enforced_automatically_when_defined(): void
    {
        config()->set('firewall-gui.gui.gate', null);

        Gate::define('manage-firewall', fn ($user = null) => false);
        $this->get('/firewall')->assertStatus(403);

        Gate::define('manage-firewall', fn ($user = null) => true);
        $this->get('/firewall')->assertOk();
    }

    public function test_test_ip_simulator_returns_json_and_redirect(): void
    {
        Firewall::block('203.0.113.88', 'Simulator test');

        $this->postJson('/firewall/test-ip', ['ip' => '203.0.113.88'])
            ->assertOk()
            ->assertJson([
                'ip' => '203.0.113.88',
                'allowed' => false,
                'action' => 'blocked',
            ]);

        $this->post('/firewall/test-ip', ['ip' => '203.0.113.88'])
            ->assertRedirect()
            ->assertSessionHas('test_ip', '203.0.113.88');
    }

    public function test_rules_crud_and_bulk_import_and_self_lockout_protection(): void
    {
        $this->get('/firewall/rules')->assertOk();

        // Create CIDR whitelist rule
        $this->post('/firewall/rules', [
            'value' => '10.10.0.0/16',
            'type' => 'whitelist',
            'label' => 'Internal LAN',
            'is_active' => '1',
        ])->assertRedirect();

        $rule = FirewallRule::query()->where('value', '10.10.0.0/16')->firstOrFail();
        $this->assertSame('cidr', $rule->rule_type);

        // Self-lockout protection blocks blacklisting current IP (127.0.0.1)
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->post('/firewall/rules', [
                'value' => '127.0.0.1',
                'type' => 'blacklist',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('value');

        $this->assertDatabaseMissing('firewall_rules', [
            'value' => '127.0.0.1',
            'type' => 'blacklist',
        ]);

        // Toggle rule
        $this->patch("/firewall/rules/{$rule->id}/toggle")->assertRedirect();
        $this->assertFalse($rule->fresh()->is_active);

        // Bulk import
        $this->post('/firewall/rules/bulk-import', [
            'entries' => "198.51.100.1\n198.51.100.0/24\n# comment\ninvalid-entry\n192.0.2.1-192.0.2.10",
            'type' => 'blacklist',
            'label' => 'Imported',
        ])->assertRedirect();

        $this->assertDatabaseHas('firewall_rules', ['value' => '198.51.100.1', 'rule_type' => 'ip']);
        $this->assertDatabaseHas('firewall_rules', ['value' => '198.51.100.0/24', 'rule_type' => 'cidr']);
        $this->assertDatabaseHas('firewall_rules', ['value' => '192.0.2.1-192.0.2.10', 'rule_type' => 'range']);

        // Delete rule
        $this->delete("/firewall/rules/{$rule->id}")->assertRedirect();
        $this->assertDatabaseMissing('firewall_rules', ['id' => $rule->id]);
    }

    public function test_groups_crud_and_cascade_type_update(): void
    {
        $this->get('/firewall/groups')->assertOk();

        $this->post('/firewall/groups', [
            'name' => 'Partner APIs',
            'type' => 'whitelist',
            'description' => 'Trusted partners',
            'is_active' => '1',
            'priority' => 10,
        ])->assertRedirect();

        $group = FirewallGroup::query()->where('name', 'Partner APIs')->firstOrFail();

        $this->post('/firewall/rules', [
            'value' => '203.0.113.100',
            'group_id' => $group->id,
            'is_active' => '1',
        ])->assertRedirect();

        $this->get("/firewall/groups/{$group->id}")->assertOk()->assertSee('Partner APIs');

        // Update group type to blacklist -> cascades to its rules
        $this->put("/firewall/groups/{$group->id}", [
            'name' => 'Partner APIs',
            'type' => 'blacklist',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertSame('blacklist', $group->rules()->firstOrFail()->type);

        // Toggle group
        $this->patch("/firewall/groups/{$group->id}/toggle")->assertRedirect();
        $this->assertFalse($group->fresh()->is_active);

        // Delete group
        $this->delete("/firewall/groups/{$group->id}")->assertRedirect('/firewall/groups');
        $this->assertDatabaseMissing('firewall_groups', ['id' => $group->id]);
    }

    public function test_logs_index_allow_ip_and_clear(): void
    {
        $log = FirewallLog::query()->create([
            'ip_address' => '203.0.113.200',
            'method' => 'GET',
            'path' => '/admin',
            'action' => 'blocked',
            'reason' => 'Test block',
            'created_at' => now(),
        ]);

        $this->get('/firewall/logs')->assertOk()->assertSee('203.0.113.200');

        $this->post("/firewall/logs/{$log->id}/allow")->assertRedirect();

        $this->assertDatabaseHas('firewall_rules', [
            'value' => '203.0.113.200',
            'type' => 'whitelist',
            'is_active' => true,
        ]);

        $this->delete('/firewall/logs')->assertRedirect();
        $this->assertDatabaseCount('firewall_logs', 0);
    }

    public function test_can_create_notify_rule_and_group_from_gui(): void
    {
        $this->post('/firewall/groups', [
            'name' => 'External Consultants',
            'type' => 'notify',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('firewall_groups', [
            'name' => 'External Consultants',
            'type' => 'notify',
        ]);

        $this->post('/firewall/rules', [
            'value' => '198.51.100.0/24',
            'type' => 'notify',
            'label' => 'Watch subnet',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('firewall_rules', [
            'value' => '198.51.100.0/24',
            'type' => 'notify',
        ]);

        // Blacklist + Notify from GUI
        $this->post('/firewall/rules', [
            'value' => '198.51.100.99',
            'type' => 'blacklist',
            'notify_on_hit' => '1',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('firewall_rules', [
            'value' => '198.51.100.99',
            'type' => 'blacklist',
            'notify_on_hit' => true,
        ]);
    }

    public function test_settings_page_saves_email_config_and_queues_test_email(): void
    {
        Mail::fake();

        $this->get('/firewall/settings')->assertOk()->assertSee('Email Notification Settings');

        $this->put('/firewall/settings', [
            'notifications_enabled' => '1',
            'notifications_to' => 'soc@example.com, ops@example.com',
            'notifications_queue' => 'alerts',
            'notifications_log_access' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('firewall_settings', [
            'key' => 'notifications.to',
            'value' => 'soc@example.com, ops@example.com',
        ]);

        $this->post('/firewall/settings/test-email', [
            'test_email' => 'tester@example.com',
        ])->assertRedirect();

        Mail::assertQueued(FirewallAccessNotification::class, function (FirewallAccessNotification $mail): bool {
            return $mail->hasTo('tester@example.com') && $mail->queue === 'alerts';
        });
    }

    public function test_settings_page_saves_mail_server_config_and_uses_custom_from_and_mailer(): void
    {
        Mail::fake();

        $this->get('/firewall/settings')
            ->assertOk()
            ->assertSee('Mail Server Settings');

        // Validation: smtp requires host and port
        $this->put('/firewall/settings/mail', [
            'mail_mailer' => 'smtp',
            'mail_host' => '',
            'mail_port' => '',
        ])->assertSessionHasErrors(['mail_host', 'mail_port']);

        // Save valid SMTP config with password
        $this->put('/firewall/settings/mail', [
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.example.org',
            'mail_port' => '465',
            'mail_encryption' => 'ssl',
            'mail_username' => 'firewall@example.org',
            'mail_password' => 'super-secret-pass',
            'mail_from_address' => 'alerts@example.org',
            'mail_from_name' => 'Firewall Bot',
        ])->assertRedirect();

        $this->assertDatabaseHas('firewall_settings', [
            'key' => 'mail.mailer',
            'value' => 'smtp',
        ]);
        $this->assertDatabaseHas('firewall_settings', [
            'key' => 'mail.host',
            'value' => 'smtp.example.org',
        ]);

        // Password must be encrypted in DB, not stored as plaintext
        $storedPassword = \FirewallGui\LaravelFirewallGui\Models\FirewallSetting::query()
            ->where('key', 'mail.password')
            ->value('value');
        $this->assertNotNull($storedPassword);
        $this->assertNotSame('super-secret-pass', $storedPassword);

        // Updating without password keeps existing password
        $this->put('/firewall/settings/mail', [
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.example.org',
            'mail_port' => '587',
            'mail_encryption' => 'tls',
            'mail_username' => 'firewall@example.org',
            'mail_password' => '',
            'mail_from_address' => 'alerts@example.org',
            'mail_from_name' => 'Firewall Bot',
        ])->assertRedirect();

        $mailSettings = app(\FirewallGui\LaravelFirewallGui\Services\FirewallManager::class)->getMailSettings();
        $this->assertTrue($mailSettings['has_password']);
        $this->assertSame('super-secret-pass', $mailSettings['password']);
        $this->assertSame(587, $mailSettings['port']);

        // Send test email -> uses custom mailer config and custom From
        $this->post('/firewall/settings/test-email', [
            'test_email' => 'admin@example.org',
        ])->assertRedirect();

        $this->assertSame('smtp.example.org', config('mail.mailers.firewall_gui.host'));
        $this->assertSame(587, config('mail.mailers.firewall_gui.port'));
        $this->assertSame('tls', config('mail.mailers.firewall_gui.encryption'));
        $this->assertSame('super-secret-pass', config('mail.mailers.firewall_gui.password'));

        Mail::assertQueued(FirewallAccessNotification::class, function (FirewallAccessNotification $mail): bool {
            return $mail->hasTo('admin@example.org')
                && $mail->hasFrom('alerts@example.org', 'Firewall Bot');
        });

        // Clear password
        $this->put('/firewall/settings/mail', [
            'mail_mailer' => 'default',
            'mail_clear_password' => '1',
        ])->assertRedirect();

        $mailSettingsAfterClear = app(\FirewallGui\LaravelFirewallGui\Services\FirewallManager::class)->getMailSettings();
        $this->assertFalse($mailSettingsAfterClear['has_password']);
        $this->assertNull($mailSettingsAfterClear['mailer']);
    }

    public function test_settings_page_saves_recipient_emails_from_table_input(): void
    {
        $this->put('/firewall/settings', [
            'notifications_enabled' => '1',
            'notifications_emails_present' => '1',
            'notifications_emails' => ['sec@example.com', 'ops@example.com', 'sec@example.com'],
            'notifications_cooldown_hours' => '6',
            'notifications_log_access' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('firewall_settings', [
            'key' => 'notifications.to',
            'value' => 'sec@example.com, ops@example.com',
        ]);
        $this->assertDatabaseHas('firewall_settings', [
            'key' => 'notifications.cooldown_hours',
            'value' => '6',
        ]);

        // Invalid email inside table
        $this->put('/firewall/settings', [
            'notifications_enabled' => '1',
            'notifications_emails_present' => '1',
            'notifications_emails' => ['not-an-email'],
        ])->assertSessionHasErrors(['notifications_emails']);

        // Clearing all rows from table
        $this->put('/firewall/settings', [
            'notifications_enabled' => '1',
            'notifications_emails_present' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('firewall_settings', [
            'key' => 'notifications.to',
            'value' => '',
        ]);
    }
}
