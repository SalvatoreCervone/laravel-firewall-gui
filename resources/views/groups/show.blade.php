@extends('firewall-gui::layout')

@section('title', __('firewall-gui::ui.groups.show_title', ['name' => $group->name]))

@section('content')
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
            <a href="{{ route('firewall-gui.groups.index') }}" class="btn btn-secondary btn-sm">&larr; {{ __('firewall-gui::ui.groups.all_groups') }}</a>

            @if ($group->is_active)
                <span class="badge badge-green">{{ __('firewall-gui::ui.common.active') }}</span>
            @else
                <span class="badge badge-slate">{{ __('firewall-gui::ui.common.inactive') }}</span>
            @endif

            <span class="badge badge-slate">{{ __('firewall-gui::ui.groups.priority_badge', ['priority' => $group->priority]) }}</span>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">{{ __('firewall-gui::ui.groups.edit_title') }}</h2>
                @if ($group->description)
                    <p class="card-subtitle">{{ $group->description }}</p>
                @endif
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('firewall-gui.groups.update', $group) }}" class="form-grid">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label for="edit-group-name">{{ __('firewall-gui::ui.groups.name') }}</label>
                    <input type="text"
                           id="edit-group-name"
                           name="name"
                           value="{{ old('name', $group->name) }}"
                           required>
                </div>

                <div class="form-group">
                    <label for="edit-group-priority">{{ __('firewall-gui::ui.groups.priority') }}</label>
                    <input type="number"
                           id="edit-group-priority"
                           name="priority"
                           value="{{ old('priority', $group->priority) }}"
                           step="1">
                </div>

                <div class="form-group">
                    <label for="edit-group-cooldown">{{ __('firewall-gui::ui.groups.cooldown_hours') }}</label>
                    <input type="number"
                           id="edit-group-cooldown"
                           name="notification_cooldown_hours"
                           min="1"
                           max="8760"
                           value="{{ old('notification_cooldown_hours', $group->notification_cooldown_hours) }}"
                           placeholder="{{ __('firewall-gui::ui.groups.cooldown_placeholder', ['hours' => $globalCooldownHours ?? 24]) }}">
                </div>

                <div class="form-group" style="grid-column: span 2;">
                    <label for="edit-group-description">{{ __('firewall-gui::ui.groups.description') }}</label>
                    <input type="text"
                           id="edit-group-description"
                           name="description"
                           value="{{ old('description', $group->description) }}">
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $group->is_active) ? 'checked' : '' }}>
                        <span>{{ __('firewall-gui::ui.common.active') }}</span>
                    </label>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">{{ __('firewall-gui::ui.groups.save_button') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">{{ __('firewall-gui::ui.groups.add_rule_title', ['name' => $group->name]) }}</h2>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('firewall-gui.rules.store') }}" class="form-grid">
                @csrf
                <input type="hidden" name="group_id" value="{{ $group->id }}">

                <div class="form-group" style="grid-column: span 2;">
                    <label for="group-rule-value">{{ __('firewall-gui::ui.rules.ip_cidr_range') }}</label>
                    <input type="text"
                           id="group-rule-value"
                           name="value"
                           value="{{ old('value') }}"
                           placeholder="192.168.1.10, 10.0.0.0/8, or 192.168.1.1-192.168.1.50"
                           data-ip-preview="group-rule-value-preview"
                           required>
                    <div id="group-rule-value-preview" class="text-muted mono" style="font-size: 0.78rem; margin-top: 0.35rem; display: none;"></div>
                </div>

                <div class="form-group">
                    <label for="group-rule-type">{{ __('firewall-gui::ui.rules.action_type') }}</label>
                    <select id="group-rule-type" name="type" required>
                        <option value="blacklist" {{ old('type', 'blacklist') === 'blacklist' ? 'selected' : '' }}>{{ __('firewall-gui::ui.rules.blacklist_block') }}</option>
                        <option value="whitelist" {{ old('type') === 'whitelist' ? 'selected' : '' }}>{{ __('firewall-gui::ui.rules.whitelist_allow') }}</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="group-rule-priority">{{ __('firewall-gui::ui.groups.priority') }}</label>
                    <input type="number"
                           id="group-rule-priority"
                           name="priority"
                           value="{{ old('priority', 0) }}"
                           step="1">
                </div>

                <div class="form-group">
                    <label for="group-rule-label">{{ __('firewall-gui::ui.rules.label_optional') }}</label>
                    <input type="text"
                           id="group-rule-label"
                           name="label"
                           value="{{ old('label') }}"
                           placeholder="{{ __('firewall-gui::ui.rules.label_placeholder') }}">
                </div>

                <div class="form-group">
                    <label for="group-rule-expires">{{ __('firewall-gui::ui.rules.expires_optional') }}</label>
                    <input type="datetime-local"
                           id="group-rule-expires"
                           name="expires_at"
                           value="{{ old('expires_at') }}">
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                        <span>{{ __('firewall-gui::ui.rules.active_immediately') }}</span>
                    </label>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="notify_on_hit" value="1" {{ old('notify_on_hit', false) ? 'checked' : '' }}>
                        <span>{{ __('firewall-gui::ui.rules.email_alert_on_hit') }}</span>
                    </label>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">{{ __('firewall-gui::ui.rules.add_button') }}</button>
                </div>
            </form>
        </div>
    </div>

    <details class="collapsible">
        <summary>{{ __('firewall-gui::ui.groups.bulk_import_title', ['name' => $group->name]) }}</summary>
        <div class="collapsible-body">
            <form method="POST" action="{{ route('firewall-gui.rules.bulk-import') }}" class="form-grid">
                @csrf
                <input type="hidden" name="group_id" value="{{ $group->id }}">

                <div class="form-group form-group-full">
                    <label for="group-bulk-entries">{{ __('firewall-gui::ui.rules.bulk_entries') }}</label>
                    <textarea id="group-bulk-entries"
                              name="entries"
                              rows="5"
                              placeholder="192.168.1.10&#10;10.0.0.0/8&#10;172.16.0.1-172.16.0.50"
                              required>{{ old('entries') }}</textarea>
                </div>

                <div class="form-group">
                    <label for="group-bulk-type">{{ __('firewall-gui::ui.groups.type') }}</label>
                    <select id="group-bulk-type" name="type" required>
                        <option value="blacklist" {{ old('type', 'blacklist') === 'blacklist' ? 'selected' : '' }}>{{ __('firewall-gui::ui.common.blacklist') }}</option>
                        <option value="whitelist" {{ old('type') === 'whitelist' ? 'selected' : '' }}>{{ __('firewall-gui::ui.common.whitelist') }}</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="group-bulk-label">{{ __('firewall-gui::ui.rules.bulk_shared_label') }}</label>
                    <input type="text"
                           id="group-bulk-label"
                           name="label"
                           value="{{ old('label') }}"
                           placeholder="{{ __('firewall-gui::ui.rules.bulk_shared_placeholder') }}">
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="notify_on_hit" value="1" {{ old('notify_on_hit', false) ? 'checked' : '' }}>
                        <span>{{ __('firewall-gui::ui.rules.email_alert_on_hit') }}</span>
                    </label>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">{{ __('firewall-gui::ui.groups.bulk_import_button') }}</button>
                </div>
            </form>
        </div>
    </details>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">{{ __('firewall-gui::ui.groups.rules_in_group_title') }}</h2>
                <p class="card-subtitle">{{ __('firewall-gui::ui.groups.rules_in_group_subtitle', ['name' => $group->name]) }}</p>
            </div>
        </div>

        @if ($rules->isEmpty())
            <div class="empty-state">
                {{ __('firewall-gui::ui.groups.rules_in_group_empty') }}
            </div>
        @else
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('firewall-gui::ui.rules.col_value') }}</th>
                            <th>{{ __('firewall-gui::ui.rules.col_format') }}</th>
                            <th>{{ __('firewall-gui::ui.rules.col_type') }}</th>
                            <th>{{ __('firewall-gui::ui.groups.col_priority') }}</th>
                            <th>{{ __('firewall-gui::ui.rules.col_label') }}</th>
                            <th>{{ __('firewall-gui::ui.rules.col_expiration') }}</th>
                            <th>{{ __('firewall-gui::ui.common.status') }}</th>
                            <th>{{ __('firewall-gui::ui.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rules as $rule)
                            <tr>
                                <td>
                                    <code class="mono">{{ $rule->value }}</code>
                                    @if ($rule->isExpired())
                                        <span class="badge badge-amber" style="margin-left: 0.35rem;">{{ __('firewall-gui::ui.common.expired') }}</span>
                                    @endif
                                    @if ($rule->rule_type !== 'ip' && ($span = $rule->describeSpan()))
                                        <div class="text-muted mono" style="font-size: 0.75rem; margin-top: 0.2rem;">
                                            {{ $span['summary'] }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-slate">{{ strtoupper($rule->rule_type) }}</span>
                                </td>
                                <td>
                                    @if ($rule->type === 'whitelist')
                                        <span class="badge badge-green">{{ __('firewall-gui::ui.common.whitelist') }}</span>
                                    @elseif ($rule->type === 'notify')
                                        <span class="badge badge-blue">{{ __('firewall-gui::ui.common.notify_only') }}</span>
                                    @else
                                        <span class="badge badge-red">{{ __('firewall-gui::ui.common.blacklist') }}</span>
                                    @endif
                                    @if ($rule->type !== 'notify' && ($rule->notify_on_hit || $group->notify_on_hit))
                                        <span class="badge badge-blue" style="margin-left: 0.25rem;">{{ __('firewall-gui::ui.common.plus_notify') }}</span>
                                    @endif
                                </td>
                                <td class="mono">{{ $rule->priority ?? 0 }}</td>
                                <td>{{ $rule->label ?: '—' }}</td>
                                <td class="mono text-muted">
                                    {{ $rule->expires_at ? $rule->expires_at->format('Y-m-d H:i') : __('firewall-gui::ui.common.never') }}
                                </td>
                                <td>
                                    @if ($rule->is_active)
                                        <span class="badge badge-green">{{ __('firewall-gui::ui.common.active') }}</span>
                                    @else
                                        <span class="badge badge-slate">{{ __('firewall-gui::ui.common.inactive') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="actions-cell">
                                        <form method="POST" action="{{ route('firewall-gui.rules.toggle', $rule) }}" class="inline-form">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-secondary btn-sm">
                                                {{ $rule->is_active ? __('firewall-gui::ui.common.disable') : __('firewall-gui::ui.common.enable') }}
                                            </button>
                                        </form>

                                        <form method="POST"
                                              action="{{ route('firewall-gui.rules.destroy', $rule) }}"
                                              class="inline-form"
                                              data-confirm="{{ __('firewall-gui::ui.rules.delete_confirm', ['value' => $rule->value]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('firewall-gui::ui.common.delete') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($rules->hasPages())
                <div class="pagination-wrap">
                    {{ $rules->withQueryString()->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
