<?php

namespace FirewallGui\LaravelFirewallGui\Support;

readonly class FirewallDecision
{
    public const ACTION_ALLOWED = 'allowed';
    public const ACTION_BLOCKED = 'blocked';
    public const ACTION_DRY_RUN_BLOCKED = 'dry_run_blocked';

    /**
     * @param  array<string, mixed>|null  $matchedRule
     * @param  array<string, mixed>|null  $matchedGroup
     */
    public function __construct(
        public bool $allowed,
        public string $action,
        public string $reason,
        public ?array $matchedRule = null,
        public ?array $matchedGroup = null,
    ) {}

    public function isBlocked(): bool
    {
        return ! $this->allowed;
    }
}
