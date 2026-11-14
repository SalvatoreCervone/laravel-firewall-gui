<?php

namespace FirewallGui\LaravelFirewallGui\Http\Controllers;

use FirewallGui\LaravelFirewallGui\Mail\FirewallAccessNotification;
use FirewallGui\LaravelFirewallGui\Models\FirewallSetting;
use FirewallGui\LaravelFirewallGui\Services\FirewallManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
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
            'currentIp' => (string) $request->ip(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notifications_enabled' => ['nullable', 'boolean'],
            'notifications_to' => ['nullable', 'string'],
            'notifications_queue' => ['nullable', 'string', 'max:100'],
            'notifications_log_access' => ['nullable', 'boolean'],
        ]);

        $rawEmails = (string) ($validated['notifications_to'] ?? '');
        $emails = array_values(array_filter(array_map('trim', explode(',', $rawEmails)), fn (string $e): bool => $e !== ''));

        foreach ($emails as $email) {
            if (Validator::make(['email' => $email], ['email' => 'email'])->fails()) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors(['notifications_to' => "Invalid email address: [{$email}]."]);
            }
        }

        $pairs = [
            'notifications.enabled' => $request->boolean('notifications_enabled') ? '1' : '0',
            'notifications.to' => implode(', ', $emails),
            'notifications.queue' => trim((string) ($validated['notifications_queue'] ?? '')) ?: null,
            'notifications.log_access' => $request->boolean('notifications_log_access') ? '1' : '0',
        ];

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

    public function sendTest(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'test_email' => ['required', 'email'],
        ]);

        $settings = $this->firewall->getNotificationSettings();

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

        if (! empty($settings['queue'])) {
            $mailable->onQueue((string) $settings['queue']);
        }

        Mail::to($validated['test_email'])->queue($mailable);

        return redirect()
            ->back()
            ->with('success', "Test notification email queued for [{$validated['test_email']}].");
    }
}
