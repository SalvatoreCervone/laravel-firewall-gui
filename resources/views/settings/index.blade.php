@extends('firewall-gui::layout')

@section('title', __('firewall-gui::settings.title'))

@section('content')
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">{{ __('firewall-gui::settings.notifications.title') }}</h2>
                <p class="card-subtitle">
                    {{ __('firewall-gui::settings.notifications.subtitle') }}
                </p>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('firewall-gui.settings.update') }}" class="settings-grid">
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
                    <label>{{ __('firewall-gui::settings.notifications.recipients_label') }}</label>
                    <input type="hidden" name="notifications_emails_present" value="1">

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 3rem;">{{ __('firewall-gui::settings.notifications.col_index') }}</th>
                                    <th>{{ __('firewall-gui::settings.notifications.col_email') }}</th>
                                    <th style="width: 7rem; text-align: right;">{{ __('firewall-gui::settings.notifications.col_action') }}</th>
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
                                                   placeholder="{{ __('firewall-gui::settings.notifications.email_placeholder') }}"
                                                   required>
                                        </td>
                                        <td style="text-align: right;">
                                            <button type="button" class="btn btn-outline-danger btn-sm" data-remove-email>
                                                {{ __('firewall-gui::settings.notifications.remove_button') }}
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr id="recipient-emails-empty">
                                        <td colspan="3" class="text-muted" style="text-align: center; padding: 1rem;">
                                            {{ __('firewall-gui::settings.notifications.empty_recipients') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-top: 0.5rem; flex-wrap: wrap;">
                        <button type="button" class="btn btn-secondary btn-sm" id="add-recipient-email">
                            {{ __('firewall-gui::settings.notifications.add_email_button') }}
                        </button>
                        <span class="form-hint">{{ __('firewall-gui::settings.notifications.recipients_help') }}</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notifications-queue">{{ __('firewall-gui::settings.notifications.queue_label') }}</label>
                    <input type="text"
                           id="notifications-queue"
                           name="notifications_queue"
                           value="{{ old('notifications_queue', $settings['queue']) }}"
                           placeholder="{{ __('firewall-gui::settings.notifications.queue_placeholder') }}">
                    <span class="form-hint">{{ __('firewall-gui::settings.notifications.queue_help') }}</span>
                </div>

                <div class="form-group">
                    <label for="notifications-cooldown">{{ __('firewall-gui::settings.notifications.cooldown_label') }}</label>
                    <input type="number"
                           id="notifications-cooldown"
                           name="notifications_cooldown_hours"
                           min="1"
                           max="8760"
                           value="{{ old('notifications_cooldown_hours', $settings['cooldown_hours'] ?? 24) }}"
                           required>
                    <span class="form-hint">{{ __('firewall-gui::settings.notifications.cooldown_help') }}</span>
                </div>

                <div class="settings-toggles">
                    <label class="checkbox-label">
                        <input type="checkbox"
                               name="notifications_enabled"
                               value="1"
                               {{ old('notifications_enabled', $settings['enabled']) ? 'checked' : '' }}>
                        <span>{{ __('firewall-gui::settings.notifications.enabled_label') }}</span>
                    </label>

                    <label class="checkbox-label">
                        <input type="checkbox"
                               name="notifications_log_access"
                               value="1"
                               {{ old('notifications_log_access', $settings['log_access']) ? 'checked' : '' }}>
                        <span>{{ __('firewall-gui::settings.notifications.log_access_label') }}</span>
                    </label>
                </div>

                <div class="settings-actions">
                    <button type="submit" class="btn btn-primary">{{ __('firewall-gui::settings.notifications.save_button') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">{{ __('firewall-gui::settings.mail.title') }}</h2>
                <p class="card-subtitle">
                    {{ __('firewall-gui::settings.mail.subtitle') }}
                </p>
            </div>
        </div>
        <div class="card-body">
            @php
                $selectedMailer = old('mail_mailer', $mailSettings['mailer'] ?? 'default') ?: 'default';
                $selectedEncryption = old('mail_encryption', $mailSettings['encryption'] ?? 'none') ?: 'none';
            @endphp

            <form method="POST" action="{{ route('firewall-gui.settings.mail.update') }}" class="settings-grid" id="mail-settings-form">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label for="mail-mailer">{{ __('firewall-gui::settings.mail.mailer_label') }}</label>
                    <select id="mail-mailer" name="mail_mailer">
                        <option value="default" {{ $selectedMailer === 'default' ? 'selected' : '' }}>{{ __('firewall-gui::settings.mail.mailer_default') }}</option>
                        <option value="smtp" {{ $selectedMailer === 'smtp' ? 'selected' : '' }}>{{ __('firewall-gui::settings.mail.mailer_smtp') }}</option>
                        <option value="sendmail" {{ $selectedMailer === 'sendmail' ? 'selected' : '' }}>{{ __('firewall-gui::settings.mail.mailer_sendmail') }}</option>
                        <option value="log" {{ $selectedMailer === 'log' ? 'selected' : '' }}>{{ __('firewall-gui::settings.mail.mailer_log') }}</option>
                    </select>
                </div>

                <div class="form-group" data-smtp-field>
                    <label for="mail-encryption">{{ __('firewall-gui::settings.mail.encryption_label') }}</label>
                    <select id="mail-encryption" name="mail_encryption">
                        <option value="tls" {{ $selectedEncryption === 'tls' ? 'selected' : '' }}>{{ __('firewall-gui::settings.mail.encryption_tls') }}</option>
                        <option value="ssl" {{ $selectedEncryption === 'ssl' ? 'selected' : '' }}>{{ __('firewall-gui::settings.mail.encryption_ssl') }}</option>
                        <option value="none" {{ $selectedEncryption === 'none' ? 'selected' : '' }}>{{ __('firewall-gui::settings.mail.encryption_none') }}</option>
                    </select>
                </div>

                <div class="form-group" data-smtp-field>
                    <label for="mail-host">{{ __('firewall-gui::settings.mail.host_label') }}</label>
                    <input type="text"
                           id="mail-host"
                           name="mail_host"
                           value="{{ old('mail_host', $mailSettings['host']) }}"
                           placeholder="{{ __('firewall-gui::settings.mail.host_placeholder') }}">
                </div>

                <div class="form-group" data-smtp-field>
                    <label for="mail-port">{{ __('firewall-gui::settings.mail.port_label') }}</label>
                    <input type="number"
                           id="mail-port"
                           name="mail_port"
                           min="1"
                           max="65535"
                           value="{{ old('mail_port', $mailSettings['port'] ?? 587) }}"
                           placeholder="{{ __('firewall-gui::settings.mail.port_placeholder') }}">
                </div>

                <div class="form-group" data-smtp-field>
                    <label for="mail-username">{{ __('firewall-gui::settings.mail.username_label') }}</label>
                    <input type="text"
                           id="mail-username"
                           name="mail_username"
                           value="{{ old('mail_username', $mailSettings['username']) }}"
                           autocomplete="off"
                           placeholder="{{ __('firewall-gui::settings.mail.username_placeholder') }}">
                </div>

                <div class="form-group" data-smtp-field>
                    <label for="mail-password">{{ __('firewall-gui::settings.mail.password_label') }}</label>
                    <input type="password"
                           id="mail-password"
                           name="mail_password"
                           value=""
                           autocomplete="new-password"
                           placeholder="{{ $mailSettings['has_password'] ? __('firewall-gui::settings.mail.password_placeholder_keep') : __('firewall-gui::settings.mail.password_placeholder_enter') }}">
                </div>

                @if ($mailSettings['has_password'])
                    <div class="settings-toggles" data-smtp-field>
                        <label class="checkbox-label">
                            <input type="checkbox"
                                   name="mail_clear_password"
                                   value="1"
                                   {{ old('mail_clear_password') ? 'checked' : '' }}>
                            <span>{{ __('firewall-gui::settings.mail.clear_password_label') }}</span>
                        </label>
                    </div>
                @endif

                <div class="form-group">
                    <label for="mail-from-address">{{ __('firewall-gui::settings.mail.from_address_label') }}</label>
                    <input type="email"
                           id="mail-from-address"
                           name="mail_from_address"
                           value="{{ old('mail_from_address', $mailSettings['from_address']) }}"
                           placeholder="{{ __('firewall-gui::settings.mail.from_address_placeholder') }}">
                </div>

                <div class="form-group">
                    <label for="mail-from-name">{{ __('firewall-gui::settings.mail.from_name_label') }}</label>
                    <input type="text"
                           id="mail-from-name"
                           name="mail_from_name"
                           value="{{ old('mail_from_name', $mailSettings['from_name']) }}"
                           placeholder="{{ __('firewall-gui::settings.mail.from_name_placeholder') }}">
                </div>

                <div class="settings-actions">
                    <button type="submit" class="btn btn-primary">{{ __('firewall-gui::settings.mail.save_button') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">{{ __('firewall-gui::settings.test.title') }}</h2>
                <p class="card-subtitle">{{ __('firewall-gui::settings.test.subtitle') }}</p>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('firewall-gui.settings.test-email') }}" class="settings-inline-row">
                @csrf

                <div class="form-group">
                    <label for="test-email">{{ __('firewall-gui::settings.test.email_label') }}</label>
                    <input type="email"
                           id="test-email"
                           name="test_email"
                           value="{{ old('test_email') }}"
                           placeholder="{{ __('firewall-gui::settings.test.email_placeholder') }}"
                           required>
                </div>

                <div>
                    <button type="submit" class="btn btn-secondary">{{ __('firewall-gui::settings.test.submit_button') }}</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const tbody = document.getElementById('recipient-emails-tbody');
            const addButton = document.getElementById('add-recipient-email');
            const emptyText = @json(__('firewall-gui::settings.notifications.empty_recipients'));
            const emailPlaceholder = @json(__('firewall-gui::settings.notifications.email_placeholder'));
            const removeText = @json(__('firewall-gui::settings.notifications.remove_button'));

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
                        const td = document.createElement('td');
                        td.colSpan = 3;
                        td.className = 'text-muted';
                        td.style.textAlign = 'center';
                        td.style.padding = '1rem';
                        td.textContent = emptyText;
                        tr.appendChild(td);
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

                    const tdIndex = document.createElement('td');
                    tdIndex.setAttribute('data-row-index', '');
                    tdIndex.className = 'text-muted';

                    const tdInput = document.createElement('td');
                    const input = document.createElement('input');
                    input.type = 'email';
                    input.name = 'notifications_emails[]';
                    input.value = '';
                    input.placeholder = emailPlaceholder;
                    input.required = true;
                    tdInput.appendChild(input);

                    const tdAction = document.createElement('td');
                    tdAction.style.textAlign = 'right';
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn btn-outline-danger btn-sm';
                    btn.setAttribute('data-remove-email', '');
                    btn.textContent = removeText;
                    tdAction.appendChild(btn);

                    tr.appendChild(tdIndex);
                    tr.appendChild(tdInput);
                    tr.appendChild(tdAction);
                    tbody.appendChild(tr);
                    syncRowNumbers();
                    input.focus();
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
