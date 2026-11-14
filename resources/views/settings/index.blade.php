@extends('firewall-gui::layout')

@section('title', 'Settings')

@section('content')
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Email Notification Settings</h2>
                <p class="card-subtitle">
                    Configure queued email alerts for rules and groups with notifications enabled (sent once per IP per day).
                </p>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('firewall-gui.settings.update') }}" class="form-grid">
                @csrf
                @method('PUT')

                <div class="form-group" style="grid-column: span 2;">
                    <label for="notifications-to">Recipient Email(s)</label>
                    <input type="text"
                           id="notifications-to"
                           name="notifications_to"
                           value="{{ old('notifications_to', $settings['to']) }}"
                           placeholder="security@example.com, admin@example.com">
                    <span class="text-muted" style="font-size: 0.78rem;">Separate multiple email addresses with commas.</span>
                </div>

                <div class="form-group">
                    <label for="notifications-queue">Queue Name (optional)</label>
                    <input type="text"
                           id="notifications-queue"
                           name="notifications_queue"
                           value="{{ old('notifications_queue', $settings['queue']) }}"
                           placeholder="default">
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox"
                               name="notifications_enabled"
                               value="1"
                               {{ old('notifications_enabled', $settings['enabled']) ? 'checked' : '' }}>
                        <span>Enable Email Notifications</span>
                    </label>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox"
                               name="notifications_log_access"
                               value="1"
                               {{ old('notifications_log_access', $settings['log_access']) ? 'checked' : '' }}>
                        <span>Log Allowed Notify Hits</span>
                    </label>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Send Test Email</h2>
                <p class="card-subtitle">Queue a test notification email using the current Laravel mailer and queue configuration.</p>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('firewall-gui.settings.test-email') }}" class="form-grid">
                @csrf

                <div class="form-group" style="grid-column: span 2;">
                    <label for="test-email">Test Recipient Email</label>
                    <input type="email"
                           id="test-email"
                           name="test_email"
                           value="{{ old('test_email') }}"
                           placeholder="you@example.com"
                           required>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-secondary">Queue Test Email</button>
                </div>
            </form>
        </div>
    </div>
@endsection
