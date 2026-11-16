<?php

namespace FirewallGui\LaravelFirewallGui\Support;

readonly class FirewallDecision
{
    public const ACTION_ALLOWED = 'allowed';
    public const ACTION_BLOCKED = 'blocked';
    public const ACTION_DRY_RUN_BLOCKED = 'dry_run_blocked';
    public const ACTION_NOTIFIED = 'notified';

    /**
     * @param  array<string, mixed>|null  $matchedRule
     * @param  array<string, mixed>|null  $matchedGroup
     * @param  array<string, mixed>|null  $notifyRule
     * @param  array<string, mixed>|null  $notifyGroup
     */
    public function __construct(
        public bool $allowed,
        public string $action,
        public string $reason,
        public ?array $matchedRule = null,
        public ?array $matchedGroup = null,
        public ?array $notifyRule = null,
        public ?array $notifyGroup = null,
        public bool $isDefaultFallback = false,
        public bool $notifyOnDefault = false,
    ) {}

    public function isBlocked(): bool
    {
        return ! $this->allowed;
    }

    public function shouldNotify(): bool
    {
        return $this->notifyRule !== null || $this->notifyOnDefault;
    }
}
