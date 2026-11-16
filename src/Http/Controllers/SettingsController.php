<?php

namespace FirewallGui\LaravelFirewallGui\Http\Controllers;

use FirewallGui\LaravelFirewallGui\Mail\FirewallAccessNotification;
use FirewallGui\LaravelFirewallGui\Models\FirewallGroup;
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
            'guiLocale' => $this->firewall->getGuiLocale() ?? 'auto',
            'groups' => FirewallGroup::query()->orderBy('name')->get(['id', 'name', 'type']),
            'currentIp' => (string) $request->ip(),
        ]);
    }

    public function updateLocale(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'gui_locale' => ['required', 'in:auto,en,it'],
        ]);

        FirewallSetting::query()->updateOrCreate(
            ['key' => 'gui.locale'],
            ['value' => $validated['gui_locale']]
        );

        $this->firewall->clearCache();

        if (in_array($validated['gui_locale'], ['en', 'it'], true)) {
            app()->setLocale($validated['gui_locale']);
        }

        return redirect()
            ->back()
            ->with('success', __('firewall-gui::settings.language.saved_message'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notifications_enabled' => ['nullable', 'boolean'],
            'notifications_to' => ['nullable', 'string'],
            'notifications_emails' => ['nullable', 'array'],
            'notifications_emails.*' => ['nullable', 'string', 'max:255'],
            'notifications_recipients' => ['nullable', 'array'],
            'notifications_recipients.*.email' => ['nullable', 'string', 'max:255'],
            'notifications_recipients.*.group_ids' => ['nullable', 'array'],
            'notifications_recipients.*.group_ids.*' => ['integer'],
            'notifications_emails_present' => ['nullable', 'boolean'],
            'notifications_queue' => ['nullable', 'string', 'max:100'],
            'notifications_cooldown_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'notifications_on_unmatched_block' => ['nullable', 'boolean'],
            'notifications_log_access' => ['nullable', 'boolean'],
        ]);

        $usingTableInput = $request->boolean('notifications_emails_present')
            || $request->has('notifications_recipients')
            || $request->has('notifications_emails');

        $recipients = [];

        if ($request->has('notifications_recipients')) {
            $seen = [];
            foreach ((array) ($validated['notifications_recipients'] ?? []) as $row) {
                $email = trim((string) ($row['email'] ?? ''));
                if ($email === '' || isset($seen[strtolower($email)])) {
                    continue;
                }
                $seen[strtolower($email)] = true;
                $recipients[] = [
                    'email' => $email,
                    'group_ids' => array_values(array_unique(array_map('intval', (array) ($row['group_ids'] ?? [])))),
                ];
            }
        } elseif ($usingTableInput) {
            $rawList = $validated['notifications_emails'] ?? [];
            $emails = array_values(array_unique(array_filter(
                array_map(fn ($e): string => trim((string) $e), $rawList),
                fn (string $e): bool => $e !== ''
            )));
            $recipients = array_map(fn (string $e): array => ['email' => $e, 'group_ids' => []], $emails);
        } else {
            $rawEmails = (string) ($validated['notifications_to'] ?? '');
            $emails = array_values(array_unique(array_filter(
                array_map('trim', explode(',', $rawEmails)),
                fn (string $e): bool => $e !== ''
            )));
            $recipients = array_map(fn (string $e): array => ['email' => $e, 'group_ids' => []], $emails);
        }

        $errorKey = $usingTableInput ? 'notifications_emails' : 'notifications_to';

        foreach ($recipients as $entry) {
            if (Validator::make(['email' => $entry['email']], ['email' => 'email'])->fails()) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors([$errorKey => __('firewall-gui::settings.notifications.invalid_email', ['email' => $entry['email']])]);
            }
        }

        $emailsList = array_map(fn (array $entry): string => $entry['email'], $recipients);

        $pairs = [
            'notifications.enabled' => $request->boolean('notifications_enabled') ? '1' : '0',
            'notifications.to' => implode(', ', $emailsList),
            'notifications.recipients' => json_encode($recipients, JSON_THROW_ON_ERROR),
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
            ->with('success', __('firewall-gui::settings.notifications.saved_message'));
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
            ->with('success', __('firewall-gui::settings.mail.saved_message'));
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
            ->with('success', __('firewall-gui::settings.test.queued_message', ['email' => $validated['test_email']]));
    }
}
