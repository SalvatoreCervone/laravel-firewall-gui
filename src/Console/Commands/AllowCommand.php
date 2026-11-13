<?php

namespace FirewallGui\LaravelFirewallGui\Console\Commands;

use FirewallGui\LaravelFirewallGui\Console\Commands\Concerns\ResolvesRuleOptions;
use FirewallGui\LaravelFirewallGui\Services\FirewallManager;
use Illuminate\Console\Command;
use InvalidArgumentException;

class AllowCommand extends Command
{
    use ResolvesRuleOptions;

    protected $signature = 'firewall:allow {value : IP, CIDR, or IP range to allow}
                            {--label= : Optional human-readable label}
                            {--group= : Optional group slug or ID}
                            {--expires= : Optional expiration date/time}';

    protected $description = 'Add an IP, CIDR, or IP range to the firewall whitelist';

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
            $rule = $firewall->allow(
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

        $this->info("Allowed [{$rule->value}] ({$rule->rule_type}) as rule #{$rule->id}.");

        return self::SUCCESS;
    }
}
