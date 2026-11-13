<?php

namespace FirewallGui\LaravelFirewallGui\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ip_address
 * @property string $method
 * @property string $path
 * @property string|null $user_agent
 * @property string $action
 * @property int|null $matched_rule_id
 * @property int|null $matched_group_id
 * @property string|null $reason
 * @property Carbon $created_at
 */
class FirewallLog extends Model
{
    public $timestamps = false;

    protected $table = 'firewall_logs';

    protected $fillable = [
        'ip_address',
        'method',
        'path',
        'user_agent',
        'action',
        'matched_rule_id',
        'matched_group_id',
        'reason',
        'created_at',
    ];

    protected $casts = [
        'matched_rule_id' => 'integer',
        'matched_group_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(FirewallRule::class, 'matched_rule_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(FirewallGroup::class, 'matched_group_id');
    }
}
