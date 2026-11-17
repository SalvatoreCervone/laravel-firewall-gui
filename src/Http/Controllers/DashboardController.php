<?php

namespace FirewallGui\LaravelFirewallGui\Http\Controllers;

use FirewallGui\LaravelFirewallGui\Models\FirewallGroup;
use FirewallGui\LaravelFirewallGui\Models\FirewallLog;
use FirewallGui\LaravelFirewallGui\Models\FirewallRule;
use FirewallGui\LaravelFirewallGui\Services\FirewallManager;
use FirewallGui\LaravelFirewallGui\Support\FirewallDecision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected FirewallManager $firewall,
    ) {}

    public function index(Request $request): View
    {
        $stats = [
            'whitelist_count' => FirewallRule::query()
                ->where('type', FirewallRule::TYPE_WHITELIST)
                ->count(),
            'blacklist_count' => FirewallRule::query()
                ->where('type', FirewallRule::TYPE_BLACKLIST)
                ->count(),
            'groups_count' => FirewallGroup::query()->count(),
            'blocked_24h' => FirewallLog::query()
                ->whereIn('action', [
                    FirewallDecision::ACTION_BLOCKED,
                    FirewallDecision::ACTION_DRY_RUN_BLOCKED,
                ])
                ->where('created_at', '>=', now()->subDay())
                ->count(),
        ];

        $filters = $request->only(['action', 'ip', 'search']);

        $logsQuery = FirewallLog::query()
            ->with(['rule', 'group'])
            ->latest('created_at')
            ->latest('id');

        if (! empty($filters['action'])) {
            $logsQuery->where('action', $filters['action']);
        }

        if (! empty($filters['ip'])) {
            $logsQuery->where('ip_address', 'like', '%'.trim((string) $filters['ip']).'%');
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $logsQuery->where(function (Builder $q) use ($search): void {
                $q->where('ip_address', 'like', "%{$search}%")
                    ->orWhere('path', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhere('user_agent', 'like', "%{$search}%");
            });
        }

        $perPage = (int) config('firewall-gui.gui.per_page', 20);

        $testIp = $request->query('test_ip');
        $testResult = null;

        if (is_string($testIp) && filter_var($testIp, FILTER_VALIDATE_IP) !== false) {
            $testResult = $this->firewall->check($testIp);
        } elseif ($request->session()->has('test_result')) {
            $testIp = $request->session()->get('test_ip');
            $testResult = $request->session()->get('test_result');
        }

        return view('firewall-gui::dashboard', [
            'stats' => $stats,
            'currentIp' => (string) $request->ip(),
            'isEnabled' => $this->firewall->isEnabled(),
            'isDryRun' => $this->firewall->isDryRun(),
            'mode' => $this->firewall->mode(),
            'logs' => $logsQuery->paginate($perPage)->withQueryString(),
            'filters' => $filters,
            'testIp' => $testIp,
            'testResult' => $testResult,
        ]);
    }

    public function testIp(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'ip' => ['required', 'ip'],
        ]);

        $ip = (string) $validated['ip'];
        $decision = $this->firewall->check($ip);

        if ($request->expectsJson()) {
            return response()->json([
                'ip' => $ip,
                'allowed' => $decision->allowed,
                'action' => $decision->action,
                'reason' => $decision->reason,
                'matched_rule' => $decision->matchedRule,
                'matched_group' => $decision->matchedGroup,
            ]);
        }

        return redirect()
            ->back()
            ->with('test_ip', $ip)
            ->with('test_result', $decision);
    }
}
