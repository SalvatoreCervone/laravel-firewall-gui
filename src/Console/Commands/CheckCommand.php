<?php

namespace FirewallGui\LaravelFirewallGui\Console\Commands;

use FirewallGui\LaravelFirewallGui\Services\FirewallManager;
use FirewallGui\LaravelFirewallGui\Support\IpMatcher;
use Illuminate\Console\Command;

class CheckCommand extends Command
{
    protected $signature = 'firewall:check {ip : IP address to evaluate against firewall rules}';

    protected $description = 'Evaluate an IP address against the current firewall rules';

    public function handle(FirewallManager $firewall): int
    {
        $ip = trim((string) $this->argument('ip'));

        if (! IpMatcher::isValid($ip, IpMatcher::TYPE_IP)) {
            $this->error("Invalid IP address: [{$ip}].");

            return self::FAILURE;
        }

        $decision = $firewall->check($ip);

        $this->table(
            ['Field', 'Value'],
            [
                ['IP Address', $ip],
                ['Allowed', $decision->allowed ? 'Yes' : 'No'],
                ['Action', $decision->action],
                ['Reason', $decision->reason],
                ['Matched Rule', $decision->matchedRule !== null ? "#{$decision->matchedRule['id']} ({$decision->matchedRule['value']})" : 'None'],
                ['Matched Group', $decision->matchedGroup !== null ? "{$decision->matchedGroup['name']} ({$decision->matchedGroup['slug']})" : 'None'],
            ]
        );

        return self::SUCCESS;
    }
}
