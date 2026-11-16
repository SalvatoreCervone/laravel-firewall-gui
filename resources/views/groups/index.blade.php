@extends('firewall-gui::layout')

@section('title', __('firewall-gui::ui.groups.title'))

@section('content')
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">{{ __('firewall-gui::ui.groups.create_title') }}</h2>
                <p class="card-subtitle">{{ __('firewall-gui::ui.groups.create_subtitle') }}</p>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('firewall-gui.groups.store') }}" class="form-grid">
                @csrf

                <div class="form-group">
                    <label for="group-name">{{ __('firewall-gui::ui.groups.name') }}</label>
                    <input type="text"
                           id="group-name"
                           name="name"
                           value="{{ old('name') }}"
                           placeholder="{{ __('firewall-gui::ui.groups.name_placeholder') }}"
                           required>
                </div>

                <div class="form-group">
                    <label for="group-type">{{ __('firewall-gui::ui.groups.type') }}</label>
                    <select id="group-type" name="type" required>
                        <option value="blacklist" {{ old('type', 'blacklist') === 'blacklist' ? 'selected' : '' }}>{{ __('firewall-gui::ui.common.blacklist') }}</option>
                        <option value="whitelist" {{ old('type') === 'whitelist' ? 'selected' : '' }}>{{ __('firewall-gui::ui.common.whitelist') }}</option>
                        <option value="notify" {{ old('type') === 'notify' ? 'selected' : '' }}>{{ __('firewall-gui::ui.common.notify_alert_only') }}</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="group-priority">{{ __('firewall-gui::ui.groups.priority') }}</label>
                    <input type="number"
                           id="group-priority"
                           name="priority"
                           value="{{ old('priority', 0) }}"
                           step="1">
                </div>

                <div class="form-group">
                    <label for="group-cooldown">{{ __('firewall-gui::ui.groups.cooldown_hours') }}</label>
                    <input type="number"
                           id="group-cooldown"
                           name="notification_cooldown_hours"
                           min="1"
                           max="8760"
                           value="{{ old('notification_cooldown_hours') }}"
                           placeholder="{{ __('firewall-gui::ui.groups.cooldown_placeholder', ['hours' => $globalCooldownHours ?? 24]) }}">
                </div>

                <div class="form-group" style="grid-column: span 2;">
                    <label for="group-description">{{ __('firewall-gui::ui.groups.description_optional') }}</label>
                    <input type="text"
                           id="group-description"
                           name="description"
                           value="{{ old('description') }}"
                           placeholder="{{ __('firewall-gui::ui.groups.description_placeholder') }}">
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                        <span>{{ __('firewall-gui::ui.groups.active_immediately') }}</span>
                    </label>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="notify_on_hit" value="1" {{ old('notify_on_hit', false) ? 'checked' : '' }}>
                        <span>{{ __('firewall-gui::ui.groups.email_alert_on_hit') }}</span>
                    </label>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">{{ __('firewall-gui::ui.groups.create_button') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">{{ __('firewall-gui::ui.groups.list_title') }}</h2>
                <p class="card-subtitle">{{ __('firewall-gui::ui.groups.list_subtitle') }}</p>
            </div>
        </div>

        @if ($groups->isEmpty())
            <div class="empty-state">
                {{ __('firewall-gui::ui.groups.empty') }}
            </div>
        @else
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Rules</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($groups as $group)
                            <tr>
                                <td>
                                    <a href="{{ route('firewall-gui.groups.show', $group) }}" style="font-weight: 600;">
                                        {{ $group->name }}
                                    </a>
                                    @if ($group->description)
                                        <div class="text-muted" style="font-size: 0.8rem;">{{ $group->description }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($group->type === 'whitelist')
                                        <span class="badge badge-green">Whitelist</span>
                                    @elseif ($group->type === 'notify')
                                        <span class="badge badge-blue">Notify Only</span>
                                    @else
                                        <span class="badge badge-red">Blacklist</span>
                                    @endif
                                    @if ($group->type !== 'notify' && $group->notify_on_hit)
                                        <span class="badge badge-blue" style="margin-left: 0.25rem;">+ Notify</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-slate">{{ $group->rules_count ?? 0 }}</span>
                                </td>
                                <td class="mono">{{ $group->priority }}</td>
                                <td>
                                    @if ($group->is_active)
                                        <span class="badge badge-green">Active</span>
                                    @else
                                        <span class="badge badge-slate">Inactive</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="actions-cell">
                                        <a href="{{ route('firewall-gui.groups.show', $group) }}" class="btn btn-secondary btn-sm">
                                            Manage
                                        </a>

                                        <form method="POST" action="{{ route('firewall-gui.groups.toggle', $group) }}" class="inline-form">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-secondary btn-sm">
                                                {{ $group->is_active ? 'Disable' : 'Enable' }}
                                            </button>
                                        </form>

                                        <form method="POST"
                                              action="{{ route('firewall-gui.groups.destroy', $group) }}"
                                              class="inline-form"
                                              data-confirm="Delete group {{ $group->name }} and all of its rules?">
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

            @if ($groups->hasPages())
                <div class="pagination-wrap">
                    {{ $groups->withQueryString()->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
