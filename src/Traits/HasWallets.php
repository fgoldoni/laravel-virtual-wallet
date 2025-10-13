<?php

declare(strict_types=1);

namespace Goldoni\LaravelVirtualWallet\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

trait HasWallets
{
    protected array $resolvedWallets = [];

    public function wallets(): MorphMany
    {
        $walletClass = config('wallet.models.wallet');

        return $this->morphMany($walletClass, 'owner');
    }

    public function wallet(string $label = 'main', ?string $currency = null): Model
    {
        $currency = strtoupper($currency ?: (string) config('wallet.default_currency', 'EUR'));
        $memoKey  = sprintf('%s|%s|%s|%s', $this->getMorphClass(), $this->getKey(), $label, $currency);

        return $this->resolvedWallets[$memoKey] ?? $this->resolvedWallets[$memoKey] = DB::transaction(function () use ($label, $currency) {
            $query = $this->wallets()
                ->where('label', $label)
                ->where('currency', $currency)
                ->lockForUpdate();

            if ($existing = $query->first()) {
                return $existing;
            }

            try {
                return $this->wallets()->create([
                    'label'    => $label,
                    'currency' => $currency,
                ]);
            } catch (UniqueConstraintViolationException) {
                return $this->wallets()
                    ->where('label', $label)
                    ->where('currency', $currency)
                    ->firstOrFail();
            }
        });
    }
}
