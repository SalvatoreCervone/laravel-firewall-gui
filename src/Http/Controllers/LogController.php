<?php

namespace FirewallGui\LaravelFirewallGui\Http\Controllers;

use FirewallGui\LaravelFirewallGui\Models\FirewallLog;
use FirewallGui\LaravelFirewallGui\Models\FirewallRule;
use FirewallGui\LaravelFirewallGui\Services\FirewallManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class LogController extends Controller
{
    public function __construct(
        protected FirewallManager $firewall,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['action', 'ip', 'search']);

        $query = FirewallLog::query()
            ->with(['rule', 'group'])
            ->latest('created_at');

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['ip'])) {
            $query->where('ip_address', 'like', '%'.(string) $filters['ip'].'%');
        }

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function (Builder $q) use ($search): void {
                $q->where('ip_address', 'like', "%{$search}%")
                    ->orWhere('path', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhere('user_agent', 'like', "%{$search}%");
            });
        }

        $perPage = (int) config('firewall-gui.gui.per_page', 20);

        return view('firewall-gui::logs.index', [
            'logs' => $query->paginate($perPage)->withQueryString(),
            'currentIp' => (string) $request->ip(),
            'filters' => $filters,
        ]);
    }

    public function allowIp(Request $request, FirewallLog $log): RedirectResponse
    {
        $exists = FirewallRule::query()
            ->whereNull('group_id')
            ->where('type', FirewallRule::TYPE_WHITELIST)
            ->where('value', $log->ip_address)
            ->active()
            ->exists();

        if (! $exists) {
            $this->firewall->allow(
                value: $log->ip_address,
                label: "Whitelisted from log #{$log->id}",
                createdBy: 'gui-log',
            );
        }

        $this->firewall->clearCache();

        return redirect()
            ->back()
            ->with('success', "IP [{$log->ip_address}] whitelisted.");
    }

    public function clear(): RedirectResponse
    {
        FirewallLog::query()->delete();

        return redirect()
            ->back()
            ->with('success', 'All firewall logs cleared.');
    }
}
