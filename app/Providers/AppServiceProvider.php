<?php

namespace App\Providers;

use App\Auth\PlaintextUserProvider;
use App\Models\InventoryNotification;
use App\Models\Setting;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('plaintext-eloquent', function (Application $app, array $config): PlaintextUserProvider {
            return new PlaintextUserProvider($app['hash'], $config['model']);
        });

        View::composer('*', function ($view): void {
            $systemSettings = Schema::hasTable('settings')
                ? Setting::query()->pluck('value', 'key')
                : collect();

            $view->with('systemSettings', $systemSettings);

            if (auth()->check() && Schema::hasTable('inventory_notifications')) {
                $notificationQuery = InventoryNotification::query()
                    ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', auth()->id()));

                $view->with('layoutNotifications', (clone $notificationQuery)->latest()->limit(6)->get());
                $view->with('unreadNotificationCount', (clone $notificationQuery)->whereNull('read_at')->count());
            }
        });
    }
}
