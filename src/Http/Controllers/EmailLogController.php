<?php

namespace FirewallGui\LaravelFirewallGui\Http\Controllers;

use FirewallGui\LaravelFirewallGui\Models\FirewallNotificationLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class EmailLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['ip', 'recipient']);

        $query = FirewallNotificationLog::query()
            ->with(['rule', 'group'])
            ->latest('created_at')
            ->latest('id');

        if (! empty($filters['ip'])) {
            $query->where('ip_address', 'like', '%'.trim((string) $filters['ip']).'%');
        }

        if (! empty($filters['recipient'])) {
            $query->where('recipients', 'like', '%'.trim((string) $filters['recipient']).'%');
        }

        $perPage = (int) config('firewall-gui.gui.per_page', 20);

        return view('firewall-gui::email-logs.index', [
            'emailLogs' => $query->paginate($perPage)->withQueryString(),
            'filters' => $filters,
            'currentIp' => (string) $request->ip(),
        ]);
    }

    public function clear(): RedirectResponse
    {
        FirewallNotificationLog::query()->delete();

        return redirect()
            ->back()
            ->with('success', __('firewall-gui::ui.email_logs.cleared_message'));
    }
}
