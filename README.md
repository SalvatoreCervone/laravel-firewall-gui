# Laravel Firewall GUI

A complete Laravel 11+ / PHP 8.2+ firewall package with a built-in **Blade GUI** (zero npm/Vite dependencies) to manage **whitelist**, **blacklist**, and **notify** rules for:

- **Single IPs** (IPv4 & IPv6, e.g. `192.168.1.10`, `2001:db8::1`)
- **CIDR Classes / Subnets** (e.g. `10.0.0.0/8`, `192.168.1.0/24`, `2001:db8::/32`)
- **IP Ranges** (e.g. `192.168.1.10-192.168.1.50`)
- **Reusable Groups** (e.g. "Office IPs", "Known Scrapers") with mixed rule types, per-group notification settings, and one-click active/inactive toggles

---

## Requirements

- PHP `^8.2`
- Laravel `^11.0` or `^12.0`

---

## Features

- **Zero-build Blade GUI**: Self-contained CSS and progressive HTML forms — works out of the box in any Laravel 11 or 12 application.
- **Localized Interface (EN / IT)**: Built-in English and Italian translations with an in-panel language selector (`auto`, `en`, `it`).
- **Cache-first Middleware**: Active rules, groups, and settings are cached and automatically invalidated whenever modified.
- **Priority-Ordered Evaluation & Default Fallback**: Rules are evaluated in priority order (lower number = higher priority), with a single configurable default fallback action (`allow` / `blacklist_only` or `block` / `whitelist_only`) and optional notification on unmatched requests.
- **Mixed Rule Types & Groups**: Groups can contain a mix of `whitelist`, `blacklist`, and `notify` rules, each with its own priority.
- **GUI Rule Editing Modal**: Edit existing rules directly from the panel, including value, type, group, priority, expiration, and `notify_on_hit`.
- **Email Notifications & Cooldowns**:
  - `notify` rules (or `notify_on_hit` on allow/block rules) and unmatched-block alerts send email notifications.
  - Configurable cooldown window in **minutes** (default `60` minutes), with per-group cooldown and recipient overrides.
  - Custom SMTP / mailer settings configurable in `config/firewall-gui.php` or directly from the GUI Settings page, including a **Send Test Email** action.
- **Email Notification Logs**: Track queued, sent, and failed notification emails along with recipient history and error details.
- **Dry-Run Mode**: Log requests that *would* be blocked (`dry_run_blocked`) without returning `403`.
- **Self-Lockout Protection**: Prevents admins from blacklisting their own current IP from the GUI.
- **Temporary Rules (TTL)**: Optional expiration date/time on any rule.
- **Bulk Import**: Paste lists of IPs, CIDRs, and ranges at once.
- **Test IP Simulator**: Check any IP from the dashboard or CLI to see which rule or group matches.
- **Consolidated Dashboard & Access Logs**: Inspect firewall logs directly from the dashboard or Logs page, whitelist an IP with one click, or clear logs.

---

## Installation

```bash
composer require salvatorecervone/laravel-firewall-gui
```

Run the migrations:

```bash
php artisan migrate
```

Optionally publish the configuration file, views, or migrations if you want to customize them:

```bash
php artisan vendor:publish --tag=firewall-gui-config
php artisan vendor:publish --tag=firewall-gui-views
php artisan vendor:publish --tag=firewall-gui-migrations
```

---

## Protecting Routes with the Middleware

### Global Protection (High Priority, No Route Changes)

To protect your entire application automatically without modifying individual routes, prepend [`FirewallMiddleware`](src/Http/Middleware/FirewallMiddleware.php) in `bootstrap/app.php`. Prepending places it at the top of the global middleware stack so it executes **before** other middleware (sessions, auth, controllers, etc.):

```php
// bootstrap/app.php
use FirewallGui\LaravelFirewallGui\Http\Middleware\FirewallMiddleware;
use Illuminate\Foundation\Configuration\Middleware;

->withMiddleware(function (Middleware $middleware) {
    $middleware->prepend(FirewallMiddleware::class);
})
```

