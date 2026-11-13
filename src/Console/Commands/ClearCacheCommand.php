<?php

namespace FirewallGui\LaravelFirewallGui\Console\Commands;

use FirewallGui\LaravelFirewallGui\Services\FirewallManager;
use Illuminate\Console\Command;

class ClearCacheCommand extends Command
{
    protected $signature = 'firewall:clear-cache';

    protected $description = 'Clear the cached firewall rules payload';

    public function handle(FirewallManager $firewall): int
    {
        $firewall->clearCache();

        $this->info('Firewall rules cache cleared.');

        return self::SUCCESS;
    }
}
