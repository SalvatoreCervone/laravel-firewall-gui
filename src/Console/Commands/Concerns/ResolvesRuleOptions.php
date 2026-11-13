<?php

namespace FirewallGui\LaravelFirewallGui\Console\Commands\Concerns;

use FirewallGui\LaravelFirewallGui\Models\FirewallGroup;
use Illuminate\Support\Carbon;
use Throwable;

trait ResolvesRuleOptions
{
    protected function resolveGroupId(mixed $groupOption): ?int
    {
        if ($groupOption === null || $groupOption === '') {
            return null;
        }

        $value = (string) $groupOption;

        $group = FirewallGroup::query()
            ->where('slug', $value)
            ->when(is_numeric($value), fn ($query) => $query->orWhere('id', (int) $value))
            ->first();

        if ($group === null) {
            $this->error("Firewall group [{$value}] not found.");

            return -1;
        }

        return $group->id;
    }

    /**
     * @return array{0: bool, 1: ?Carbon}
     */
    protected function resolveExpiresAt(mixed $expiresOption): array
    {
        if ($expiresOption === null || $expiresOption === '') {
            return [true, null];
        }

        try {
            return [true, Carbon::parse((string) $expiresOption)];
        } catch (Throwable) {
            $this->error("Invalid expiration date/time: [{$expiresOption}].");

            return [false, null];
        }
    }
}
