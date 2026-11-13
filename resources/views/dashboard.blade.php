@extends('firewall-gui::layout')

@section('title', 'Dashboard')

@section('content')
    <div class="stats-grid">
        <div class="stat-card stat-green">
            <div class="stat-label">Whitelist Rules</div>
            <div class="stat-value">{{ number_format($stats['whitelist_count'] ?? 0) }}</div>
        </div>

        <div class="stat-card stat-red">
            <div class="stat-label">Blacklist Rules</div>
            <div class="stat-value">{{ number_format($stats['blacklist_count'] ?? 0) }}</div>
        </div>

        <div class="stat-card stat-blue">
            <div class="stat-label">Rule Groups</div>
            <div class="stat-value">{{ number_format($stats['groups_count'] ?? 0) }}</div>
        </div>

        <div class="stat-card stat-amber">
            <div class="stat-label">Blocked (24h)</div>
            <div class="stat-value">{{ number_format($stats['blocked_24h'] ?? 0) }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body" style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <span class="text-muted" style="font-size: 0.85rem; font-weight: 600;">Firewall Engine:</span>
                @if ($isEnabled)
                    <span class="badge badge-green">Enabled</span>
                @else
                    <span class="badge badge-slate">Disabled</span>
                @endif

                <span class="text-muted" style="font-size: 0.85rem; font-weight: 600;">Mode:</span>
                @if ($mode === 'whitelist_only')
                    <span class="badge badge-blue">Whitelist Only</span>
                @else
                    <span class="badge badge-slate">Blacklist Only</span>
                @endif

                <span class="text-muted" style="font-size: 0.85rem; font-weight: 600;">Dry-Run:</span>
                @if ($isDryRun)
                    <span class="badge badge-amber">Active (Logging Only)</span>
                @else
                    <span class="badge badge-slate">Off (Enforcing)</span>
                @endif
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <a href="{{ route('firewall-gui.rules.index') }}" class="btn btn-secondary btn-sm">Manage Rules</a>
                <a href="{{ route('firewall-gui.groups.index') }}" class="btn btn-secondary btn-sm">Manage Groups</a>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Test IP Simulator</h2>
                <p class="card-subtitle">Simulate how the firewall evaluates an IPv4 or IPv6 address against active rules and groups.</p>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('firewall-gui.test-ip') }}" class="form-grid">
                @csrf
                <div class="form-group" style="grid-column: span 2;">
                    <label for="test-ip-input">IP Address</label>
                    <input type="text"
                           id="test-ip-input"
                           name="ip"
                           value="{{ old('ip', $testIp ?? $currentIp) }}"
                           placeholder="e.g. 192.168.1.50 or 2001:db8::1"
                           required>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Evaluate IP</button>
                </div>
            </form>

            @if (isset($testResult) && $testResult !== null)
                <div style="margin-top: 1.25rem; padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background-color: var(--bg-subtle);">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 0.6rem;">
                            <span style="font-weight: 600;">Result for <code class="mono">{{ $testIp ?? '' }}</code>:</span>
                            @if ($testResult->action === \FirewallGui\LaravelFirewallGui\Support\FirewallDecision::ACTION_ALLOWED)
                                <span class="badge badge-green">Allowed</span>
                            @elseif ($testResult->action === \FirewallGui\LaravelFirewallGui\Support\FirewallDecision::ACTION_DRY_RUN_BLOCKED)
                                <span class="badge badge-amber">Dry-Run Blocked</span>
                            @else
                                <span class="badge badge-red">Blocked</span>
                            @endif
                        </div>
                    </div>

                    <p style="margin: 0.6rem 0 0; font-size: 0.9rem; color: var(--text-secondary);">
                        <strong>Reason:</strong> {{ $testResult->reason }}
                    </p>

                    @if (! empty($testResult->matchedRule))
                        <p style="margin: 0.35rem 0 0; font-size: 0.85rem; color: var(--text-secondary);">
                            <strong>Matched Rule:</strong>
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
                            <strong>Matched Group:</strong>
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
                <h2 class="card-title">Recent Blocked Requests</h2>
                <p class="card-subtitle">Latest blocked and dry-run blocked traffic.</p>
            </div>
            <a href="{{ route('firewall-gui.logs.index') }}" class="btn btn-secondary btn-sm">View All Logs</a>
        </div>

        @if ($recentLogs->isEmpty())
            <div class="empty-state">
                No blocked requests have been logged yet.
            </div>
        @else
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>IP Address</th>
                            <th>Request</th>
                            <th>Action</th>
                            <th>Reason</th>
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
