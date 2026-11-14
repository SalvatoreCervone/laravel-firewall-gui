<?php

namespace FirewallGui\LaravelFirewallGui\Console\Commands;

use FirewallGui\LaravelFirewallGui\Console\Commands\Concerns\ResolvesRuleOptions;
use FirewallGui\LaravelFirewallGui\Services\FirewallManager;
use Illuminate\Console\Command;
use Throwable;

class NotifyCommand extends Command
{
    use ResolvesRuleOptions;

    protected $signature = 'firewall:notify
        {value : Single IP, CIDR block, or IP range}
        {--label= : Optional human-readable label}
        {--group= : Optional group ID or slug}
        {--expires= : Optional expiration time (e.g. "+24 hours" or "2026-12-31 23:59:59")}';

    protected $description = 'Add a notify rule to allow access and queue a daily email alert on first access';

    public function handle(FirewallManager $firewall): int
    {
        $groupId = $this->resolveGroupId($this->option('group'));
        if ($groupId === -1) {
            return self::FAILURE;
        }

        [$validExpires, $expiresAt] = $this->resolveExpiresAt($this->option('expires'));
        if (! $validExpires) {
            return self::FAILURE;
        }

        $label = $this->option('label');
        $label = is_string($label) && $label !== '' ? $label : null;

        try {
            $rule = $firewall->notify(
                value: (string) $this->argument('value'),
                label: $label,
                groupId: $groupId,
                expiresAt: $expiresAt,
                createdBy: 'artisan',
            );

            $this->info("Notify rule #{$rule->id} created for [{$rule->value}] ({$rule->rule_type}).");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
