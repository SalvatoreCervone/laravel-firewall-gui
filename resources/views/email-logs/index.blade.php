@extends('firewall-gui::layout')

@section('title', __('firewall-gui::ui.email_logs.title'))

@section('content')
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">{{ __('firewall-gui::ui.email_logs.card_title') }}</h2>
                <p class="card-subtitle">{{ __('firewall-gui::ui.email_logs.card_subtitle') }}</p>
            </div>

            <form method="POST"
                  action="{{ route('firewall-gui.email-logs.clear') }}"
                  class="inline-form"
                  data-confirm="{{ __('firewall-gui::ui.email_logs.clear_confirm') }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('firewall-gui::ui.email_logs.clear_button') }}</button>
            </form>
        </div>

        <div class="card-body" style="border-bottom: 1px solid var(--border-color); background-color: var(--bg-subtle);">
            <form method="GET" action="{{ route('firewall-gui.email-logs.index') }}" class="form-grid">
                <div class="form-group">
                    <label for="email-log-filter-ip">{{ __('firewall-gui::ui.email_logs.filter_ip') }}</label>
                    <input type="text"
                           id="email-log-filter-ip"
                           name="ip"
                           value="{{ $filters['ip'] ?? '' }}"
                           placeholder="e.g. 203.0.113.10">
                </div>

                <div class="form-group">
                    <label for="email-log-filter-recipient">{{ __('firewall-gui::ui.email_logs.filter_recipient') }}</label>
                    <input type="text"
                           id="email-log-filter-recipient"
                           name="recipient"
                           value="{{ $filters['recipient'] ?? '' }}"
                           placeholder="{{ __('firewall-gui::ui.email_logs.filter_recipient_placeholder') }}">
                </div>

                <div class="form-group" style="flex-direction: row; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary">{{ __('firewall-gui::ui.common.filter') }}</button>
                    <a href="{{ route('firewall-gui.email-logs.index') }}" class="btn btn-secondary">{{ __('firewall-gui::ui.common.reset') }}</a>
                </div>
            </form>
        </div>

        @if ($emailLogs->isEmpty())
            <div class="empty-state">
                {{ __('firewall-gui::ui.email_logs.empty') }}
            </div>
        @else
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('firewall-gui::ui.email_logs.col_timestamp') }}</th>
                            <th>{{ __('firewall-gui::ui.email_logs.col_ip') }}</th>
                            <th>{{ __('firewall-gui::ui.email_logs.col_action') }}</th>
                            <th>{{ __('firewall-gui::ui.email_logs.col_recipients') }}</th>
                            <th>{{ __('firewall-gui::ui.email_logs.col_status') }}</th>
                            <th>{{ __('firewall-gui::ui.email_logs.col_subject') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($emailLogs as $log)
                            <tr>
                                <td class="mono text-muted" style="white-space: nowrap;">
                                    {{ $log->created_at?->format('Y-m-d H:i:s') }}
                                </td>
                                <td class="mono">{{ $log->ip_address }}</td>
                                <td>
                                    @if ($log->decision_action === 'blocked')
                                        <span class="badge badge-red">{{ __('firewall-gui::ui.logs.blocked') }}</span>
                                    @elseif ($log->decision_action === 'dry_run_blocked')
                                        <span class="badge badge-amber">{{ __('firewall-gui::ui.logs.dry_run_blocked') }}</span>
                                    @else
                                        <span class="badge badge-green">{{ __('firewall-gui::ui.dashboard.allowed') }}</span>
                                    @endif
                                </td>
                                <td class="mono">{{ $log->recipients }}</td>
                                <td>
                                    @if ($log->status === 'sent')
                                        <span class="badge badge-green">{{ __('firewall-gui::ui.email_logs.status_sent') }}</span>
                                        @if ($log->sent_at)
                                            <div class="text-muted mono" style="font-size: 0.72rem; margin-top: 0.15rem;">
                                                {{ $log->sent_at->format('H:i:s') }}
                                            </div>
                                        @endif
                                    @elseif ($log->status === 'failed')
                                        <span class="badge badge-red">{{ __('firewall-gui::ui.email_logs.status_failed') }}</span>
                                        @if ($log->error_message)
                                            <div class="text-muted" style="font-size: 0.75rem; margin-top: 0.15rem; max-width: 240px;">
                                                {{ \Illuminate\Support\Str::limit($log->error_message, 120) }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="badge badge-amber">{{ __('firewall-gui::ui.email_logs.status_queued') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $log->subject ?? '—' }}</div>
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
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($emailLogs->hasPages())
                <div class="pagination-wrap">
                    {{ $emailLogs->withQueryString()->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
