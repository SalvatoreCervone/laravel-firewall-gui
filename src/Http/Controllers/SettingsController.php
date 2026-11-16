<?php

namespace FirewallGui\LaravelFirewallGui\Http\Controllers;

use FirewallGui\LaravelFirewallGui\Mail\FirewallAccessNotification;
use FirewallGui\LaravelFirewallGui\Models\FirewallSetting;
use FirewallGui\LaravelFirewallGui\Services\FirewallManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        protected FirewallManager $firewall,
    ) {}

    public function index(Request $request): View
    {
        return view('firewall-gui::settings.index', [
            'settings' => $this->firewall->getNotificationSettings(),
            'mailSettings' => $this->firewall->getMailSettings(),
            'currentIp' => (string) $request->ip(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notifications_enabled' => ['nullable', 'boolean'],
            'notifications_to' => ['nullable', 'string'],
            'notifications_emails' => ['nullable', 'array'],
            'notifications_emails.*' => ['nullable', 'string', 'max:255'],
            'notifications_emails_present' => ['nullable', 'boolean'],
            'notifications_queue' => ['nullable', 'string', 'max:100'],
            'notifications_cooldown_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'notifications_log_access' => ['nullable', 'boolean'],
        ]);

        $usingTableInput = $request->boolean('notifications_emails_present') || $request->has('notifications_emails');

        if ($usingTableInput) {
            $rawList = $validated['notifications_emails'] ?? [];
            $emails = array_values(array_unique(array_filter(
                array_map(fn ($e): string => trim((string) $e), $rawList),
                fn (string $e): bool => $e !== ''
            )));
        } else {
            $rawEmails = (string) ($validated['notifications_to'] ?? '');
            $emails = array_values(array_unique(array_filter(
                array_map('trim', explode(',', $rawEmails)),
                fn (string $e): bool => $e !== ''
            )));
        }

        $errorKey = $usingTableInput ? 'notifications_emails' : 'notifications_to';

        foreach ($emails as $email) {
            if (Validator::make(['email' => $email], ['email' => 'email'])->fails()) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors([$errorKey => "Invalid email address: [{$email}]."]);
            }
        }

        $pairs = [
            'notifications.enabled' => $request->boolean('notifications_enabled') ? '1' : '0',
            'notifications.to' => implode(', ', $emails),
            'notifications.queue' => trim((string) ($validated['notifications_queue'] ?? '')) ?: null,
            'notifications.log_access' => $request->boolean('notifications_log_access') ? '1' : '0',
        ];

        if (isset($validated['notifications_cooldown_hours'])) {
            $pairs['notifications.cooldown_hours'] = (string) $validated['notifications_cooldown_hours'];
        }

        foreach ($pairs as $key => $value) {
            FirewallSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        $this->firewall->clearCache();

        return redirect()
            ->back()
            ->with('success', 'Notification settings saved.');
    }

    public function updateMail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mail_mailer' => ['nullable', 'in:default,smtp,sendmail,log'],
            'mail_host' => ['nullable', 'required_if:mail_mailer,smtp', 'string', 'max:255'],
            'mail_port' => ['nullable', 'required_if:mail_mailer,smtp', 'integer', 'min:1', 'max:65535'],
            'mail_encryption' => ['nullable', 'in:tls,ssl,none'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_clear_password' => ['nullable', 'boolean'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
        ]);

        $mailer = ($validated['mail_mailer'] ?? '') ?: 'default';
        $encryption = ($validated['mail_encryption'] ?? '') ?: 'none';

        $pairs = [
            'mail.mailer' => $mailer === 'default' ? null : $mailer,
            'mail.host' => trim((string) ($validated['mail_host'] ?? '')) ?: null,
            'mail.port' => isset($validated['mail_port']) && $validated['mail_port'] !== '' ? (string) $validated['mail_port'] : null,
            'mail.encryption' => $encryption === 'none' ? null : $encryption,
            'mail.username' => trim((string) ($validated['mail_username'] ?? '')) ?: null,
            'mail.from_address' => trim((string) ($validated['mail_from_address'] ?? '')) ?: null,
            'mail.from_name' => trim((string) ($validated['mail_from_name'] ?? '')) ?: null,
        ];

        if ($request->boolean('mail_clear_password')) {
            $pairs['mail.password'] = null;
        } elseif (isset($validated['mail_password']) && $validated['mail_password'] !== '') {
            $pairs['mail.password'] = Crypt::encryptString($validated['mail_password']);
        }

        foreach ($pairs as $key => $value) {
            FirewallSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        $this->firewall->clearCache();

        return redirect()
            ->back()
            ->with('success', 'Mail server settings saved.');
    }

    public function sendTest(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'test_email' => ['required', 'email'],
        ]);

        $mailable = new FirewallAccessNotification(
            ipAddress: (string) $request->ip(),
            method: $request->method(),
            path: '/'.ltrim($request->path(), '/'),
            userAgent: $request->userAgent(),
            accessedAt: now()->toDateTimeString(),
            matchedRule: [
                'id' => 0,
                'value' => (string) $request->ip(),
                'label' => 'Test Notification',
            ],
            matchedGroup: null,
            decisionAction: 'allowed',
        );

        $this->firewall->queueNotificationMailable($validated['test_email'], $mailable);

        return redirect()
            ->back()
            ->with('success', "Test notification email queued for [{$validated['test_email']}].");
    }
}
