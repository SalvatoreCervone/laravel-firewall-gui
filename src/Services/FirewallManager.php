<?php

namespace FirewallGui\LaravelFirewallGui\Services;

use Closure;
use FirewallGui\LaravelFirewallGui\Mail\FirewallAccessNotification;
use FirewallGui\LaravelFirewallGui\Models\FirewallGroup;
use FirewallGui\LaravelFirewallGui\Models\FirewallLog;
use FirewallGui\LaravelFirewallGui\Models\FirewallRule;
use FirewallGui\LaravelFirewallGui\Models\FirewallSetting;
use FirewallGui\LaravelFirewallGui\Support\FirewallDecision;
use FirewallGui\LaravelFirewallGui\Support\IpMatcher;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

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
        $notifyMatch = $this->findMatchingRule($ip, $payload['notify'] ?? []);

        if ($whitelistOverrides && $whitelistMatch !== null) {
            $activeNotify = $notifyMatch ?? (! empty($whitelistMatch['notify_on_hit']) ? $whitelistMatch : null);

            return new FirewallDecision(
                allowed: true,
                action: FirewallDecision::ACTION_ALLOWED,
                reason: $this->formatReason('Allowed by whitelist rule', $whitelistMatch),
                matchedRule: $whitelistMatch,
                matchedGroup: $whitelistMatch['group'] ?? null,
                notifyRule: $activeNotify,
                notifyGroup: $activeNotify['group'] ?? null,
            );
        }

        if ($blacklistMatch !== null) {
            $blockNotify = ! empty($blacklistMatch['notify_on_hit']) ? $blacklistMatch : null;

            return new FirewallDecision(
                allowed: false,
                action: $this->isDryRun()
                    ? FirewallDecision::ACTION_DRY_RUN_BLOCKED
                    : FirewallDecision::ACTION_BLOCKED,
                reason: $this->formatReason('Blocked by blacklist rule', $blacklistMatch),
                matchedRule: $blacklistMatch,
                matchedGroup: $blacklistMatch['group'] ?? null,
                notifyRule: $blockNotify,
                notifyGroup: $blockNotify['group'] ?? null,
            );
        }

        if ($mode === 'whitelist_only') {
            if ($whitelistMatch !== null) {
                $activeNotify = $notifyMatch ?? (! empty($whitelistMatch['notify_on_hit']) ? $whitelistMatch : null);

                return new FirewallDecision(
                    allowed: true,
                    action: FirewallDecision::ACTION_ALLOWED,
                    reason: $this->formatReason('Allowed by whitelist rule', $whitelistMatch),
                    matchedRule: $whitelistMatch,
                    matchedGroup: $whitelistMatch['group'] ?? null,
                    notifyRule: $activeNotify,
                    notifyGroup: $activeNotify['group'] ?? null,
                );
            }

            if ($notifyMatch !== null) {
                return new FirewallDecision(
                    allowed: true,
                    action: FirewallDecision::ACTION_ALLOWED,
                    reason: $this->formatReason('Allowed by notify rule', $notifyMatch),
                    matchedRule: $notifyMatch,
                    matchedGroup: $notifyMatch['group'] ?? null,
                    notifyRule: $notifyMatch,
                    notifyGroup: $notifyMatch['group'] ?? null,
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
            reason: $notifyMatch !== null
                ? $this->formatReason('Allowed with access notification', $notifyMatch)
                : 'No blocking rules matched.',
            matchedRule: $notifyMatch,
            matchedGroup: $notifyMatch['group'] ?? null,
            notifyRule: $notifyMatch,
            notifyGroup: $notifyMatch['group'] ?? null,
        );
    }

    public function allow(
        string $value,
        ?string $label = null,
        ?int $groupId = null,
        ?Carbon $expiresAt = null,
        ?string $createdBy = null,
        bool $notifyOnHit = false,
    ): FirewallRule {
        return $this->createRule(FirewallRule::TYPE_WHITELIST, $value, $label, $groupId, $expiresAt, $createdBy, $notifyOnHit);
    }

    public function block(
        string $value,
        ?string $label = null,
        ?int $groupId = null,
        ?Carbon $expiresAt = null,
        ?string $createdBy = null,
        bool $notifyOnHit = false,
    ): FirewallRule {
        return $this->createRule(FirewallRule::TYPE_BLACKLIST, $value, $label, $groupId, $expiresAt, $createdBy, $notifyOnHit);
    }

    public function notify(
        string $value,
        ?string $label = null,
        ?int $groupId = null,
        ?Carbon $expiresAt = null,
        ?string $createdBy = null,
    ): FirewallRule {
        return $this->createRule(FirewallRule::TYPE_NOTIFY, $value, $label, $groupId, $expiresAt, $createdBy, true);
    }

    public function dispatchDailyNotificationIfNeeded(Request $request, FirewallDecision $decision): bool
    {
        $settings = $this->getNotificationSettings();

        if (! $decision->shouldNotify() || ! $settings['enabled']) {
            return false;
        }

        $recipients = $this->parseNotificationRecipients($settings['to']);
        if ($recipients === []) {
            return false;
        }

        $ip = (string) $request->ip();
        $ruleId = (int) ($decision->notifyRule['id'] ?? 0);
        $dailyKey = sprintf('firewall_gui.notified.%s.%s', now()->format('Y-m-d'), md5($ip.'|'.$ruleId));

        if (! $this->cacheStore()->add($dailyKey, true, now()->endOfDay())) {
            return false;
        }

        $mailable = new FirewallAccessNotification(
            ipAddress: $ip,
            method: $request->method(),
            path: '/'.ltrim($request->path(), '/'),
            userAgent: $request->userAgent(),
            accessedAt: now()->toDateTimeString(),
            matchedRule: $decision->notifyRule,
            matchedGroup: $decision->notifyGroup,
            decisionAction: $decision->action,
        );

        $queue = $settings['queue'];
        if (is_string($queue) && $queue !== '') {
            $mailable->onQueue($queue);
        }

        Mail::to($recipients)->queue($mailable);

        if ($decision->allowed && $settings['log_access'] && (bool) config('firewall-gui.logging.enabled', true)) {
            FirewallLog::query()->create([
                'ip_address' => $ip,
                'method' => $request->method(),
                'path' => '/'.ltrim($request->path(), '/'),
                'user_agent' => $request->userAgent(),
                'action' => FirewallDecision::ACTION_NOTIFIED,
                'matched_rule_id' => $decision->notifyRule['id'] ?? null,
                'matched_group_id' => $decision->notifyGroup['id'] ?? null,
                'reason' => $this->formatReason('First daily access notification queued', $decision->notifyRule),
                'created_at' => now(),
            ]);
        }

        return true;
    }

    /**
     * @return array{enabled: bool, to: string, queue: ?string, log_access: bool}
     */
    public function getNotificationSettings(): array
    {
        $ttl = (int) config('firewall-gui.cache.ttl', 86400);

        return $this->cacheStore()->remember($this->settingsCacheKey(), $ttl, function (): array {
            $defaults = [
                'enabled' => (bool) config('firewall-gui.notifications.enabled', true),
                'to' => is_array($to = config('firewall-gui.notifications.to', ''))
                    ? implode(', ', $to)
                    : (string) $to,
                'queue' => config('firewall-gui.notifications.queue') ?: null,
                'log_access' => (bool) config('firewall-gui.notifications.log_access', true),
            ];

            try {
                $rows = FirewallSetting::query()
                    ->whereIn('key', [
                        'notifications.enabled',
                        'notifications.to',
                        'notifications.queue',
                        'notifications.log_access',
                    ])
                    ->pluck('value', 'key');
            } catch (\Throwable) {
                return $defaults;
            }

            if ($rows->has('notifications.enabled')) {
                $defaults['enabled'] = $rows->get('notifications.enabled') === '1';
            }
            if ($rows->has('notifications.to')) {
                $defaults['to'] = (string) $rows->get('notifications.to');
            }
            if ($rows->has('notifications.queue')) {
                $defaults['queue'] = $rows->get('notifications.queue') ?: null;
            }
            if ($rows->has('notifications.log_access')) {
                $defaults['log_access'] = $rows->get('notifications.log_access') === '1';
            }

            return $defaults;
        });
    }

    /**
     * @param  array<int, string>|string|null  $raw
     * @return array<int, string>
     */
    protected function parseNotificationRecipients(array|string|null $raw = null): array
    {
        $raw ??= config('firewall-gui.notifications.to', []);

        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }

        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', $raw), fn (string $email): bool => $email !== ''));
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
        $this->cacheStore()->forget($this->settingsCacheKey());
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
                FirewallRule::TYPE_NOTIFY => [],
            ];

            foreach ($rules as $rule) {
                $effectiveType = $rule->group?->type ?? $rule->type;

                $payload[$effectiveType][] = [
                    'id' => $rule->id,
                    'type' => $effectiveType,
                    'rule_type' => $rule->rule_type,
                    'value' => $rule->value,
                    'label' => $rule->label,
                    'notify_on_hit' => $effectiveType === FirewallRule::TYPE_NOTIFY
                        || $rule->notify_on_hit
                        || ($rule->group?->notify_on_hit ?? false),
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
        bool $notifyOnHit = false,
    ): FirewallRule {
        [$ruleType, $normalized] = IpMatcher::normalizeOrFail($value);

        if ($groupId !== null) {
            $group = FirewallGroup::query()->findOrFail($groupId);
            $type = $group->type;
            $notifyOnHit = $notifyOnHit || $group->notify_on_hit;
        }

        $rule = FirewallRule::query()->create([
            'group_id' => $groupId,
            'type' => $type,
            'rule_type' => $ruleType,
            'value' => $normalized,
            'label' => $label,
            'is_active' => true,
            'notify_on_hit' => $type === FirewallRule::TYPE_NOTIFY ? true : $notifyOnHit,
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

    protected function settingsCacheKey(): string
    {
        return $this->cacheKey().'.settings';
    }
}
