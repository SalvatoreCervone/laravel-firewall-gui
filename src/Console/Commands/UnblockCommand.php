<?php

namespace FirewallGui\LaravelFirewallGui\Console\Commands;

use FirewallGui\LaravelFirewallGui\Services\FirewallManager;
use Illuminate\Console\Command;

class UnblockCommand extends Command
{
    protected $signature = 'firewall:unblock {value : IP, CIDR, or IP range to unblock}';

    protected $description = 'Deactivate matching active blacklist rules for the given IP or rule value';

    public function handle(FirewallManager $firewall): int
    {
        $value = (string) $this->argument('value');
        $count = $firewall->unblock($value);

        if ($count === 0) {
            $this->warn("No active blacklist rules matched [{$value}].");

            return self::SUCCESS;
        }

        $this->info("Deactivated {$count} active blacklist rule(s) for [{$value}].");

        return self::SUCCESS;
    }
}
