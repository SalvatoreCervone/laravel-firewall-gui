@extends('firewall-gui::layout')

@section('title', __('firewall-gui::ui.rules.title'))

@section('content')
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">{{ __('firewall-gui::ui.rules.add_title') }}</h2>
                <p class="card-subtitle">{{ __('firewall-gui::ui.rules.add_subtitle') }}</p>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('firewall-gui.rules.store') }}" class="form-grid">
                @csrf

                <div class="form-group" style="grid-column: span 2;">
                    <label for="rule-value">{{ __('firewall-gui::ui.rules.ip_cidr_range') }}</label>
                    <input type="text"
                           id="rule-value"
                           name="value"
                           value="{{ old('value') }}"
                           placeholder="192.168.1.10, 10.0.0.0/8, or 192.168.1.1-192.168.1.50"
                           data-ip-preview="rule-value-preview"
                           required>
                    <div id="rule-value-preview" class="text-muted mono" style="font-size: 0.78rem; margin-top: 0.35rem; display: none;"></div>
                </div>

                <div class="form-group">
                    <label for="rule-type">{{ __('firewall-gui::ui.rules.action_type') }}</label>
                    <select id="rule-type" name="type" required>
                        <option value="blacklist" {{ old('type', 'blacklist') === 'blacklist' ? 'selected' : '' }}>{{ __('firewall-gui::ui.rules.blacklist_block') }}</option>
                        <option value="whitelist" {{ old('type') === 'whitelist' ? 'selected' : '' }}>{{ __('firewall-gui::ui.rules.whitelist_allow') }}</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="rule-group">{{ __('firewall-gui::ui.rules.group_optional') }}</label>
                    <select id="rule-group" name="group_id">
                        <option value="">{{ __('firewall-gui::ui.common.none') }}</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" {{ (string) old('group_id') === (string) $group->id ? 'selected' : '' }}>
                                {{ $group->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="rule-label">{{ __('firewall-gui::ui.rules.label_optional') }}</label>
                    <input type="text"
                           id="rule-label"
                           name="label"
                           value="{{ old('label') }}"
                           placeholder="{{ __('firewall-gui::ui.rules.label_placeholder') }}">
                </div>

                <div class="form-group">
                    <label for="rule-priority">{{ __('firewall-gui::ui.groups.priority') }}</label>
                    <input type="number"
                           id="rule-priority"
                           name="priority"
                           value="{{ old('priority', 0) }}"
                           step="1">
                </div>

                <div class="form-group">
                    <label for="rule-expires">{{ __('firewall-gui::ui.rules.expires_optional') }}</label>
                    <input type="datetime-local"
                           id="rule-expires"
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
        <summary>{{ __('firewall-gui::ui.rules.bulk_title') }}</summary>
        <div class="collapsible-body">
            <form method="POST" action="{{ route('firewall-gui.rules.bulk-import') }}" class="form-grid">
                @csrf

                <div class="form-group form-group-full">
                    <label for="bulk-entries">{{ __('firewall-gui::ui.rules.bulk_entries') }}</label>
                    <textarea id="bulk-entries"
                              name="entries"
                              rows="5"
                              placeholder="192.168.1.10&#10;10.0.0.0/8&#10;172.16.0.1-172.16.0.50"
                              required>{{ old('entries') }}</textarea>
                </div>

                <div class="form-group">
                    <label for="bulk-type">{{ __('firewall-gui::ui.groups.type') }}</label>
                    <select id="bulk-type" name="type" required>
                        <option value="blacklist" {{ old('type', 'blacklist') === 'blacklist' ? 'selected' : '' }}>{{ __('firewall-gui::ui.common.blacklist') }}</option>
                        <option value="whitelist" {{ old('type') === 'whitelist' ? 'selected' : '' }}>{{ __('firewall-gui::ui.common.whitelist') }}</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="bulk-group">{{ __('firewall-gui::ui.rules.group_optional') }}</label>
                    <select id="bulk-group" name="group_id">
                        <option value="">{{ __('firewall-gui::ui.common.none') }}</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" {{ (string) old('group_id') === (string) $group->id ? 'selected' : '' }}>
                                {{ $group->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="bulk-label">{{ __('firewall-gui::ui.rules.bulk_shared_label') }}</label>
                    <input type="text"
                           id="bulk-label"
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
                    <button type="submit" class="btn btn-primary">{{ __('firewall-gui::ui.rules.bulk_import_button') }}</button>
                </div>
            </form>
        </div>
    </details>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">{{ __('firewall-gui::ui.rules.list_title') }}</h2>
                <p class="card-subtitle">{{ __('firewall-gui::ui.rules.list_subtitle') }}</p>
            </div>
        </div>

        <div class="card-body" style="border-bottom: 1px solid var(--border-color); background-color: var(--bg-subtle);">
            <form method="GET" action="{{ route('firewall-gui.rules.index') }}" class="form-grid">
                <div class="form-group">
                    <label for="filter-search">{{ __('firewall-gui::ui.rules.filter_search') }}</label>
                    <input type="text"
                           id="filter-search"
                           name="search"
                           value="{{ $filters['search'] ?? '' }}"
                           placeholder="{{ __('firewall-gui::ui.rules.filter_search_placeholder') }}">
                </div>

                <div class="form-group">
                    <label for="filter-type">{{ __('firewall-gui::ui.rules.filter_type') }}</label>
                    <select id="filter-type" name="type">
                        <option value="">{{ __('firewall-gui::ui.rules.filter_all_types') }}</option>
                        <option value="whitelist" {{ ($filters['type'] ?? '') === 'whitelist' ? 'selected' : '' }}>{{ __('firewall-gui::ui.common.whitelist') }}</option>
                        <option value="blacklist" {{ ($filters['type'] ?? '') === 'blacklist' ? 'selected' : '' }}>{{ __('firewall-gui::ui.common.blacklist') }}</option>
                        <option value="notify" {{ ($filters['type'] ?? '') === 'notify' ? 'selected' : '' }}>{{ __('firewall-gui::ui.common.notify') }}</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="filter-rule-type">{{ __('firewall-gui::ui.rules.filter_format') }}</label>
                    <select id="filter-rule-type" name="rule_type">
                        <option value="">{{ __('firewall-gui::ui.rules.filter_all_formats') }}</option>
                        <option value="ip" {{ ($filters['rule_type'] ?? '') === 'ip' ? 'selected' : '' }}>IP</option>
                        <option value="cidr" {{ ($filters['rule_type'] ?? '') === 'cidr' ? 'selected' : '' }}>CIDR</option>
                        <option value="range" {{ ($filters['rule_type'] ?? '') === 'range' ? 'selected' : '' }}>Range</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="filter-group">{{ __('firewall-gui::ui.rules.filter_group') }}</label>
                    <select id="filter-group" name="group_id">
                        <option value="">{{ __('firewall-gui::ui.rules.filter_all_groups') }}</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" {{ (string) ($filters['group_id'] ?? '') === (string) $group->id ? 'selected' : '' }}>
                                {{ $group->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group" style="flex-direction: row; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary">{{ __('firewall-gui::ui.common.filter') }}</button>
                    <a href="{{ route('firewall-gui.rules.index') }}" class="btn btn-secondary">{{ __('firewall-gui::ui.common.reset') }}</a>
                </div>
            </form>
        </div>

        @if ($rules->isEmpty())
            <div class="empty-state">
                {{ __('firewall-gui::ui.rules.empty') }}
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
                            <th>{{ __('firewall-gui::ui.rules.col_group') }}</th>
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
                                    @if ($rule->type !== 'notify' && ($rule->notify_on_hit || ($rule->group && $rule->group->notify_on_hit)))
                                        <span class="badge badge-blue" style="margin-left: 0.25rem;">{{ __('firewall-gui::ui.common.plus_notify') }}</span>
                                    @endif
                                </td>
                                <td class="mono">{{ $rule->priority ?? 0 }}</td>
                                <td>
                                    @if ($rule->group)
                                        <a href="{{ route('firewall-gui.groups.show', $rule->group) }}">{{ $rule->group->name }}</a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
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
                                        <button type="button"
                                                class="btn btn-secondary btn-sm"
                                                data-edit-rule
                                                data-action="{{ route('firewall-gui.rules.update', $rule) }}"
                                                data-value="{{ $rule->value }}"
                                                data-type="{{ $rule->type }}"
                                                data-group-id="{{ $rule->group_id ?? '' }}"
                                                data-priority="{{ $rule->priority ?? 0 }}"
                                                data-label="{{ $rule->label ?? '' }}"
                                                data-expires="{{ $rule->expires_at ? $rule->expires_at->format('Y-m-d\TH:i') : '' }}"
                                                data-active="{{ $rule->is_active ? '1' : '0' }}"
                                                data-notify="{{ $rule->notify_on_hit ? '1' : '0' }}">
                                            {{ __('firewall-gui::ui.common.edit') }}
                                        </button>

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

    @include('firewall-gui::rules.partials.edit-dialog')
@endsection
