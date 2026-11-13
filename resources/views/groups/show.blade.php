@extends('firewall-gui::layout')

@section('title', 'Group: ' . $group->name)

@section('content')
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
            <a href="{{ route('firewall-gui.groups.index') }}" class="btn btn-secondary btn-sm">&larr; All Groups</a>

            @if ($group->type === 'whitelist')
                <span class="badge badge-green">Whitelist</span>
            @else
                <span class="badge badge-red">Blacklist</span>
            @endif

            @if ($group->is_active)
                <span class="badge badge-green">Active</span>
            @else
                <span class="badge badge-slate">Inactive</span>
            @endif

            <span class="badge badge-slate">Priority: {{ $group->priority }}</span>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Edit Group Settings</h2>
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
                    <label for="edit-group-name">Group Name</label>
                    <input type="text"
                           id="edit-group-name"
                           name="name"
                           value="{{ old('name', $group->name) }}"
                           required>
                </div>

                <div class="form-group">
                    <label for="edit-group-type">Type</label>
                    <select id="edit-group-type" name="type" required>
                        <option value="blacklist" {{ old('type', $group->type) === 'blacklist' ? 'selected' : '' }}>Blacklist</option>
                        <option value="whitelist" {{ old('type', $group->type) === 'whitelist' ? 'selected' : '' }}>Whitelist</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="edit-group-priority">Priority</label>
                    <input type="number"
                           id="edit-group-priority"
                           name="priority"
                           value="{{ old('priority', $group->priority) }}"
                           step="1">
                </div>

                <div class="form-group" style="grid-column: span 2;">
                    <label for="edit-group-description">Description</label>
                    <input type="text"
                           id="edit-group-description"
                           name="description"
                           value="{{ old('description', $group->description) }}">
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $group->is_active) ? 'checked' : '' }}>
                        <span>Active</span>
                    </label>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Save Group</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Add Rule to {{ $group->name }}</h2>
                <p class="card-subtitle">New rules added here inherit this group's <strong>{{ $group->type }}</strong> type.</p>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('firewall-gui.rules.store') }}" class="form-grid">
                @csrf
                <input type="hidden" name="group_id" value="{{ $group->id }}">
                <input type="hidden" name="type" value="{{ $group->type }}">

                <div class="form-group" style="grid-column: span 2;">
                    <label for="group-rule-value">IP / CIDR / Range</label>
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
                    <label for="group-rule-label">Label (optional)</label>
                    <input type="text"
                           id="group-rule-label"
                           name="label"
                           value="{{ old('label') }}"
                           placeholder="e.g. Branch Office">
                </div>

                <div class="form-group">
                    <label for="group-rule-expires">Expires At (optional)</label>
                    <input type="datetime-local"
                           id="group-rule-expires"
                           name="expires_at"
                           value="{{ old('expires_at') }}">
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                        <span>Active immediately</span>
                    </label>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Add Rule</button>
                </div>
            </form>
        </div>
    </div>

    <details class="collapsible">
        <summary>Bulk Import into {{ $group->name }}</summary>
        <div class="collapsible-body">
            <form method="POST" action="{{ route('firewall-gui.rules.bulk-import') }}" class="form-grid">
                @csrf
                <input type="hidden" name="group_id" value="{{ $group->id }}">
                <input type="hidden" name="type" value="{{ $group->type }}">

                <div class="form-group form-group-full">
                    <label for="group-bulk-entries">Entries (one IP, CIDR, or range per line)</label>
                    <textarea id="group-bulk-entries"
                              name="entries"
                              rows="5"
                              placeholder="192.168.1.10&#10;10.0.0.0/8&#10;172.16.0.1-172.16.0.50"
                              required>{{ old('entries') }}</textarea>
                </div>

                <div class="form-group">
                    <label for="group-bulk-label">Shared Label (optional)</label>
                    <input type="text"
                           id="group-bulk-label"
                           name="label"
                           value="{{ old('label') }}"
                           placeholder="e.g. Batch Import">
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Import into Group</button>
                </div>
            </form>
        </div>
    </details>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Rules in Group</h2>
                <p class="card-subtitle">All rules currently assigned to {{ $group->name }}.</p>
            </div>
        </div>

        @if ($rules->isEmpty())
            <div class="empty-state">
                No rules are assigned to this group yet.
            </div>
        @else
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Value</th>
                            <th>Format</th>
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
