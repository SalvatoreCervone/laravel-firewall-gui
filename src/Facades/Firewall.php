<?php

namespace FirewallGui\LaravelFirewallGui\Facades;

use FirewallGui\LaravelFirewallGui\Models\FirewallRule;
use FirewallGui\LaravelFirewallGui\Services\FirewallManager;
use FirewallGui\LaravelFirewallGui\Support\FirewallDecision;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Facade;

/**
 * @method static FirewallDecision check(string $ip, ?string $overrideMode = null)
 * @method static FirewallRule allow(string $value, ?string $label = null, ?int $groupId = null, ?Carbon $expiresAt = null, ?string $createdBy = null, bool $notifyOnHit = false)
 * @method static FirewallRule block(string $value, ?string $label = null, ?int $groupId = null, ?Carbon $expiresAt = null, ?string $createdBy = null, bool $notifyOnHit = false)
 * @method static FirewallRule notify(string $value, ?string $label = null, ?int $groupId = null, ?Carbon $expiresAt = null, ?string $createdBy = null)
 * @method static int unblock(string $value)
 * @method static void clearCache()
 * @method static bool isEnabled()
 * @method static bool isDryRun()
 * @method static string mode()
 * @method static void auth(?\Closure $callback)
 *
 * @see FirewallManager
 */
class Firewall extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FirewallManager::class;
    }
}
