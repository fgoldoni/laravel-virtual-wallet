<?php

declare(strict_types=1);

namespace Goldoni\LaravelVirtualWallet;

use Illuminate\Support\ServiceProvider;

class WalletServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/wallet.php', 'wallet');
        $this->app->singleton(WalletService::class, fn (): WalletService => new WalletService());
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/wallet.php' => config_path('wallet.php'),
        ], 'wallet-config');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
