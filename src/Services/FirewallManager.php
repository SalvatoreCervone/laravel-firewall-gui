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
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
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
        $engine = $this->getEngineSettings();

        if ($engine['dry_run'] !== null) {
            return $engine['dry_run'];
        }

        return (bool) config('firewall-gui.dry_run', false);
    }

    public function mode(): string
    {
        $engine = $this->getEngineSettings();

        if ($engine['mode'] !== null) {
            return $engine['mode'];
        }

        return (string) config('firewall-gui.mode', 'blacklist_only');
    }

    /**
     * @return array{mode: ?string, dry_run: ?bool}
     */
    public function getEngineSettings(): array
    {
        $ttl = (int) config('firewall-gui.cache.ttl', 86400);

        return $this->cacheStore()->remember($this->engineSettingsCacheKey(), $ttl, function (): array {
            $result = [
                'mode' => null,
                'dry_run' => null,
            ];

            try {
                $rows = FirewallSetting::query()
                    ->whereIn('key', ['firewall.mode', 'firewall.dry_run'])
                    ->pluck('value', 'key');
            } catch (\Throwable) {
                return $result;
            }

            if ($rows->has('firewall.mode') && in_array($rows->get('firewall.mode'), ['blacklist_only', 'whitelist_only'], true)) {
                $result['mode'] = (string) $rows->get('firewall.mode');
            }

            if ($rows->has('firewall.dry_run')) {
                $result['dry_run'] = $rows->get('firewall.dry_run') === '1';
            }

            return $result;
        });
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
        $isUnmatchedBlock = $decision->isBlocked()
            && $decision->matchedRule === null
            && (bool) ($settings['on_unmatched_block'] ?? config('firewall-gui.notifications.on_unmatched_block', true));

        if (! $decision->shouldNotify() && ! $isUnmatchedBlock) {
            return false;
        }

        $ip = (string) $request->ip();
        $notifyRule = $decision->notifyRule ?? [
            'id' => 0,
            'value' => $ip,
            'label' => 'Not in Whitelist (Default Block)',
        ];
        $ruleId = (int) ($notifyRule['id'] ?? 0);

        $emailQueued = false;
        $groupCooldown = $decision->notifyGroup['notification_cooldown_hours'] ?? null;
        $cooldownHours = max(1, (int) ($groupCooldown ?: ($settings['cooldown_hours'] ?? config('firewall-gui.notifications.cooldown_hours', 24))));

        if ($settings['enabled']) {
            $groupId = isset($decision->notifyGroup['id']) ? (int) $decision->notifyGroup['id'] : null;
            $recipients = $this->resolveRecipientsForGroup($groupId, $settings);

            if ($recipients !== []) {
                $cooldownKey = sprintf('firewall_gui.notified.%s', md5($ip.'|'.$ruleId));

                if ($this->cacheStore()->add($cooldownKey, true, now()->addHours($cooldownHours))) {
                    $mailable = new FirewallAccessNotification(
                        ipAddress: $ip,
                        method: $request->method(),
                        path: '/'.ltrim($request->path(), '/'),
                        userAgent: $request->userAgent(),
                        accessedAt: now()->toDateTimeString(),
                        matchedRule: $notifyRule,
                        matchedGroup: $decision->notifyGroup,
                        decisionAction: $decision->action,
                    );

                    $this->queueNotificationMailable($recipients, $mailable);
                    $emailQueued = true;
                }
            }
        }

        if ($decision->allowed && $settings['log_access'] && (bool) config('firewall-gui.logging.enabled', true)) {
            FirewallLog::query()->create([
                'ip_address' => $ip,
                'method' => $request->method(),
                'path' => '/'.ltrim($request->path(), '/'),
                'user_agent' => $request->userAgent(),
                'action' => FirewallDecision::ACTION_NOTIFIED,
                'matched_rule_id' => $notifyRule['id'] ?? null,
                'matched_group_id' => $decision->notifyGroup['id'] ?? null,
                'reason' => $this->formatReason(
                    $emailQueued ? 'Access notification queued' : 'Notify rule access (notification cooldown active)',
                    $notifyRule
                ),
                'created_at' => now(),
            ]);
        }

        return $emailQueued;
    }

    /**
     * @return array{
     *     enabled: bool,
     *     to: string,
     *     recipients: array<int, array{email: string, group_ids: array<int, int>}>,
     *     queue: ?string,
     *     cooldown_hours: int,
     *     log_access: bool
     * }
     */
    public function getNotificationSettings(): array
    {
        $ttl = (int) config('firewall-gui.cache.ttl', 86400);

        $defaultTo = is_array($to = config('firewall-gui.notifications.to', ''))
            ? implode(', ', $to)
            : (string) $to;

        $defaults = [
            'enabled' => (bool) config('firewall-gui.notifications.enabled', true),
            'to' => $defaultTo,
            'recipients' => array_map(
                fn (string $email): array => ['email' => $email, 'group_ids' => []],
                $this->parseNotificationRecipients($defaultTo)
            ),
            'queue' => config('firewall-gui.notifications.queue') ?: null,
            'cooldown_hours' => max(1, (int) config('firewall-gui.notifications.cooldown_hours', 24)),
            'on_unmatched_block' => (bool) config('firewall-gui.notifications.on_unmatched_block', true),
            'log_access' => (bool) config('firewall-gui.notifications.log_access', true),
        ];

        $cached = $this->cacheStore()->remember($this->settingsCacheKey(), $ttl, function () use ($defaults): array {
            try {
                $rows = FirewallSetting::query()
                    ->whereIn('key', [
                        'notifications.enabled',
                        'notifications.to',
                        'notifications.recipients',
                        'notifications.queue',
                        'notifications.cooldown_hours',
                        'notifications.on_unmatched_block',
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
                $defaults['recipients'] = array_map(
                    fn (string $email): array => ['email' => $email, 'group_ids' => []],
                    $this->parseNotificationRecipients($defaults['to'])
                );
            }
            if ($rows->has('notifications.recipients')) {
                $decoded = json_decode((string) $rows->get('notifications.recipients'), true);
                if (is_array($decoded)) {
                    $defaults['recipients'] = array_values(array_filter(array_map(function ($item): ?array {
                        if (! is_array($item) || empty($item['email'])) {
                            return null;
                        }

                        return [
                            'email' => trim((string) $item['email']),
                            'group_ids' => array_values(array_unique(array_map('intval', (array) ($item['group_ids'] ?? [])))),
                        ];
                    }, $decoded)));
                }
            }
            if ($rows->has('notifications.queue')) {
                $defaults['queue'] = $rows->get('notifications.queue') ?: null;
            }
            if ($rows->has('notifications.cooldown_hours')) {
                $defaults['cooldown_hours'] = max(1, (int) $rows->get('notifications.cooldown_hours'));
            }
            if ($rows->has('notifications.on_unmatched_block')) {
                $defaults['on_unmatched_block'] = $rows->get('notifications.on_unmatched_block') === '1';
            }
            if ($rows->has('notifications.log_access')) {
                $defaults['log_access'] = $rows->get('notifications.log_access') === '1';
            }

            return $defaults;
        });

        return array_merge($defaults, $cached);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, string>
     */
    protected function resolveRecipientsForGroup(?int $groupId, array $settings): array
    {
        $recipients = $settings['recipients'] ?? [];

        if (! is_array($recipients) || $recipients === []) {
            return $this->parseNotificationRecipients($settings['to'] ?? '');
        }

        $matched = [];

        foreach ($recipients as $entry) {
            $email = trim((string) ($entry['email'] ?? ''));
            if ($email === '') {
                continue;
            }

            $groupIds = array_values(array_map('intval', (array) ($entry['group_ids'] ?? [])));

            if ($groupIds === [] || ($groupId !== null && in_array($groupId, $groupIds, true))) {
                $matched[] = $email;
            }
        }

        return array_values(array_unique($matched));
    }

    /**
     * @return array{
     *     mailer: ?string,
     *     host: ?string,
     *     port: ?int,
     *     encryption: ?string,
     *     username: ?string,
     *     password: ?string,
     *     has_password: bool,
     *     from_address: ?string,
     *     from_name: ?string
     * }
     */
    public function getMailSettings(): array
    {
        $ttl = (int) config('firewall-gui.cache.ttl', 86400);

        $settings = $this->cacheStore()->remember($this->mailSettingsCacheKey(), $ttl, function (): array {
            $defaults = [
                'mailer' => ($m = config('firewall-gui.mail.mailer')) && $m !== 'default' ? (string) $m : null,
                'host' => ($h = config('firewall-gui.mail.host')) ? (string) $h : null,
                'port' => ($p = config('firewall-gui.mail.port')) ? (int) $p : 587,
                'encryption' => ($e = config('firewall-gui.mail.encryption')) ? (string) $e : null,
                'username' => ($u = config('firewall-gui.mail.username')) ? (string) $u : null,
                'password_encrypted' => null,
                'password_plain' => ($pw = config('firewall-gui.mail.password')) ? (string) $pw : null,
                'from_address' => ($fa = config('firewall-gui.mail.from_address')) ? (string) $fa : null,
                'from_name' => ($fn = config('firewall-gui.mail.from_name')) ? (string) $fn : null,
            ];

            try {
                $rows = FirewallSetting::query()
                    ->whereIn('key', [
                        'mail.mailer',
                        'mail.host',
                        'mail.port',
                        'mail.encryption',
                        'mail.username',
                        'mail.password',
                        'mail.from_address',
                        'mail.from_name',
                    ])
                    ->pluck('value', 'key');
            } catch (\Throwable) {
                return $defaults;
            }

            if ($rows->has('mail.mailer')) {
                $mailer = $rows->get('mail.mailer');
                $defaults['mailer'] = ($mailer && $mailer !== 'default') ? (string) $mailer : null;
            }
            if ($rows->has('mail.host')) {
                $defaults['host'] = $rows->get('mail.host') ?: null;
            }
            if ($rows->has('mail.port')) {
                $defaults['port'] = $rows->get('mail.port') ? (int) $rows->get('mail.port') : null;
            }
            if ($rows->has('mail.encryption')) {
                $defaults['encryption'] = $rows->get('mail.encryption') ?: null;
            }
            if ($rows->has('mail.username')) {
                $defaults['username'] = $rows->get('mail.username') ?: null;
            }
            if ($rows->has('mail.password')) {
                $defaults['password_encrypted'] = $rows->get('mail.password') ?: null;
                $defaults['password_plain'] = null;
            }
            if ($rows->has('mail.from_address')) {
                $defaults['from_address'] = $rows->get('mail.from_address') ?: null;
            }
            if ($rows->has('mail.from_name')) {
                $defaults['from_name'] = $rows->get('mail.from_name') ?: null;
            }

            return $defaults;
        });

        $password = $settings['password_plain'] ?? null;
        if (! empty($settings['password_encrypted'])) {
            try {
                $password = Crypt::decryptString((string) $settings['password_encrypted']);
            } catch (DecryptException) {
                $password = null;
            }
        }

        return [
            'mailer' => $settings['mailer'],
            'host' => $settings['host'],
            'port' => $settings['port'],
            'encryption' => $settings['encryption'],
            'username' => $settings['username'],
            'password' => $password,
            'has_password' => $password !== null && $password !== '',
            'from_address' => $settings['from_address'],
            'from_name' => $settings['from_name'],
        ];
    }

    public function applyCustomMailerConfig(): ?string
    {
        $mail = $this->getMailSettings();

        if (empty($mail['mailer'])) {
            return null;
        }

        $mailerConfig = match ($mail['mailer']) {
            'smtp' => [
                'transport' => 'smtp',
                'scheme' => $mail['encryption'] === 'ssl' ? 'smtps' : ($mail['encryption'] === 'tls' ? 'smtp' : null),
                'host' => $mail['host'],
                'port' => $mail['port'] ?? 587,
                'encryption' => $mail['encryption'] ?: null,
                'username' => $mail['username'] ?: null,
                'password' => $mail['password'] ?: null,
                'timeout' => max(1, (int) config('firewall-gui.mail.timeout', 15)),
                'local_domain' => config('mail.mailers.smtp.local_domain'),
            ],
            'sendmail' => [
                'transport' => 'sendmail',
                'path' => config('mail.mailers.sendmail.path', '/usr/sbin/sendmail -bs -i'),
            ],
            'log' => [
                'transport' => 'log',
                'channel' => config('mail.mailers.log.channel'),
            ],
            default => null,
        };

        if ($mailerConfig === null) {
            return null;
        }

        config()->set('mail.mailers.firewall_gui', $mailerConfig);

        return 'firewall_gui';
    }

    public function refreshCustomMailer(): void
    {
        $this->applyCustomMailerConfig();

        if (app()->resolved('mail.manager')) {
            $manager = app('mail.manager');
            if (method_exists($manager, 'purge')) {
                $manager->purge('firewall_gui');
            }
        }
    }

    /**
     * @param  array<int, string>|string  $recipients
     */
    public function queueNotificationMailable(array|string $recipients, FirewallAccessNotification $mailable): void
    {
        $notificationSettings = $this->getNotificationSettings();
        $mailSettings = $this->getMailSettings();

        if (! empty($notificationSettings['queue'])) {
            $mailable->onQueue((string) $notificationSettings['queue']);
        }

        if (! empty($mailSettings['from_address'])) {
            $mailable->fromAddress = $mailSettings['from_address'];
            $mailable->fromName = $mailSettings['from_name'] ?: null;
        }

        $customMailer = $this->applyCustomMailerConfig();

        if ($customMailer !== null) {
            Mail::mailer($customMailer)->to($recipients)->queue($mailable);

            return;
        }

        Mail::to($recipients)->queue($mailable);
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

    public function getGuiLocale(): ?string
    {
        $ttl = (int) config('firewall-gui.cache.ttl', 86400);

        $locale = $this->cacheStore()->remember($this->guiLocaleCacheKey(), $ttl, function (): string {
            $default = (string) (config('firewall-gui.gui.locale') ?: 'auto');

            try {
                $value = FirewallSetting::query()
                    ->where('key', 'gui.locale')
                    ->value('value');
            } catch (\Throwable) {
                return $default;
            }

            return $value ? (string) $value : $default;
        });

        return in_array($locale, ['en', 'it'], true) ? $locale : null;
    }

    public function clearCache(): void
    {
        $this->cacheStore()->forget($this->cacheKey());
        $this->cacheStore()->forget($this->settingsCacheKey());
        $this->cacheStore()->forget($this->mailSettingsCacheKey());
        $this->cacheStore()->forget($this->guiLocaleCacheKey());
        $this->cacheStore()->forget($this->engineSettingsCacheKey());

        if (app()->resolved('mail.manager')) {
            $manager = app('mail.manager');
            if (method_exists($manager, 'purge')) {
                $manager->purge('firewall_gui');
            }
        }
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
                        'notification_cooldown_hours' => $rule->group->notification_cooldown_hours,
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

    protected function mailSettingsCacheKey(): string
    {
        return $this->cacheKey().'.mail_settings';
    }

    protected function guiLocaleCacheKey(): string
    {
        return $this->cacheKey().'.gui_locale';
    }

    protected function engineSettingsCacheKey(): string
    {
        return $this->cacheKey().'.engine_settings';
    }
}
