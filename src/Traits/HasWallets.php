<?php

declare(strict_types=1);

namespace Goldoni\LaravelVirtualWallet\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasWallets
{
    public function wallets(): MorphMany
    {
        $walletClass = config('wallet.models.wallet');

        return $this->morphMany($walletClass, 'owner');
    }

    public function wallet(string $label = 'main', string $currency = null): Model
    {
        config('wallet.models.wallet');
        $currency = $currency ?: config('wallet.default_currency', 'EUR');

        return $this->wallets()->firstOrCreate([
            'label'    => $label,
            'currency' => strtoupper((string) $currency),
        ]);
    }
}
