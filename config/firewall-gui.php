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
    | Access Email Notifications (Notify Rules)
    |--------------------------------------------------------------------------
    |
    | Rules/groups of type "notify" allow the request and queue an email at most
    | once per cooldown window (in hours) per IP address, while still logging
    | every access.
    |
    */
    'notifications' => [
        'enabled' => env('FIREWALL_GUI_NOTIFY_ENABLED', true),
        'to' => env('FIREWALL_GUI_NOTIFY_TO', ''), // Comma-separated emails or array
        'queue' => env('FIREWALL_GUI_NOTIFY_QUEUE', null), // Optional queue name
        'cooldown_hours' => env('FIREWALL_GUI_NOTIFY_COOLDOWN_HOURS', 24),
        'log_access' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail Server Configuration
    |--------------------------------------------------------------------------
    |
    | Optionally override Laravel's default mailer for firewall notifications.
    | When "mailer" is null or "default", Laravel's default mailer is used.
    |
    */
    'mail' => [
        'mailer' => env('FIREWALL_GUI_MAILER', null), // null/'default', 'smtp', 'sendmail', 'log'
        'host' => env('FIREWALL_GUI_MAIL_HOST', null),
        'port' => env('FIREWALL_GUI_MAIL_PORT', 587),
        'encryption' => env('FIREWALL_GUI_MAIL_ENCRYPTION', 'tls'), // 'tls', 'ssl', or null
        'username' => env('FIREWALL_GUI_MAIL_USERNAME', null),
        'password' => env('FIREWALL_GUI_MAIL_PASSWORD', null),
        'from_address' => env('FIREWALL_GUI_MAIL_FROM_ADDRESS', null),
        'from_name' => env('FIREWALL_GUI_MAIL_FROM_NAME', null),
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
