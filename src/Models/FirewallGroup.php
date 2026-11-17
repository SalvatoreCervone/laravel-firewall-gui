<?php

namespace FirewallGui\LaravelFirewallGui\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $type
 * @property string|null $description
 * @property bool $is_active
 * @property bool $notify_on_hit
 * @property int|null $notification_cooldown_hours
 * @property int|null $notification_cooldown_minutes
 * @property int $priority
 */
class FirewallGroup extends Model
{
    protected $table = 'firewall_groups';

    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
        'is_active',
        'notify_on_hit',
        'notification_cooldown_hours',
        'notification_cooldown_minutes',
        'priority',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'notify_on_hit' => 'boolean',
        'notification_cooldown_hours' => 'integer',
        'notification_cooldown_minutes' => 'integer',
        'priority' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $group): void {
            if (empty($group->slug)) {
                $group->slug = Str::slug($group->name);
            }
        });
    }

    public function rules(): HasMany
    {
        return $this->hasMany(FirewallRule::class, 'group_id');
    }
}
