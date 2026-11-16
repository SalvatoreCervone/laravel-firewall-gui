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
                            <th>{{ __('firewall-gui::ui.groups.col_name') }}</th>
                            <th>{{ __('firewall-gui::ui.groups.col_rules') }}</th>
                            <th>{{ __('firewall-gui::ui.groups.col_priority') }}</th>
                            <th>{{ __('firewall-gui::ui.common.status') }}</th>
                            <th>{{ __('firewall-gui::ui.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($groups as $group)
                            <tr>
                                <td>
                                    <a href="{{ route('firewall-gui.groups.show', $group) }}" style="font-weight: 600;">
                                        {{ $group->name }}
                                    </a>
                                    @if ($group->notify_on_hit)
                                        <span class="badge badge-blue" style="margin-left: 0.35rem;">{{ __('firewall-gui::ui.common.plus_notify') }}</span>
                                    @endif
                                    @if ($group->description)
                                        <div class="text-muted" style="font-size: 0.8rem;">{{ $group->description }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-slate">{{ $group->rules_count ?? 0 }}</span>
                                </td>
                                <td class="mono">{{ $group->priority }}</td>
                                <td>
                                    @if ($group->is_active)
                                        <span class="badge badge-green">{{ __('firewall-gui::ui.common.active') }}</span>
                                    @else
                                        <span class="badge badge-slate">{{ __('firewall-gui::ui.common.inactive') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="actions-cell">
                                        <a href="{{ route('firewall-gui.groups.show', $group) }}" class="btn btn-secondary btn-sm">
                                            {{ __('firewall-gui::ui.common.manage') }}
                                        </a>

                                        <form method="POST" action="{{ route('firewall-gui.groups.toggle', $group) }}" class="inline-form">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-secondary btn-sm">
                                                {{ $group->is_active ? __('firewall-gui::ui.common.disable') : __('firewall-gui::ui.common.enable') }}
                                            </button>
                                        </form>

                                        <form method="POST"
                                              action="{{ route('firewall-gui.groups.destroy', $group) }}"
                                              class="inline-form"
                                              data-confirm="{{ __('firewall-gui::ui.groups.delete_confirm', ['name' => $group->name]) }}">
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

            @if ($groups->hasPages())
                <div class="pagination-wrap">
                    {{ $groups->withQueryString()->links() }}
                </div>
            @endif
        @endif
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">{{ __('firewall-gui::ui.groups.default_rule_title') }}</h2>
                <p class="card-subtitle">{{ __('firewall-gui::ui.groups.default_rule_subtitle') }}</p>
            </div>
        </div>
        <div class="card-body">
            @php
                $selectedDefaultAction = old('default_action', $defaultRule['action'] ?? 'allow');
                $defaultNotify = old('default_notify', $defaultRule['notify'] ?? false);
            @endphp

            <form method="POST" action="{{ route('firewall-gui.groups.default-rule.update') }}" class="settings-grid">
                @csrf
                @method('PUT')

                <div class="form-group form-group-full">
                    <label for="default-rule-action">{{ __('firewall-gui::ui.groups.default_action_label') }}</label>
                    <select id="default-rule-action" name="default_action">
                        <option value="allow" {{ $selectedDefaultAction === 'allow' ? 'selected' : '' }}>{{ __('firewall-gui::ui.groups.default_action_allow') }}</option>
                        <option value="block" {{ $selectedDefaultAction === 'block' ? 'selected' : '' }}>{{ __('firewall-gui::ui.groups.default_action_block') }}</option>
                    </select>
                </div>

                <div class="settings-toggles">
                    <label class="checkbox-label">
                        <input type="checkbox"
                               name="default_notify"
                               value="1"
                               {{ $defaultNotify ? 'checked' : '' }}>
                        <span>{{ __('firewall-gui::ui.groups.default_notify_label') }}</span>
                    </label>
                </div>

                <div class="settings-actions">
                    <button type="submit" class="btn btn-primary">{{ __('firewall-gui::ui.groups.default_rule_save') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
