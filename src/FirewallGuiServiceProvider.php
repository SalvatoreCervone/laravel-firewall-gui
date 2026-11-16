<?php

namespace FirewallGui\LaravelFirewallGui;

use FirewallGui\LaravelFirewallGui\Console\Commands\AllowCommand;
use FirewallGui\LaravelFirewallGui\Console\Commands\BlockCommand;
use FirewallGui\LaravelFirewallGui\Console\Commands\CheckCommand;
use FirewallGui\LaravelFirewallGui\Console\Commands\ClearCacheCommand;
use FirewallGui\LaravelFirewallGui\Console\Commands\NotifyCommand;
use FirewallGui\LaravelFirewallGui\Console\Commands\UnblockCommand;
use FirewallGui\LaravelFirewallGui\Http\Middleware\FirewallMiddleware;
use FirewallGui\LaravelFirewallGui\Models\FirewallGroup;
use FirewallGui\LaravelFirewallGui\Models\FirewallRule;
use FirewallGui\LaravelFirewallGui\Models\FirewallSetting;
use FirewallGui\LaravelFirewallGui\Services\FirewallManager;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class FirewallGuiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/firewall-gui.php', 'firewall-gui');

        $this->app->singleton(FirewallManager::class);
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('firewall', FirewallMiddleware::class);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'firewall-gui');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'firewall-gui');

        if ((bool) config('firewall-gui.gui.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        $this->publishes([
            __DIR__.'/../config/firewall-gui.php' => config_path('firewall-gui.php'),
        ], 'firewall-gui-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'firewall-gui-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/firewall-gui'),
        ], 'firewall-gui-views');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AllowCommand::class,
                BlockCommand::class,
                NotifyCommand::class,
                UnblockCommand::class,
                CheckCommand::class,
                ClearCacheCommand::class,
            ]);
        }

        $flushCache = function (): void {
            $this->app->make(FirewallManager::class)->clearCache();
        };

        FirewallRule::saved($flushCache);
        FirewallRule::deleted($flushCache);
        FirewallGroup::saved($flushCache);
        FirewallGroup::deleted($flushCache);
        FirewallSetting::saved($flushCache);
        FirewallSetting::deleted($flushCache);

        $this->app->resolving('mail.manager', function (): void {
            $this->app->make(FirewallManager::class)->applyCustomMailerConfig();
        });
    }
}
