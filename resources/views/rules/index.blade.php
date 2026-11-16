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
                        <option value="notify" {{ old('type') === 'notify' ? 'selected' : '' }}>{{ __('firewall-gui::ui.rules.notify_only') }}</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="rule-group">{{ __('firewall-gui::ui.rules.group_optional') }}</label>
                    <select id="rule-group" name="group_id">
                        <option value="">{{ __('firewall-gui::ui.common.none') }}</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" {{ (string) old('group_id') === (string) $group->id ? 'selected' : '' }}>
                                {{ $group->name }} ({{ ucfirst($group->type) }})
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
                    <label for="bulk-type">Type</label>
                    <select id="bulk-type" name="type" required>
                        <option value="blacklist" {{ old('type', 'blacklist') === 'blacklist' ? 'selected' : '' }}>Blacklist</option>
                        <option value="whitelist" {{ old('type') === 'whitelist' ? 'selected' : '' }}>Whitelist</option>
                        <option value="notify" {{ old('type') === 'notify' ? 'selected' : '' }}>Notify (Alert Only)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="bulk-group">Group (optional)</label>
                    <select id="bulk-group" name="group_id">
                        <option value="">— None —</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" {{ (string) old('group_id') === (string) $group->id ? 'selected' : '' }}>
                                {{ $group->name }} ({{ ucfirst($group->type) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="bulk-label">Shared Label (optional)</label>
                    <input type="text"
                           id="bulk-label"
                           name="label"
                           value="{{ old('label') }}"
                           placeholder="e.g. Imported Blocklist">
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="notify_on_hit" value="1" {{ old('notify_on_hit', false) ? 'checked' : '' }}>
                        <span>Email alert (1st hit/day)</span>
                    </label>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Import Entries</button>
                </div>
            </form>
        </div>
    </details>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Firewall Rules</h2>
                <p class="card-subtitle">Filter and manage active or inactive IP rules.</p>
            </div>
        </div>

        <div class="card-body" style="border-bottom: 1px solid var(--border-color); background-color: var(--bg-subtle);">
            <form method="GET" action="{{ route('firewall-gui.rules.index') }}" class="form-grid">
                <div class="form-group">
                    <label for="filter-search">Search</label>
                    <input type="text"
                           id="filter-search"
                           name="search"
                           value="{{ $filters['search'] ?? '' }}"
                           placeholder="IP, CIDR, or label...">
                </div>

                <div class="form-group">
                    <label for="filter-type">Type</label>
                    <select id="filter-type" name="type">
                        <option value="">All Types</option>
                        <option value="whitelist" {{ ($filters['type'] ?? '') === 'whitelist' ? 'selected' : '' }}>Whitelist</option>
                        <option value="blacklist" {{ ($filters['type'] ?? '') === 'blacklist' ? 'selected' : '' }}>Blacklist</option>
                        <option value="notify" {{ ($filters['type'] ?? '') === 'notify' ? 'selected' : '' }}>Notify</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="filter-rule-type">Format</label>
                    <select id="filter-rule-type" name="rule_type">
                        <option value="">All Formats</option>
                        <option value="ip" {{ ($filters['rule_type'] ?? '') === 'ip' ? 'selected' : '' }}>IP</option>
                        <option value="cidr" {{ ($filters['rule_type'] ?? '') === 'cidr' ? 'selected' : '' }}>CIDR</option>
                        <option value="range" {{ ($filters['rule_type'] ?? '') === 'range' ? 'selected' : '' }}>Range</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="filter-group">Group</label>
                    <select id="filter-group" name="group_id">
                        <option value="">All Groups</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" {{ (string) ($filters['group_id'] ?? '') === (string) $group->id ? 'selected' : '' }}>
                                {{ $group->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group" style="flex-direction: row; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('firewall-gui.rules.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        @if ($rules->isEmpty())
            <div class="empty-state">
                No firewall rules match the current filters.
            </div>
        @else
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Value</th>
                            <th>Format</th>
                            <th>Type</th>
                            <th>Group</th>
                            <th>Label</th>
                            <th>Expiration</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rules as $rule)
                            <tr>
                                <td>
                                    <code class="mono">{{ $rule->value }}</code>
                                    @if ($rule->isExpired())
                                        <span class="badge badge-amber" style="margin-left: 0.35rem;">Expired</span>
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
                                        <span class="badge badge-green">Whitelist</span>
                                    @elseif ($rule->type === 'notify')
                                        <span class="badge badge-blue">Notify Only</span>
                                    @else
                                        <span class="badge badge-red">Blacklist</span>
                                    @endif
                                    @if ($rule->type !== 'notify' && ($rule->notify_on_hit || ($rule->group && $rule->group->notify_on_hit)))
                                        <span class="badge badge-blue" style="margin-left: 0.25rem;">+ Notify</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($rule->group)
                                        <a href="{{ route('firewall-gui.groups.show', $rule->group) }}">{{ $rule->group->name }}</a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $rule->label ?: '—' }}</td>
                                <td class="mono text-muted">
                                    {{ $rule->expires_at ? $rule->expires_at->format('Y-m-d H:i') : 'Never' }}
                                </td>
                                <td>
                                    @if ($rule->is_active)
                                        <span class="badge badge-green">Active</span>
                                    @else
                                        <span class="badge badge-slate">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="actions-cell">
                                        <form method="POST" action="{{ route('firewall-gui.rules.toggle', $rule) }}" class="inline-form">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-secondary btn-sm">
                                                {{ $rule->is_active ? 'Disable' : 'Enable' }}
                                            </button>
                                        </form>

                                        <form method="POST"
                                              action="{{ route('firewall-gui.rules.destroy', $rule) }}"
                                              class="inline-form"
                                              data-confirm="Delete rule {{ $rule->value }}?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
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
