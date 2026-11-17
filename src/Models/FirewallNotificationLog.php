<?php

namespace FirewallGui\LaravelFirewallGui\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ip_address
 * @property string $decision_action
 * @property string $recipients
 * @property string|null $subject
 * @property string $status
 * @property string|null $error_message
 * @property Carbon|null $sent_at
 * @property int|null $matched_rule_id
 * @property int|null $matched_group_id
 * @property Carbon $created_at
 */
class FirewallNotificationLog extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    public $timestamps = false;

    protected $table = 'firewall_notification_logs';

    protected $fillable = [
        'ip_address',
        'decision_action',
        'recipients',
        'subject',
        'status',
        'error_message',
        'sent_at',
        'matched_rule_id',
        'matched_group_id',
        'created_at',
    ];

    protected $casts = [
        'matched_rule_id' => 'integer',
        'matched_group_id' => 'integer',
        'sent_at' => 'datetime',
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
