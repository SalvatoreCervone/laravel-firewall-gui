<?php

namespace FirewallGui\LaravelFirewallGui\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 */
class FirewallSetting extends Model
{
    protected $table = 'firewall_settings';

    protected $fillable = [
        'key',
        'value',
    ];
}
