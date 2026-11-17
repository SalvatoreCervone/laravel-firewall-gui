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

    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('firewall-gui.dashboard', $request->query());
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
