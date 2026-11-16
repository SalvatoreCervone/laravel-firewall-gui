@extends('firewall-gui::layout')

@section('title', __('firewall-gui::ui.logs.title'))

@section('content')
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">{{ __('firewall-gui::ui.logs.card_title') }}</h2>
                <p class="card-subtitle">{{ __('firewall-gui::ui.logs.card_subtitle') }}</p>
            </div>

            <form method="POST"
                  action="{{ route('firewall-gui.logs.clear') }}"
                  class="inline-form"
                  data-confirm="{{ __('firewall-gui::ui.logs.clear_confirm') }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('firewall-gui::ui.logs.clear_button') }}</button>
            </form>
        </div>

        <div class="card-body" style="border-bottom: 1px solid var(--border-color); background-color: var(--bg-subtle);">
            <form method="GET" action="{{ route('firewall-gui.logs.index') }}" class="form-grid">
                <div class="form-group">
                    <label for="log-filter-action">{{ __('firewall-gui::ui.logs.action') }}</label>
                    <select id="log-filter-action" name="action">
                        <option value="">{{ __('firewall-gui::ui.logs.all_actions') }}</option>
                        <option value="blocked" {{ ($filters['action'] ?? '') === 'blocked' ? 'selected' : '' }}>{{ __('firewall-gui::ui.logs.blocked') }}</option>
                        <option value="dry_run_blocked" {{ ($filters['action'] ?? '') === 'dry_run_blocked' ? 'selected' : '' }}>{{ __('firewall-gui::ui.logs.dry_run_blocked') }}</option>
                        <option value="notified" {{ ($filters['action'] ?? '') === 'notified' ? 'selected' : '' }}>{{ __('firewall-gui::ui.logs.notified') }}</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="log-filter-ip">{{ __('firewall-gui::ui.logs.ip_address') }}</label>
                    <input type="text"
                           id="log-filter-ip"
                           name="ip"
                           value="{{ $filters['ip'] ?? '' }}"
                           placeholder="e.g. 203.0.113.10">
                </div>

                <div class="form-group">
                    <label for="log-filter-search">{{ __('firewall-gui::ui.logs.search_label') }}</label>
                    <input type="text"
                           id="log-filter-search"
                           name="search"
                           value="{{ $filters['search'] ?? '' }}"
                           placeholder="{{ __('firewall-gui::ui.logs.search_placeholder') }}">
                </div>

                <div class="form-group" style="flex-direction: row; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary">{{ __('firewall-gui::ui.common.filter') }}</button>
                    <a href="{{ route('firewall-gui.logs.index') }}" class="btn btn-secondary">{{ __('firewall-gui::ui.common.reset') }}</a>
                </div>
            </form>
        </div>

        @if ($logs->isEmpty())
            <div class="empty-state">
                {{ __('firewall-gui::ui.logs.empty') }}
            </div>
        @else
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('firewall-gui::ui.logs.col_timestamp') }}</th>
                            <th>{{ __('firewall-gui::ui.logs.col_ip') }}</th>
                            <th>{{ __('firewall-gui::ui.logs.col_method_path') }}</th>
                            <th>{{ __('firewall-gui::ui.logs.col_action') }}</th>
                            <th>{{ __('firewall-gui::ui.logs.col_reason') }}</th>
                            <th>{{ __('firewall-gui::ui.logs.col_quick_action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td class="mono text-muted" style="white-space: nowrap;">
                                    {{ $log->created_at?->format('Y-m-d H:i:s') }}
                                </td>
                                <td class="mono">{{ $log->ip_address }}</td>
                                <td>
                                    <span class="badge badge-slate">{{ $log->method }}</span>
                                    <span class="mono">{{ $log->path }}</span>
                                </td>
                                <td>
                                    @if ($log->action === 'dry_run_blocked')
                                        <span class="badge badge-amber">Dry-Run Blocked</span>
                                    @elseif ($log->action === 'notified')
                                        <span class="badge badge-blue">Notified</span>
                                    @else
                                        <span class="badge badge-red">Blocked</span>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $log->reason ?? '—' }}</div>
                                    @if ($log->rule || $log->group)
                                        <div class="text-muted" style="font-size: 0.78rem; margin-top: 0.15rem;">
                                            @if ($log->rule)
                                                {{ __('firewall-gui::ui.logs.rule_prefix') }} <code class="mono">{{ $log->rule->value }}</code>
                                            @endif
                                            @if ($log->group)
                                                &middot; {{ __('firewall-gui::ui.logs.group_prefix') }} <a href="{{ route('firewall-gui.groups.show', $log->group) }}">{{ $log->group->name }}</a>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST"
                                          action="{{ route('firewall-gui.logs.allow', $log) }}"
                                          class="inline-form"
                                          data-confirm="{{ __('firewall-gui::ui.logs.whitelist_ip_confirm', ['ip' => $log->ip_address]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-success btn-sm">{{ __('firewall-gui::ui.logs.whitelist_ip_button') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="pagination-wrap">
                    {{ $logs->withQueryString()->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
