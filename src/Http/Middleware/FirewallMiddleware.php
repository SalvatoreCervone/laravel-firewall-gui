<?php

namespace FirewallGui\LaravelFirewallGui\Http\Middleware;

use Closure;
use FirewallGui\LaravelFirewallGui\Services\FirewallManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FirewallMiddleware
{
    public function __construct(
        protected FirewallManager $firewall,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->firewall->isEnabled()) {
            return $next($request);
        }

        if (! (bool) config('firewall-gui.gui.protect_with_firewall', false) && $this->isGuiRequest($request)) {
            return $next($request);
        }

        $ip = (string) $request->ip();
        $decision = $this->firewall->check($ip);

        if ($decision->isBlocked()) {
            $this->firewall->logDecision($request, $decision);

            if ($this->firewall->isDryRun()) {
                return $next($request);
            }

            $status = (int) config('firewall-gui.response.status', 403);
            $message = (string) config('firewall-gui.response.message', 'Access denied by firewall.');

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'ip' => $ip,
                ], $status);
            }

            $view = (string) config('firewall-gui.response.view', 'firewall-gui::blocked');

            if (view()->exists($view)) {
                return response()->view($view, [
                    'ip' => $ip,
                    'message' => $message,
                    'decision' => $decision,
                ], $status);
            }

            abort($status, $message);
        }

        return $next($request);
    }

    protected function isGuiRequest(Request $request): bool
    {
        $prefix = trim((string) config('firewall-gui.gui.prefix', 'firewall'), '/');

        if ($prefix === '') {
            return false;
        }

        return $request->is($prefix) || $request->is("{$prefix}/*");
    }
}