By default (`'protect_with_firewall' => false` in `config/firewall-gui.php`), GUI routes (`/firewall/*`) are automatically excluded so you cannot accidentally lock yourself out of the panel.

### Per-Route / Group Protection

Alternatively, apply the `firewall` middleware alias to specific routes or route groups:

```php
// routes/web.php
Route::middleware(['firewall'])->group(function () {
    // Protected routes...
});
```

---

## Securing the GUI

By default, the GUI is available at `/firewall` using the `['web']` middleware. In production, configure `config/firewall-gui.php` or register an authorization callback in your `AppServiceProvider`:

```php
use FirewallGui\LaravelFirewallGui\Facades\Firewall;

public function boot(): void
{
    Firewall::auth(function ($request) {
        return $request->user()?->isAdmin() ?? false;
    });
}
```

Alternatively, set `'gate' => 'manage-firewall'` (or `FIREWALL_GUI_GATE=manage-firewall`) in `config/firewall-gui.php`.

---

## Configuration

Key options in `config/firewall-gui.php` (many can also be overridden at runtime from the GUI Settings page):

| Option / Env Variable | Description | Default |
|---|---|---|
| `FIREWALL_GUI_ENABLED` | Master switch for the firewall middleware | `true` |
| `FIREWALL_GUI_MODE` | Default evaluation mode (`blacklist_only` or `whitelist_only`) | `blacklist_only` |
| `FIREWALL_GUI_DRY_RUN` | Log blocks without returning HTTP 403 | `false` |
| `FIREWALL_GUI_CACHE_STORE` | Cache store name (`null` uses default store) | `null` |
| `FIREWALL_GUI_LOGGING` | Enable database logging of firewall events | `true` |
| `FIREWALL_GUI_NOTIFY_ENABLED` | Enable email notifications | `true` |
| `FIREWALL_GUI_NOTIFY_TO` | Comma-separated default notification recipients | `''` |
| `FIREWALL_GUI_NOTIFY_QUEUE` | Optional queue name for notification emails | `null` |
| `FIREWALL_GUI_NOTIFY_COOLDOWN_MINUTES` | Cooldown window in minutes between alerts per IP/rule | `60` |
| `FIREWALL_GUI_NOTIFY_ON_UNMATCHED_BLOCK` | Send alert when an unmatched request hits default block | `true` |
| `FIREWALL_GUI_MAILER` | Custom mailer (`null`/`default`, `smtp`, `sendmail`, `log`) and SMTP credentials | `null` |
| `FIREWALL_GUI_PANEL_ENABLED` | Enable or disable the Blade GUI routes | `true` |
| `FIREWALL_GUI_PREFIX` | URL prefix for the GUI | `firewall` |
| `FIREWALL_GUI_GATE` | Optional Laravel Gate ability name for GUI access | `null` |
| `FIREWALL_GUI_LOCALE` | GUI locale (`auto`, `en`, `it`) | `auto` |

---

## Facade Usage

```php
use FirewallGui\LaravelFirewallGui\Facades\Firewall;

Firewall::allow('192.168.1.50', label: 'Office VPN');
Firewall::block('203.0.113.0/24', label: 'Scraper subnet', expiresAt: now()->addDays(7));
Firewall::notify('198.51.100.25', label: 'Partner access');
Firewall::unblock('203.0.113.10');

$decision = Firewall::check('203.0.113.10');
if ($decision->isBlocked()) {
    // $decision->reason
}

Firewall::clearCache();
```

---

## Artisan Commands

```bash
php artisan firewall:allow 192.168.1.50 --label="Office" --group=office
php artisan firewall:block 203.0.113.0/24 --label="Spam" --expires="+24 hours"
php artisan firewall:notify 198.51.100.25 --label="Partner" --group=partners
php artisan firewall:unblock 203.0.113.10
php artisan firewall:check 203.0.113.10
php artisan firewall:clear-cache
```

---

## Testing

```bash
vendor/bin/phpunit
```
