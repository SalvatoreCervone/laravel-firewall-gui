<?php

namespace FirewallGui\LaravelFirewallGui\Models;

use FirewallGui\LaravelFirewallGui\Support\IpMatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $group_id
 * @property string $type
 * @property string $rule_type
 * @property string $value
 * @property string|null $label
 * @property bool $is_active
 * @property Carbon|null $expires_at
 * @property string|null $created_by
 */
class FirewallRule extends Model
{
    public const TYPE_WHITELIST = 'whitelist';
    public const TYPE_BLACKLIST = 'blacklist';
    public const TYPE_NOTIFY = 'notify';

    protected $table = 'firewall_rules';

    protected $fillable = [
        'group_id',
        'type',
        'rule_type',
        'value',
        'label',
        'is_active',
        'notify_on_hit',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'group_id' => 'integer',
        'is_active' => 'boolean',
        'notify_on_hit' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(FirewallGroup::class, 'group_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * @return array{type: string, first_ip: string, last_ip: string, total_ips: string, summary: string}|null
     */
    public function describeSpan(): ?array
    {
        return IpMatcher::describe($this->value, $this->rule_type);
    }
}
