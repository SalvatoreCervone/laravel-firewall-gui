@extends('firewall-gui::layout')

@section('title', __('firewall-gui::ui.dashboard.title'))

@section('content')
    <div class="stats-grid">
        <div class="stat-card stat-green">
            <div class="stat-label">{{ __('firewall-gui::ui.dashboard.whitelist_rules') }}</div>
            <div class="stat-value">{{ number_format($stats['whitelist_count'] ?? 0) }}</div>
        </div>

        <div class="stat-card stat-red">
            <div class="stat-label">{{ __('firewall-gui::ui.dashboard.blacklist_rules') }}</div>
            <div class="stat-value">{{ number_format($stats['blacklist_count'] ?? 0) }}</div>
        </div>

        <div class="stat-card stat-blue">
            <div class="stat-label">{{ __('firewall-gui::ui.dashboard.rule_groups') }}</div>
            <div class="stat-value">{{ number_format($stats['groups_count'] ?? 0) }}</div>
        </div>

        <div class="stat-card stat-amber">
            <div class="stat-label">{{ __('firewall-gui::ui.dashboard.blocked_24h') }}</div>
            <div class="stat-value">{{ number_format($stats['blocked_24h'] ?? 0) }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body" style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <span class="text-muted" style="font-size: 0.85rem; font-weight: 600;">{{ __('firewall-gui::ui.dashboard.engine') }}</span>
                @if ($isEnabled)
                    <span class="badge badge-green">{{ __('firewall-gui::ui.dashboard.enabled') }}</span>
                @else
                    <span class="badge badge-slate">{{ __('firewall-gui::ui.dashboard.disabled') }}</span>
                @endif

                <span class="text-muted" style="font-size: 0.85rem; font-weight: 600;">{{ __('firewall-gui::ui.dashboard.mode') }}</span>
                @if ($mode === 'whitelist_only')
                    <span class="badge badge-blue">{{ __('firewall-gui::ui.dashboard.whitelist_only') }}</span>
                @else
                    <span class="badge badge-slate">{{ __('firewall-gui::ui.dashboard.blacklist_only') }}</span>
                @endif

                <span class="text-muted" style="font-size: 0.85rem; font-weight: 600;">{{ __('firewall-gui::ui.dashboard.dry_run') }}</span>
                @if ($isDryRun)
                    <span class="badge badge-amber">{{ __('firewall-gui::ui.dashboard.dry_run_active') }}</span>
                @else
                    <span class="badge badge-slate">{{ __('firewall-gui::ui.dashboard.dry_run_off') }}</span>
                @endif
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <a href="{{ route('firewall-gui.rules.index') }}" class="btn btn-secondary btn-sm">{{ __('firewall-gui::ui.dashboard.manage_rules') }}</a>
                <a href="{{ route('firewall-gui.groups.index') }}" class="btn btn-secondary btn-sm">{{ __('firewall-gui::ui.dashboard.manage_groups') }}</a>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">{{ __('firewall-gui::ui.dashboard.simulator_title') }}</h2>
                <p class="card-subtitle">{{ __('firewall-gui::ui.dashboard.simulator_subtitle') }}</p>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('firewall-gui.test-ip') }}" class="form-grid">
                @csrf
                <div class="form-group" style="grid-column: span 2;">
                    <label for="test-ip-input">{{ __('firewall-gui::ui.dashboard.ip_address') }}</label>
                    <input type="text"
                           id="test-ip-input"
                           name="ip"
                           value="{{ old('ip', $testIp ?? $currentIp) }}"
                           placeholder="e.g. 192.168.1.50 or 2001:db8::1"
                           required>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">{{ __('firewall-gui::ui.dashboard.evaluate_ip') }}</button>
                </div>
            </form>

            @if (isset($testResult) && $testResult !== null)
                <div style="margin-top: 1.25rem; padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background-color: var(--bg-subtle);">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 0.6rem;">
                            <span style="font-weight: 600;">{{ __('firewall-gui::ui.dashboard.result_for') }} <code class="mono">{{ $testIp ?? '' }}</code>:</span>
                            @if ($testResult->action === \FirewallGui\LaravelFirewallGui\Support\FirewallDecision::ACTION_ALLOWED)
                                <span class="badge badge-green">{{ __('firewall-gui::ui.dashboard.allowed') }}</span>
                            @elseif ($testResult->action === \FirewallGui\LaravelFirewallGui\Support\FirewallDecision::ACTION_DRY_RUN_BLOCKED)
                                <span class="badge badge-amber">{{ __('firewall-gui::ui.dashboard.dry_run_blocked') }}</span>
                            @else
                                <span class="badge badge-red">{{ __('firewall-gui::ui.dashboard.blocked') }}</span>
                            @endif
                        </div>
                    </div>

                    <p style="margin: 0.6rem 0 0; font-size: 0.9rem; color: var(--text-secondary);">
                        <strong>{{ __('firewall-gui::ui.dashboard.reason') }}</strong> {{ $testResult->reason }}
                    </p>

                    @if (! empty($testResult->matchedRule))
                        <p style="margin: 0.35rem 0 0; font-size: 0.85rem; color: var(--text-secondary);">
                            <strong>{{ __('firewall-gui::ui.dashboard.matched_rule') }}</strong>
                            <code class="mono">{{ $testResult->matchedRule['value'] ?? '—' }}</code>
                            @if (! empty($testResult->matchedRule['rule_type']))
                                <span class="badge badge-slate">{{ strtoupper($testResult->matchedRule['rule_type']) }}</span>
                            @endif
                            @if (! empty($testResult->matchedRule['label']))
                                ({{ $testResult->matchedRule['label'] }})
                            @endif
                        </p>
                    @endif

                    @if (! empty($testResult->matchedGroup))
                        <p style="margin: 0.35rem 0 0; font-size: 0.85rem; color: var(--text-secondary);">
                            <strong>{{ __('firewall-gui::ui.dashboard.matched_group') }}</strong>
                            {{ $testResult->matchedGroup['name'] ?? '—' }}
                            @if (! empty($testResult->matchedGroup['type']))
                                <span class="badge {{ $testResult->matchedGroup['type'] === 'whitelist' ? 'badge-green' : 'badge-red' }}">
                                    {{ ucfirst($testResult->matchedGroup['type']) }}
                                </span>
                            @endif
                        </p>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">{{ __('firewall-gui::ui.dashboard.recent_title') }}</h2>
                <p class="card-subtitle">{{ __('firewall-gui::ui.dashboard.recent_subtitle') }}</p>
            </div>
            <a href="{{ route('firewall-gui.logs.index') }}" class="btn btn-secondary btn-sm">{{ __('firewall-gui::ui.dashboard.view_all_logs') }}</a>
        </div>

        @if ($recentLogs->isEmpty())
            <div class="empty-state">
                {{ __('firewall-gui::ui.dashboard.empty_logs') }}
            </div>
        @else
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('firewall-gui::ui.dashboard.col_timestamp') }}</th>
                            <th>{{ __('firewall-gui::ui.dashboard.col_ip') }}</th>
                            <th>{{ __('firewall-gui::ui.dashboard.col_request') }}</th>
                            <th>{{ __('firewall-gui::ui.dashboard.col_action') }}</th>
                            <th>{{ __('firewall-gui::ui.dashboard.col_reason') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentLogs as $log)
                            <tr>
                                <td class="mono text-muted">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                                <td class="mono">{{ $log->ip_address }}</td>
                                <td>
                                    <span class="badge badge-slate">{{ $log->method }}</span>
                                    <span class="mono">{{ $log->path }}</span>
                                </td>
                                <td>
                                    @if ($log->action === 'dry_run_blocked')
                                        <span class="badge badge-amber">Dry-Run Blocked</span>
                                    @else
                                        <span class="badge badge-red">Blocked</span>
                                    @endif
                                </td>
                                <td class="text-muted">{{ $log->reason ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
