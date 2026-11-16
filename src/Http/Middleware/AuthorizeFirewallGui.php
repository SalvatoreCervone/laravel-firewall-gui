<?php

namespace FirewallGui\LaravelFirewallGui\Http\Middleware;

use Closure;
use FirewallGui\LaravelFirewallGui\Services\FirewallManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeFirewallGui
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! FirewallManager::checkAuth($request)) {
            abort(403, 'Unauthorized access to Firewall GUI.');
        }

        if (($locale = app(FirewallManager::class)->getGuiLocale()) !== null) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
