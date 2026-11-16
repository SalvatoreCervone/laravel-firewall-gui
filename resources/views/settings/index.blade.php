@extends('firewall-gui::layout')

@section('title', __('firewall-gui::settings.title'))

@section('content')
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">{{ __('firewall-gui::settings.language.title') }}</h2>
                <p class="card-subtitle">{{ __('firewall-gui::settings.language.subtitle') }}</p>
            </div>
        </div>
        <div class="card-body">
            @php
                $selectedLocale = old('gui_locale', $guiLocale ?? 'auto');
            @endphp

            <form method="POST" action="{{ route('firewall-gui.settings.locale.update') }}" class="settings-inline-row">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label for="gui-locale">{{ __('firewall-gui::settings.language.label') }}</label>
                    <select id="gui-locale" name="gui_locale">
                        <option value="auto" {{ $selectedLocale === 'auto' ? 'selected' : '' }}>{{ __('firewall-gui::settings.language.auto') }}</option>
                        <option value="en" {{ $selectedLocale === 'en' ? 'selected' : '' }}>{{ __('firewall-gui::settings.language.en') }}</option>
                        <option value="it" {{ $selectedLocale === 'it' ? 'selected' : '' }}>{{ __('firewall-gui::settings.language.it') }}</option>
                    </select>
                </div>

                <div>
                    <button type="submit" class="btn btn-primary">{{ __('firewall-gui::settings.language.save_button') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">{{ __('firewall-gui::settings.engine.title') }}</h2>
                <p class="card-subtitle">{{ __('firewall-gui::settings.engine.subtitle') }}</p>
            </div>
        </div>
        <div class="card-body">
            @php
                $selectedMode = old('firewall_mode', $engineSettings['mode'] ?? 'blacklist_only');
                $blockEnabled = old('firewall_block_enabled', ! ($engineSettings['dry_run'] ?? false));
            @endphp

            <form method="POST" action="{{ route('firewall-gui.settings.engine.update') }}" class="settings-grid">
                @csrf
                @method('PUT')

                <div class="form-group form-group-full">
                    <label for="firewall-mode">{{ __('firewall-gui::settings.engine.mode_label') }}</label>
                    <select id="firewall-mode" name="firewall_mode">
                        <option value="blacklist_only" {{ $selectedMode === 'blacklist_only' ? 'selected' : '' }}>{{ __('firewall-gui::settings.engine.mode_blacklist') }}</option>
                        <option value="whitelist_only" {{ $selectedMode === 'whitelist_only' ? 'selected' : '' }}>{{ __('firewall-gui::settings.engine.mode_whitelist') }}</option>
                    </select>
                </div>

                <div class="settings-toggles">
                    <label class="checkbox-label">
                        <input type="checkbox"
                               name="firewall_block_enabled"
                               value="1"
                               {{ $blockEnabled ? 'checked' : '' }}>
                        <span>{{ __('firewall-gui::settings.engine.block_enabled_label') }}</span>
                    </label>
                </div>

                <div class="settings-actions">
                    <button type="submit" class="btn btn-primary">{{ __('firewall-gui::settings.engine.save_button') }}</button>
                </div>
            </form>
        </div>
    </div>

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
                    $recipientRows = old('notifications_recipients');
                    if (! is_array($recipientRows)) {
                        $recipientRows = $settings['recipients'] ?? [];
                        if ($recipientRows === [] && ! empty($settings['to'])) {
                            $recipientRows = array_map(
                                fn (string $email): array => ['email' => $email, 'group_ids' => []],
                                array_values(array_filter(array_map('trim', explode(',', (string) $settings['to']))))
                            );
                        }
                    }
                    $availableGroups = $groups ?? collect();
                @endphp

                <div class="form-group form-group-full">
                    <label>{{ __('firewall-gui::settings.notifications.recipients_label') }}</label>
                    <input type="hidden" name="notifications_emails_present" value="1">

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 3rem;">{{ __('firewall-gui::settings.notifications.col_index') }}</th>
                                    <th style="min-width: 220px;">{{ __('firewall-gui::settings.notifications.col_email') }}</th>
                                    <th>{{ __('firewall-gui::settings.notifications.col_groups') }}</th>
                                    <th style="width: 7rem; text-align: right;">{{ __('firewall-gui::settings.notifications.col_action') }}</th>
                                </tr>
                            </thead>
                            <tbody id="recipient-emails-tbody">
                                @forelse ($recipientRows as $index => $row)
                                    @php
                                        $rowEmail = is_array($row) ? (string) ($row['email'] ?? '') : (string) $row;
                                        $rowGroupIds = is_array($row) ? array_map('intval', (array) ($row['group_ids'] ?? [])) : [];
                                    @endphp
                                    <tr data-email-row>
                                        <td data-row-index class="text-muted">{{ $loop->iteration }}</td>
                                        <td>
                                            <input type="email"
                                                   name="notifications_recipients[{{ $index }}][email]"
                                                   value="{{ $rowEmail }}"
                                                   placeholder="{{ __('firewall-gui::settings.notifications.email_placeholder') }}"
                                                   required>
                                        </td>
                                        <td>
                                            @if ($availableGroups->isEmpty())
                                                <span class="form-hint">{{ __('firewall-gui::settings.notifications.all_groups_hint') }}</span>
                                            @else
                                                <div class="recipient-groups">
                                                    @foreach ($availableGroups as $group)
                                                        <label class="checkbox-label">
                                                            <input type="checkbox"
                                                                   name="notifications_recipients[{{ $index }}][group_ids][]"
                                                                   value="{{ $group->id }}"
                                                                   {{ in_array((int) $group->id, $rowGroupIds, true) ? 'checked' : '' }}>
                                                            <span>{{ $group->name }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                        <td style="text-align: right;">
                                            <button type="button" class="btn btn-outline-danger btn-sm" data-remove-email>
                                                {{ __('firewall-gui::settings.notifications.remove_button') }}
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr id="recipient-emails-empty">
                                        <td colspan="4" class="text-muted" style="text-align: center; padding: 1rem;">
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
                    <input type="hidden" name="notifications_on_unmatched_block_present" value="1">
                    <label class="checkbox-label">
                        <input type="checkbox"
                               name="notifications_enabled"
                               value="1"
                               {{ old('notifications_enabled', $settings['enabled']) ? 'checked' : '' }}>
                        <span>{{ __('firewall-gui::settings.notifications.enabled_label') }}</span>
                    </label>

                    <label class="checkbox-label">
                        <input type="checkbox"
                               name="notifications_on_unmatched_block"
                               value="1"
                               {{ old('notifications_on_unmatched_block', $settings['on_unmatched_block'] ?? true) ? 'checked' : '' }}>
                        <span>{{ __('firewall-gui::settings.notifications.on_unmatched_block_label') }}</span>
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
            const allGroupsHint = @json(__('firewall-gui::settings.notifications.all_groups_hint'));
            const availableGroups = @json($availableGroups->map(fn ($g) => ['id' => $g->id, 'name' => $g->name])->values());
            let nextRecipientIndex = {{ count($recipientRows) }};

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
                        td.colSpan = 4;
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

                    const rowIdx = nextRecipientIndex++;
                    const tr = document.createElement('tr');
                    tr.setAttribute('data-email-row', '');

                    const tdIndex = document.createElement('td');
                    tdIndex.setAttribute('data-row-index', '');
                    tdIndex.className = 'text-muted';

                    const tdInput = document.createElement('td');
                    const input = document.createElement('input');
                    input.type = 'email';
                    input.name = 'notifications_recipients[' + rowIdx + '][email]';
                    input.value = '';
                    input.placeholder = emailPlaceholder;
                    input.required = true;
                    tdInput.appendChild(input);

                    const tdGroups = document.createElement('td');
                    if (!availableGroups.length) {
                        const span = document.createElement('span');
                        span.className = 'form-hint';
                        span.textContent = allGroupsHint;
                        tdGroups.appendChild(span);
                    } else {
                        const wrap = document.createElement('div');
                        wrap.className = 'recipient-groups';
                        availableGroups.forEach(function (group) {
                            const label = document.createElement('label');
                            label.className = 'checkbox-label';
                            const cb = document.createElement('input');
                            cb.type = 'checkbox';
                            cb.name = 'notifications_recipients[' + rowIdx + '][group_ids][]';
                            cb.value = String(group.id);
                            const text = document.createElement('span');
                            text.textContent = group.name;
                            label.appendChild(cb);
                            label.appendChild(text);
                            wrap.appendChild(label);
                        });
                        tdGroups.appendChild(wrap);
                    }

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
                    tr.appendChild(tdGroups);
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
