<?php

declare(strict_types=1);

namespace Goldoni\LaravelVirtualWallet\Facades;

use Goldoni\LaravelVirtualWallet\WalletService;
use Illuminate\Support\Facades\Facade;

class Wallet extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WalletService::class;
    }
}
