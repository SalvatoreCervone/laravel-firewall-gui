# Laravel Firewall GUI

A complete Laravel 11+ / PHP 8.2+ firewall package with a built-in **Blade GUI** (zero npm/Vite dependencies) to manage **whitelist** and **blacklist** rules for:

- **Single IPs** (IPv4 & IPv6, e.g. `192.168.1.10`, `2001:db8::1`)
- **CIDR Classes / Subnets** (e.g. `10.0.0.0/8`, `192.168.1.0/24`, `2001:db8::/32`)
- **IP Ranges** (e.g. `192.168.1.10-192.168.1.50`)
- **Reusable Groups** (e.g. "Office IPs", "Known Scrapers") with one-click active/inactive toggles

---

## Features

- **Zero-build Blade GUI**: Self-contained CSS and progressive HTML forms — works out of the box in any Laravel 11 or 12 application.
- **Cache-first Middleware**: Active rules and groups are cached and automatically invalidated when modified.
- **Flexible Modes**:
  - `blacklist_only` (default): Allows all traffic except blacklisted rules; explicit whitelist rules override blacklist rules.
  - `whitelist_only`: Blocks all traffic except whitelisted rules.
- **Dry-Run Mode**: Log requests that *would* be blocked without returning `403`.
- **Self-Lockout Protection**: Prevents admins from blacklisting their own current IP from the GUI.
- **Temporary Rules (TTL)**: Optional expiration date/time on any rule.
- **Bulk Import**: Paste lists of IPs, CIDRs, and ranges at once.
- **Test IP Simulator**: Check any IP from the dashboard or CLI to see which rule or group matches.
- **Blocked Request Logs**: Inspect blocked hits and whitelist an IP with one click.

---

## Installation

```bash
composer require salvatore/laravel-firewall-gui
```

Run the migrations:

```bash
php artisan migrate
```

Optionally publish the configuration file (and views/migrations if you want to customize them):

```bash
php artisan vendor:publish --tag=firewall-gui-config
```

---

## Protecting Routes with the Middleware

Apply the `firewall` middleware alias to routes, route groups, or globally in `bootstrap/app.php`:

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

Alternatively, set `'gate' => 'manage-firewall'` in `config/firewall-gui.php`.

---

## Facade Usage

```php
use FirewallGui\LaravelFirewallGui\Facades\Firewall;

Firewall::allow('192.168.1.50', label: 'Office VPN');
Firewall::block('203.0.113.0/24', label: 'Scraper subnet', expiresAt: now()->addDays(7));
Firewall::unblock('203.0.113.10');

$decision = Firewall::check('203.0.113.10');
if ($decision->isBlocked()) {
    // $decision->reason
}
```

---

## Artisan Commands

```bash
php artisan firewall:allow 192.168.1.50 --label="Office" --group=office
php artisan firewall:block 203.0.113.0/24 --label="Spam" --expires="+24 hours"
php artisan firewall:unblock 203.0.113.10
php artisan firewall:check 203.0.113.10
php artisan firewall:clear-cache
```

---

## Testing

```bash
vendor/bin/phpunit
```
