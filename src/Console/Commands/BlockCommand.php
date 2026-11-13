<?php

namespace FirewallGui\LaravelFirewallGui\Console\Commands;

use FirewallGui\LaravelFirewallGui\Console\Commands\Concerns\ResolvesRuleOptions;
use FirewallGui\LaravelFirewallGui\Services\FirewallManager;
use Illuminate\Console\Command;
use InvalidArgumentException;

class BlockCommand extends Command
{
    use ResolvesRuleOptions;

    protected $signature = 'firewall:block {value : IP, CIDR, or IP range to block}
                            {--label= : Optional human-readable label}
                            {--group= : Optional group slug or ID}
                            {--expires= : Optional expiration date/time}';

    protected $description = 'Add an IP, CIDR, or IP range to the firewall blacklist';

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
            $rule = $firewall->block(
                value: (string) $this->argument('value'),
                label: $label,
                groupId: $groupId,
                expiresAt: $expiresAt,
                createdBy: 'artisan',
            );
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Blocked [{$rule->value}] ({$rule->rule_type}) as rule #{$rule->id}.");

        return self::SUCCESS;
    }
}
