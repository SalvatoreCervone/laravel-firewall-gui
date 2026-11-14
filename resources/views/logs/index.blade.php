@extends('firewall-gui::layout')

@section('title', 'Logs')

@section('content')
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Firewall Logs</h2>
                <p class="card-subtitle">Inspect blocked and dry-run blocked requests, or quickly whitelist a false positive.</p>
            </div>

            <form method="POST"
                  action="{{ route('firewall-gui.logs.clear') }}"
                  class="inline-form"
                  data-confirm="Clear all firewall logs? This action cannot be undone.">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm">Clear Logs</button>
            </form>
        </div>

        <div class="card-body" style="border-bottom: 1px solid var(--border-color); background-color: var(--bg-subtle);">
            <form method="GET" action="{{ route('firewall-gui.logs.index') }}" class="form-grid">
                <div class="form-group">
                    <label for="log-filter-action">Action</label>
                    <select id="log-filter-action" name="action">
                        <option value="">All Actions</option>
                        <option value="blocked" {{ ($filters['action'] ?? '') === 'blocked' ? 'selected' : '' }}>Blocked</option>
                        <option value="dry_run_blocked" {{ ($filters['action'] ?? '') === 'dry_run_blocked' ? 'selected' : '' }}>Dry-Run Blocked</option>
                        <option value="notified" {{ ($filters['action'] ?? '') === 'notified' ? 'selected' : '' }}>Notified</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="log-filter-ip">IP Address</label>
                    <input type="text"
                           id="log-filter-ip"
                           name="ip"
                           value="{{ $filters['ip'] ?? '' }}"
                           placeholder="e.g. 203.0.113.10">
                </div>

                <div class="form-group">
                    <label for="log-filter-search">Search Path / Reason</label>
                    <input type="text"
                           id="log-filter-search"
                           name="search"
                           value="{{ $filters['search'] ?? '' }}"
                           placeholder="Path, method, or reason...">
                </div>

                <div class="form-group" style="flex-direction: row; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('firewall-gui.logs.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        @if ($logs->isEmpty())
            <div class="empty-state">
                No firewall log entries match the current filters.
            </div>
        @else
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>IP Address</th>
                            <th>Method &amp; Path</th>
                            <th>Action</th>
                            <th>Reason / Matched Rule</th>
                            <th>Quick Action</th>
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
                                                Rule: <code class="mono">{{ $log->rule->value }}</code>
                                            @endif
                                            @if ($log->group)
                                                &middot; Group: <a href="{{ route('firewall-gui.groups.show', $log->group) }}">{{ $log->group->name }}</a>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST"
                                          action="{{ route('firewall-gui.logs.allow', $log) }}"
                                          class="inline-form"
                                          data-confirm="Add {{ $log->ip_address }} to the whitelist?">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-success btn-sm">Whitelist IP</button>
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
