<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firewall Master Switch
    |--------------------------------------------------------------------------
    |
    | Enable or disable the firewall middleware globally.
    |
    */
    'enabled' => env('FIREWALL_GUI_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Evaluation Mode
    |--------------------------------------------------------------------------
    |
    | Supported modes:
    | - "blacklist_only": Allow all IPs except those matching blacklist rules.
    |                     Explicit whitelist rules override blacklist rules.
    | - "whitelist_only": Block all IPs except those matching whitelist rules.
    |
    */
    'mode' => env('FIREWALL_GUI_MODE', 'blacklist_only'),

    /*
    |--------------------------------------------------------------------------
    | Whitelist Priority
    |--------------------------------------------------------------------------
    |
    | When true, an explicit whitelist match always allows the IP even if a
    | broader blacklist rule (such as a CIDR block) also matches.
    |
    */
    'whitelist_overrides_blacklist' => true,

    /*
    |--------------------------------------------------------------------------
    | Dry Run Mode
    |--------------------------------------------------------------------------
    |
    | When true, requests that would be blocked are logged as "dry_run_blocked"
    | and allowed through. Useful for testing new rules safely in production.
    |
    */
    'dry_run' => env('FIREWALL_GUI_DRY_RUN', false),

    /*
    |--------------------------------------------------------------------------
    | Always Bypassed IPs
    |--------------------------------------------------------------------------
    |
    | IPs or CIDR ranges listed here will always bypass the firewall.
    |
    */
    'bypass_ips' => [
        // '127.0.0.1',
        // '::1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Active rules and groups are cached for fast middleware evaluation and
    | automatically invalidated whenever rules or groups are modified.
    |
    */
    'cache' => [
        'store' => env('FIREWALL_GUI_CACHE_STORE', null),
        'key' => 'firewall_gui.rules_payload',
        'ttl' => 86400, // 24 hours in seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure whether blocked (and optionally dry-run) requests are recorded
    | in the firewall_logs table.
    |
    */
    'logging' => [
        'enabled' => env('FIREWALL_GUI_LOGGING', true),
        'retention_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Response on Block
    |--------------------------------------------------------------------------
    */
    'response' => [
        'status' => 403,
        'message' => 'Access denied by firewall.',
        'view' => 'firewall-gui::blocked',
    ],

    /*
    |--------------------------------------------------------------------------
    | GUI Configuration
    |--------------------------------------------------------------------------
    */
    'gui' => [
        'enabled' => env('FIREWALL_GUI_PANEL_ENABLED', true),
        'prefix' => env('FIREWALL_GUI_PREFIX', 'firewall'),
        'middleware' => ['web'],
        'gate' => env('FIREWALL_GUI_GATE', null), // Optional Gate ability name, e.g. 'manage-firewall'
        'protect_with_firewall' => false,
        'prevent_self_lockout' => true,
        'per_page' => 20,
    ],

];
