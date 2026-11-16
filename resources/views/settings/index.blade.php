@extends('firewall-gui::layout')

@section('title', 'Settings')

@section('content')
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Email Notification Settings</h2>
                <p class="card-subtitle">
                    Configure queued email alerts for rules and groups with notifications enabled (configurable cooldown per IP; all accesses are still logged).
                </p>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('firewall-gui.settings.update') }}" class="form-grid">
                @csrf
                @method('PUT')

                @php
                    $recipientEmails = old('notifications_emails');
                    if (! is_array($recipientEmails)) {
                        $recipientEmails = array_values(array_filter(
                            array_map('trim', explode(',', (string) ($settings['to'] ?? ''))),
                            fn (string $email): bool => $email !== ''
                        ));
                    }
                @endphp

                <div class="form-group form-group-full">
                    <label>Recipient Email(s)</label>
                    <input type="hidden" name="notifications_emails_present" value="1">

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 3rem;">#</th>
                                    <th>Email Address</th>
                                    <th style="width: 7rem; text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="recipient-emails-tbody">
                                @forelse ($recipientEmails as $index => $email)
                                    <tr data-email-row>
                                        <td data-row-index class="text-muted">{{ $index + 1 }}</td>
                                        <td>
                                            <input type="email"
                                                   name="notifications_emails[]"
                                                   value="{{ $email }}"
                                                   placeholder="security@example.com"
                                                   required>
                                        </td>
                                        <td style="text-align: right;">
                                            <button type="button" class="btn btn-outline-danger btn-sm" data-remove-email>Remove</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr id="recipient-emails-empty">
                                        <td colspan="3" class="text-muted" style="text-align: center; padding: 1rem;">
                                            No recipient emails configured yet. Click "+ Add Email" to add one.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-top: 0.5rem; flex-wrap: wrap;">
                        <button type="button" class="btn btn-secondary btn-sm" id="add-recipient-email">+ Add Email</button>
                        <span class="text-muted" style="font-size: 0.78rem;">Add one email address per row.</span>
                    </div>
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
                    <label for="notifications-cooldown">Notification Cooldown (Hours)</label>
                    <input type="number"
                           id="notifications-cooldown"
                           name="notifications_cooldown_hours"
                           min="1"
                           max="8760"
                           value="{{ old('notifications_cooldown_hours', $settings['cooldown_hours'] ?? 24) }}"
                           required>
                    <span class="text-muted" style="font-size: 0.78rem;">Send at most 1 email per IP within this window.</span>
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
                <h2 class="card-title">Mail Server Settings</h2>
                <p class="card-subtitle">
                    Configure a dedicated SMTP or mail transport for firewall notifications, or use Laravel's default mailer.
                </p>
            </div>
        </div>
        <div class="card-body">
            @php
                $selectedMailer = old('mail_mailer', $mailSettings['mailer'] ?? 'default') ?: 'default';
                $selectedEncryption = old('mail_encryption', $mailSettings['encryption'] ?? 'none') ?: 'none';
            @endphp

            <form method="POST" action="{{ route('firewall-gui.settings.mail.update') }}" class="form-grid" id="mail-settings-form">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label for="mail-mailer">Mailer Transport</label>
                    <select id="mail-mailer" name="mail_mailer">
                        <option value="default" {{ $selectedMailer === 'default' ? 'selected' : '' }}>Use Laravel Default Mailer</option>
                        <option value="smtp" {{ $selectedMailer === 'smtp' ? 'selected' : '' }}>SMTP</option>
                        <option value="sendmail" {{ $selectedMailer === 'sendmail' ? 'selected' : '' }}>Sendmail</option>
                        <option value="log" {{ $selectedMailer === 'log' ? 'selected' : '' }}>Log (Debug)</option>
                    </select>
                </div>

                <div class="form-group" data-smtp-field>
                    <label for="mail-host">SMTP Host</label>
                    <input type="text"
                           id="mail-host"
                           name="mail_host"
                           value="{{ old('mail_host', $mailSettings['host']) }}"
                           placeholder="smtp.example.com">
                </div>

                <div class="form-group" data-smtp-field>
                    <label for="mail-port">SMTP Port</label>
                    <input type="number"
                           id="mail-port"
                           name="mail_port"
                           min="1"
                           max="65535"
                           value="{{ old('mail_port', $mailSettings['port'] ?? 587) }}"
                           placeholder="587">
                </div>

                <div class="form-group" data-smtp-field>
                    <label for="mail-encryption">Encryption</label>
                    <select id="mail-encryption" name="mail_encryption">
                        <option value="tls" {{ $selectedEncryption === 'tls' ? 'selected' : '' }}>TLS (STARTTLS)</option>
                        <option value="ssl" {{ $selectedEncryption === 'ssl' ? 'selected' : '' }}>SSL / SMTPS</option>
                        <option value="none" {{ $selectedEncryption === 'none' ? 'selected' : '' }}>None</option>
                    </select>
                </div>

                <div class="form-group" data-smtp-field>
                    <label for="mail-username">SMTP Username</label>
                    <input type="text"
                           id="mail-username"
                           name="mail_username"
                           value="{{ old('mail_username', $mailSettings['username']) }}"
                           autocomplete="off"
                           placeholder="noreply@example.com">
                </div>

                <div class="form-group" data-smtp-field>
                    <label for="mail-password">SMTP Password</label>
                    <input type="password"
                           id="mail-password"
                           name="mail_password"
                           value=""
                           autocomplete="new-password"
                           placeholder="{{ $mailSettings['has_password'] ? '•••••••• (leave blank to keep current)' : 'Enter password' }}">
                </div>

                @if ($mailSettings['has_password'])
                    <div class="form-group form-group-full" data-smtp-field>
                        <label class="checkbox-label">
                            <input type="checkbox"
                                   name="mail_clear_password"
                                   value="1"
                                   {{ old('mail_clear_password') ? 'checked' : '' }}>
                            <span>Clear saved SMTP password</span>
                        </label>
                    </div>
                @endif

                <div class="form-group">
                    <label for="mail-from-address">From Email Address (optional)</label>
                    <input type="email"
                           id="mail-from-address"
                           name="mail_from_address"
                           value="{{ old('mail_from_address', $mailSettings['from_address']) }}"
                           placeholder="firewall@example.com">
                </div>

                <div class="form-group">
                    <label for="mail-from-name">From Name (optional)</label>
                    <input type="text"
                           id="mail-from-name"
                           name="mail_from_name"
                           value="{{ old('mail_from_name', $mailSettings['from_name']) }}"
                           placeholder="Laravel Firewall">
                </div>

                <div class="form-group form-group-full">
                    <div>
                        <button type="submit" class="btn btn-primary">Save Mail Server Settings</button>
                    </div>
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

    <script>
        (function () {
            const tbody = document.getElementById('recipient-emails-tbody');
            const addButton = document.getElementById('add-recipient-email');

            if (tbody && addButton) {
                function syncRowNumbers() {
                    const rows = tbody.querySelectorAll('tr[data-email-row]');
                    const emptyRow = document.getElementById('recipient-emails-empty');

                    rows.forEach(function (row, idx) {
                        const indexCell = row.querySelector('[data-row-index]');
                        if (indexCell) indexCell.textContent = String(idx + 1);
                    });

                    if (rows.length === 0 && !emptyRow) {
                        const tr = document.createElement('tr');
                        tr.id = 'recipient-emails-empty';
                        tr.innerHTML = '<td colspan="3" class="text-muted" style="text-align: center; padding: 1rem;">No recipient emails configured yet. Click "+ Add Email" to add one.</td>';
                        tbody.appendChild(tr);
                    } else if (rows.length > 0 && emptyRow) {
                        emptyRow.remove();
                    }
                }

                addButton.addEventListener('click', function () {
                    const emptyRow = document.getElementById('recipient-emails-empty');
                    if (emptyRow) emptyRow.remove();

                    const tr = document.createElement('tr');
                    tr.setAttribute('data-email-row', '');
                    tr.innerHTML =
                        '<td data-row-index class="text-muted"></td>' +
                        '<td><input type="email" name="notifications_emails[]" value="" placeholder="security@example.com" required></td>' +
                        '<td style="text-align: right;"><button type="button" class="btn btn-outline-danger btn-sm" data-remove-email>Remove</button></td>';
                    tbody.appendChild(tr);
                    syncRowNumbers();

                    const input = tr.querySelector('input[type="email"]');
                    if (input) input.focus();
                });

                tbody.addEventListener('click', function (event) {
                    const removeBtn = event.target.closest('[data-remove-email]');
                    if (!removeBtn) return;

                    const row = removeBtn.closest('tr[data-email-row]');
                    if (row) {
                        row.remove();
                        syncRowNumbers();
                    }
                });
            }

            const mailerSelect = document.getElementById('mail-mailer');
            const smtpFields = document.querySelectorAll('[data-smtp-field]');
            if (!mailerSelect || !smtpFields.length) return;

            function toggleSmtpFields() {
                const isSmtp = mailerSelect.value === 'smtp';
                smtpFields.forEach(function (el) {
                    el.style.display = isSmtp ? '' : 'none';
                });
            }

            mailerSelect.addEventListener('change', toggleSmtpFields);
            toggleSmtpFields();
        })();
    </script>
@endsection
