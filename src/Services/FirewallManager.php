<?php

namespace FirewallGui\LaravelFirewallGui\Services;

use Closure;
use FirewallGui\LaravelFirewallGui\Models\FirewallGroup;
use FirewallGui\LaravelFirewallGui\Models\FirewallLog;
use FirewallGui\LaravelFirewallGui\Models\FirewallRule;
use FirewallGui\LaravelFirewallGui\Support\FirewallDecision;
use FirewallGui\LaravelFirewallGui\Support\IpMatcher;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class FirewallManager
{
    protected static ?Closure $authCallback = null;

    public function __construct(
        protected CacheFactory $cache,
    ) {}

    public static function auth(?Closure $callback): void
    {
        static::$authCallback = $callback;
    }

    public static function checkAuth(Request $request): bool
    {
        if (static::$authCallback !== null) {
            return (bool) (static::$authCallback)($request);
        }

        $gate = config('firewall-gui.gui.gate');
        if (is_string($gate) && $gate !== '') {
            return Gate::forUser($request->user())->allows($gate);
        }

        if (Gate::has('manage-firewall')) {
            return Gate::forUser($request->user())->allows('manage-firewall');
        }

        return true;
    }

    public function isEnabled(): bool
    {
        return (bool) config('firewall-gui.enabled', true);
    }

    public function isDryRun(): bool
    {
        return (bool) config('firewall-gui.dry_run', false);
    }

    public function mode(): string
    {
        return (string) config('firewall-gui.mode', 'blacklist_only');
    }

    public function check(string $ip, ?string $overrideMode = null): FirewallDecision
    {
        $ip = trim($ip);

        if (! $this->isEnabled()) {
            return new FirewallDecision(
                allowed: true,
                action: FirewallDecision::ACTION_ALLOWED,
                reason: 'Firewall is disabled.',
            );
        }

        foreach ((array) config('firewall-gui.bypass_ips', []) as $bypassValue) {
            if (is_string($bypassValue) && $bypassValue !== '' && IpMatcher::matches($ip, $bypassValue)) {
                return new FirewallDecision(
                    allowed: true,
                    action: FirewallDecision::ACTION_ALLOWED,
                    reason: "Bypassed via config ({$bypassValue}).",
                );
            }
        }

        $payload = $this->getCachedRules();
        $mode = $overrideMode ?? $this->mode();
        $whitelistOverrides = (bool) config('firewall-gui.whitelist_overrides_blacklist', true);

        $whitelistMatch = $this->findMatchingRule($ip, $payload['whitelist'] ?? []);
        $blacklistMatch = $this->findMatchingRule($ip, $payload['blacklist'] ?? []);

        if ($whitelistOverrides && $whitelistMatch !== null) {
            return new FirewallDecision(
                allowed: true,
                action: FirewallDecision::ACTION_ALLOWED,
                reason: $this->formatReason('Allowed by whitelist rule', $whitelistMatch),
                matchedRule: $whitelistMatch,
                matchedGroup: $whitelistMatch['group'] ?? null,
            );
        }

        if ($blacklistMatch !== null) {
            return new FirewallDecision(
                allowed: false,
                action: $this->isDryRun()
                    ? FirewallDecision::ACTION_DRY_RUN_BLOCKED
                    : FirewallDecision::ACTION_BLOCKED,
                reason: $this->formatReason('Blocked by blacklist rule', $blacklistMatch),
                matchedRule: $blacklistMatch,
                matchedGroup: $blacklistMatch['group'] ?? null,
            );
        }

        if ($mode === 'whitelist_only') {
            if ($whitelistMatch !== null) {
                return new FirewallDecision(
                    allowed: true,
                    action: FirewallDecision::ACTION_ALLOWED,
                    reason: $this->formatReason('Allowed by whitelist rule', $whitelistMatch),
                    matchedRule: $whitelistMatch,
                    matchedGroup: $whitelistMatch['group'] ?? null,
                );
            }

            return new FirewallDecision(
                allowed: false,
                action: $this->isDryRun()
                    ? FirewallDecision::ACTION_DRY_RUN_BLOCKED
                    : FirewallDecision::ACTION_BLOCKED,
                reason: 'Blocked: whitelist_only mode is active and IP is not whitelisted.',
            );
        }

        return new FirewallDecision(
            allowed: true,
            action: FirewallDecision::ACTION_ALLOWED,
            reason: 'No blocking rules matched.',
        );
    }

    public function allow(
        string $value,
        ?string $label = null,
        ?int $groupId = null,
        ?Carbon $expiresAt = null,
        ?string $createdBy = null,
    ): FirewallRule {
        return $this->createRule(FirewallRule::TYPE_WHITELIST, $value, $label, $groupId, $expiresAt, $createdBy);
    }

    public function block(
        string $value,
        ?string $label = null,
        ?int $groupId = null,
        ?Carbon $expiresAt = null,
        ?string $createdBy = null,
    ): FirewallRule {
        return $this->createRule(FirewallRule::TYPE_BLACKLIST, $value, $label, $groupId, $expiresAt, $createdBy);
    }

    public function unblock(string $value): int
    {
        $value = trim($value);
        $count = 0;

        $rules = FirewallRule::query()
            ->where('type', FirewallRule::TYPE_BLACKLIST)
            ->where('is_active', true)
            ->get();

        foreach ($rules as $rule) {
            if ($rule->value === $value || (IpMatcher::isValid($value, IpMatcher::TYPE_IP) && IpMatcher::matches($value, $rule->value, $rule->rule_type))) {
                $rule->is_active = false;
                $rule->save();
                $count++;
            }
        }

        $this->clearCache();

        return $count;
    }

    public function logDecision(Request $request, FirewallDecision $decision): void
    {
        if (! (bool) config('firewall-gui.logging.enabled', true)) {
            return;
        }

        FirewallLog::query()->create([
            'ip_address' => (string) $request->ip(),
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'user_agent' => $request->userAgent(),
            'action' => $decision->action,
            'matched_rule_id' => $decision->matchedRule['id'] ?? null,
            'matched_group_id' => $decision->matchedGroup['id'] ?? null,
            'reason' => $decision->reason,
            'created_at' => now(),
        ]);
    }

    public function clearCache(): void
    {
        $this->cacheStore()->forget($this->cacheKey());
    }

    /**
     * @return array{whitelist: array<int, array<string, mixed>>, blacklist: array<int, array<string, mixed>>}
     */
    public function getCachedRules(): array
    {
        $ttl = (int) config('firewall-gui.cache.ttl', 86400);

        return $this->cacheStore()->remember($this->cacheKey(), $ttl, function (): array {
            $rules = FirewallRule::query()
                ->with('group')
                ->active()
                ->get()
                ->filter(function (FirewallRule $rule): bool {
                    if ($rule->group !== null && ! $rule->group->is_active) {
                        return false;
                    }

                    return true;
                });

            $payload = [
                FirewallRule::TYPE_WHITELIST => [],
                FirewallRule::TYPE_BLACKLIST => [],
            ];

            foreach ($rules as $rule) {
                $effectiveType = $rule->group?->type ?? $rule->type;

                $payload[$effectiveType][] = [
                    'id' => $rule->id,
                    'type' => $effectiveType,
                    'rule_type' => $rule->rule_type,
                    'value' => $rule->value,
                    'label' => $rule->label,
                    'expires_at' => $rule->expires_at?->toIso8601String(),
                    'group' => $rule->group ? [
                        'id' => $rule->group->id,
                        'name' => $rule->group->name,
                        'slug' => $rule->group->slug,
                        'type' => $rule->group->type,
                    ] : null,
                ];
            }

            return $payload;
        });
    }

    protected function createRule(
        string $type,
        string $value,
        ?string $label,
        ?int $groupId,
        ?Carbon $expiresAt,
        ?string $createdBy,
    ): FirewallRule {
        [$ruleType, $normalized] = IpMatcher::normalizeOrFail($value);

        if ($groupId !== null) {
            $group = FirewallGroup::query()->findOrFail($groupId);
            $type = $group->type;
        }

        $rule = FirewallRule::query()->create([
            'group_id' => $groupId,
            'type' => $type,
            'rule_type' => $ruleType,
            'value' => $normalized,
            'label' => $label,
            'is_active' => true,
            'expires_at' => $expiresAt,
            'created_by' => $createdBy,
        ]);

        $this->clearCache();

        return $rule;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rules
     * @return array<string, mixed>|null
     */
    protected function findMatchingRule(string $ip, array $rules): ?array
    {
        $now = now();

        foreach ($rules as $rule) {
            if (! empty($rule['expires_at']) && Carbon::parse($rule['expires_at'])->lte($now)) {
                continue;
            }

            if (IpMatcher::matches($ip, $rule['value'], $rule['rule_type'])) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    protected function formatReason(string $prefix, array $rule): string
    {
        $groupName = $rule['group']['name'] ?? null;
        $label = $rule['label'] ?? null;

        $details = $rule['value'];
        if ($label) {
            $details .= " ({$label})";
        }
        if ($groupName) {
            $details .= " [Group: {$groupName}]";
        }

        return "{$prefix}: {$details}";
    }

    protected function cacheStore(): CacheRepository
    {
        $store = config('firewall-gui.cache.store');

        return $this->cache->store($store ?: null);
    }

    protected function cacheKey(): string
    {
        return (string) config('firewall-gui.cache.key', 'firewall_gui.rules_payload');
    }
}
