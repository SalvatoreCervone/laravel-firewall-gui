<dialog id="edit-rule-dialog" class="fw-dialog">
    <div class="card-header">
        <div>
            <h2 class="card-title">{{ __('firewall-gui::ui.rules.edit_title') }}</h2>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" data-close-edit-rule>&times;</button>
    </div>
    <div class="card-body">
        <form method="POST" action="" id="edit-rule-form" class="settings-grid">
            @csrf
            @method('PUT')

            <div class="form-group form-group-full">
                <label for="edit-rule-value">{{ __('firewall-gui::ui.rules.ip_cidr_range') }}</label>
                <input type="text" id="edit-rule-value" name="value" required>
            </div>

            <div class="form-group">
                <label for="edit-rule-type">{{ __('firewall-gui::ui.rules.action_type') }}</label>
                <select id="edit-rule-type" name="type" required>
                    <option value="blacklist">{{ __('firewall-gui::ui.rules.blacklist_block') }}</option>
                    <option value="whitelist">{{ __('firewall-gui::ui.rules.whitelist_allow') }}</option>
                    <option value="notify">{{ __('firewall-gui::ui.common.notify_only') }}</option>
                </select>
            </div>

            <div class="form-group">
                <label for="edit-rule-priority">{{ __('firewall-gui::ui.groups.priority') }}</label>
                <input type="number" id="edit-rule-priority" name="priority" value="0" step="1">
            </div>

            @if (isset($groups))
                <div class="form-group">
                    <label for="edit-rule-group">{{ __('firewall-gui::ui.rules.group_optional') }}</label>
                    <select id="edit-rule-group" name="group_id">
                        <option value="">{{ __('firewall-gui::ui.common.none') }}</option>
                        @foreach ($groups as $g)
                            <option value="{{ $g->id }}">{{ $g->name }}</option>
                        @endforeach
                    </select>
                </div>
            @elseif (isset($group))
                <input type="hidden" id="edit-rule-group" name="group_id" value="{{ $group->id }}">
            @endif

            <div class="form-group">
                <label for="edit-rule-label">{{ __('firewall-gui::ui.rules.label_optional') }}</label>
                <input type="text" id="edit-rule-label" name="label">
            </div>

            <div class="form-group">
                <label for="edit-rule-expires">{{ __('firewall-gui::ui.rules.expires_optional') }}</label>
                <input type="datetime-local" id="edit-rule-expires" name="expires_at">
            </div>

            <div class="settings-toggles">
                <input type="hidden" name="is_active" value="0">
                <label class="checkbox-label">
                    <input type="checkbox" id="edit-rule-active" name="is_active" value="1">
                    <span>{{ __('firewall-gui::ui.common.active') }}</span>
                </label>

                <input type="hidden" name="notify_on_hit" value="0">
                <label class="checkbox-label">
                    <input type="checkbox" id="edit-rule-notify" name="notify_on_hit" value="1">
                    <span>{{ __('firewall-gui::ui.rules.email_alert_on_hit') }}</span>
                </label>
            </div>

            <div class="settings-actions">
                <button type="submit" class="btn btn-primary">{{ __('firewall-gui::ui.common.save') }}</button>
                <button type="button" class="btn btn-secondary" data-close-edit-rule>{{ __('firewall-gui::ui.common.cancel') }}</button>
            </div>
        </form>
    </div>
</dialog>

<script>
    (function () {
        const dialog = document.getElementById('edit-rule-dialog');
        const form = document.getElementById('edit-rule-form');
        if (!dialog || !form) return;

        document.querySelectorAll('[data-edit-rule]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                form.action = btn.getAttribute('data-action') || '';
                document.getElementById('edit-rule-value').value = btn.getAttribute('data-value') || '';
                document.getElementById('edit-rule-type').value = btn.getAttribute('data-type') || 'blacklist';
                document.getElementById('edit-rule-priority').value = btn.getAttribute('data-priority') || '0';
                document.getElementById('edit-rule-label').value = btn.getAttribute('data-label') || '';
                document.getElementById('edit-rule-expires').value = btn.getAttribute('data-expires') || '';
                document.getElementById('edit-rule-active').checked = btn.getAttribute('data-active') === '1';
                document.getElementById('edit-rule-notify').checked = btn.getAttribute('data-notify') === '1';

                const groupEl = document.getElementById('edit-rule-group');
                if (groupEl && groupEl.tagName === 'SELECT') {
                    groupEl.value = btn.getAttribute('data-group-id') || '';
                }

                if (typeof dialog.showModal === 'function') {
                    dialog.showModal();
                } else {
                    dialog.setAttribute('open', 'open');
                }
            });
        });

        dialog.querySelectorAll('[data-close-edit-rule]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (typeof dialog.close === 'function') {
                    dialog.close();
                } else {
                    dialog.removeAttribute('open');
                }
            });
        });
    })();
</script>
