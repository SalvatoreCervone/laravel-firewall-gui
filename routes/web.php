<?php

use FirewallGui\LaravelFirewallGui\Http\Controllers\DashboardController;
use FirewallGui\LaravelFirewallGui\Http\Controllers\GroupController;
use FirewallGui\LaravelFirewallGui\Http\Controllers\LogController;
use FirewallGui\LaravelFirewallGui\Http\Controllers\RuleController;
use FirewallGui\LaravelFirewallGui\Http\Controllers\SettingsController;
use FirewallGui\LaravelFirewallGui\Http\Middleware\AuthorizeFirewallGui;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => config('firewall-gui.gui.prefix', 'firewall'),
    'middleware' => array_merge(
        (array) config('firewall-gui.gui.middleware', ['web']),
        [AuthorizeFirewallGui::class]
    ),
    'as' => 'firewall-gui.',
], function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/test-ip', [DashboardController::class, 'testIp'])->name('test-ip');

    Route::get('/rules', [RuleController::class, 'index'])->name('rules.index');
    Route::post('/rules', [RuleController::class, 'store'])->name('rules.store');
    Route::post('/rules/bulk-import', [RuleController::class, 'bulkImport'])->name('rules.bulk-import');
    Route::put('/rules/{rule}', [RuleController::class, 'update'])->name('rules.update');
    Route::patch('/rules/{rule}/toggle', [RuleController::class, 'toggle'])->name('rules.toggle');
    Route::delete('/rules/{rule}', [RuleController::class, 'destroy'])->name('rules.destroy');

    Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
    Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
    Route::get('/groups/{group}', [GroupController::class, 'show'])->name('groups.show');
    Route::put('/groups/{group}', [GroupController::class, 'update'])->name('groups.update');
    Route::patch('/groups/{group}/toggle', [GroupController::class, 'toggle'])->name('groups.toggle');
    Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->name('groups.destroy');

    Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
    Route::post('/logs/{log}/allow', [LogController::class, 'allowIp'])->name('logs.allow');
    Route::delete('/logs', [LogController::class, 'clear'])->name('logs.clear');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::put('/settings/locale', [SettingsController::class, 'updateLocale'])->name('settings.locale.update');
    Route::put('/settings/engine', [SettingsController::class, 'updateEngine'])->name('settings.engine.update');
    Route::put('/settings/mail', [SettingsController::class, 'updateMail'])->name('settings.mail.update');
    Route::post('/settings/test-email', [SettingsController::class, 'sendTest'])->name('settings.test-email');
});
