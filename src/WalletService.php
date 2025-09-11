<?php

declare(strict_types=1);

namespace Goldoni\LaravelVirtualWallet;

use Goldoni\LaravelVirtualWallet\Events\EntryRecorded;
use Goldoni\LaravelVirtualWallet\Events\TransferCompleted;
use Goldoni\LaravelVirtualWallet\Exceptions\CurrencyMismatch;
use Goldoni\LaravelVirtualWallet\Exceptions\DuplicateOperation;
use Goldoni\LaravelVirtualWallet\Exceptions\InsufficientFunds;
use Goldoni\LaravelVirtualWallet\Exceptions\InvalidAmount;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class WalletService
{
    protected Model $owner;

    protected string $label;

    protected string $currency;

    public function for(Model $model): self
    {
        $instance           = new self();
        $instance->owner    = $model;
        $instance->label    = 'main';
        $instance->currency = config('wallet.default_currency', 'EUR');

        return $instance;
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function currency(string $currency): self
    {
        $this->currency = strtoupper($currency);

        return $this;
    }

    public function credit(string $amount, array $options = [], ?string $label = null, ?string $currency = null): self
    {
        return DB::transaction(function () use ($amount, $options, $label, $currency): self {
            $model        = $this->resolveWallet($label, $currency);
            $walletClass  = config('wallet.models.wallet');
            $lockedWallet = $walletClass::whereKey($model->id)->lockForUpdate()->first();
            $amount       = $this->normalizePositiveAmount($amount);
            $this->assertCurrency($options['currency'] ?? $lockedWallet->currency, $lockedWallet->currency);

            if (!empty($options['idempotency_key'])) {
                $existing = $lockedWallet->entries()
                    ->where('idempotency_key', $options['idempotency_key'])
                    ->first();

                if ($existing) {
                    throw new DuplicateOperation();
                }
            }

            $precision   = (int) config('wallet.precision', 8);
            $newBalance  = bcadd((string) $lockedWallet->balance, $amount, $precision);
            $entryType   = config('wallet.enums.entry_type');
            $entryStatus = config('wallet.enums.entry_status');

            $entry = $lockedWallet->entries()->create([
                'wallet_id'       => $lockedWallet->id,
                'uuid'            => (string) Str::uuid(),
                'type'            => $entryType::CREDIT,
                'status'          => $entryStatus::COMPLETED,
                'amount'          => $amount,
                'balance_after'   => $newBalance,
                'currency'        => $lockedWallet->currency,
                'reference_type'  => $options['reference_type'] ?? null,
                'reference_id'    => $options['reference_id'] ?? null,
                'idempotency_key' => $options['idempotency_key'] ?? null,
                'meta'            => $options['meta'] ?? null,
            ]);

            $lockedWallet->update(['balance' => $newBalance]);

            DB::afterCommit(function () use ($entry): void {
                Event::dispatch(new EntryRecorded($entry));
            });

            return $this;
        });
    }

    public function debit(string $amount, array $options = [], ?string $label = null, ?string $currency = null): self
    {
        return DB::transaction(function () use ($amount, $options, $label, $currency): self {
            $model        = $this->resolveWallet($label, $currency);
            $walletClass  = config('wallet.models.wallet');
            $lockedWallet = $walletClass::whereKey($model->id)->lockForUpdate()->first();
            $amount       = $this->normalizePositiveAmount($amount);
            $this->assertCurrency($options['currency'] ?? $lockedWallet->currency, $lockedWallet->currency);

            $precision     = (int) config('wallet.precision', 8);
            $allowNegative = (bool) config('wallet.allow_negative', false);
            $newBalance    = bcsub((string) $lockedWallet->balance, $amount, $precision);

            if (!$allowNegative && bccomp($newBalance, '0', $precision) < 0) {
                $insufficientClass = InsufficientFunds::class;
                throw new $insufficientClass();
            }

            if (!empty($options['idempotency_key'])) {
                $existing = $lockedWallet->entries()
                    ->where('idempotency_key', $options['idempotency_key'])
                    ->first();

                if ($existing) {
                    throw new DuplicateOperation();
                }
            }

            $entryType   = config('wallet.enums.entry_type');
            $entryStatus = config('wallet.enums.entry_status');

            $entry = $lockedWallet->entries()->create([
                'wallet_id'       => $lockedWallet->id,
                'uuid'            => (string) Str::uuid(),
                'type'            => $entryType::DEBIT,
                'status'          => $entryStatus::COMPLETED,
                'amount'          => bcmul($amount, '-1', $precision),
                'balance_after'   => $newBalance,
                'currency'        => $lockedWallet->currency,
                'reference_type'  => $options['reference_type'] ?? null,
                'reference_id'    => $options['reference_id'] ?? null,
                'idempotency_key' => $options['idempotency_key'] ?? null,
                'meta'            => $options['meta'] ?? null,
            ]);

            $lockedWallet->update(['balance' => $newBalance]);

            DB::afterCommit(function () use ($entry): void {
                Event::dispatch(new EntryRecorded($entry));
            });

            return $this;
        });
    }

    public function transfer(Model $model, string $amount, array $options = [], ?string $fromLabel = null, ?string $toLabel = 'main', ?string $currency = null): self
    {
        return DB::transaction(function () use ($model, $amount, $options, $fromLabel, $toLabel, $currency): self {
            $fromWallet = $this->resolveWallet($fromLabel, $currency);
            $toWallet   = $model->wallet($toLabel ?: 'main', $currency ? strtoupper($currency) : $this->currency);
            $this->assertCurrency($fromWallet->currency, $toWallet->currency);

            $amount = $this->normalizePositiveAmount($amount);

            if (!empty($options['idempotency_key'])) {
                $transferClass = config('wallet.models.transfer');
                $existing      = $transferClass::where('idempotency_key', $options['idempotency_key'])->first();

                if ($existing) {
                    throw new DuplicateOperation();
                }
            }

            $walletClass = config('wallet.models.wallet');

            $firstId  = min($fromWallet->id, $toWallet->id);
            $secondId = max($fromWallet->id, $toWallet->id);

            $firstLock  = $walletClass::whereKey($firstId)->lockForUpdate()->first();
            $secondLock = $walletClass::whereKey($secondId)->lockForUpdate()->first();

            if ($fromWallet->id === $firstLock->id) {
                $this->applyDebitOnLocked($firstLock, $amount, $options, 'out');
                $this->applyCreditOnLocked($secondLock, $amount, $options, 'in');
            } else {
                $this->applyDebitOnLocked($secondLock, $amount, $options, 'out');
                $this->applyCreditOnLocked($firstLock, $amount, $options, 'in');
            }

            $transferClass  = config('wallet.models.transfer');
            $transferStatus = config('wallet.enums.transfer_status');

            $transfer = $transferClass::create([
                'uuid'            => (string) Str::uuid(),
                'from_wallet_id'  => $fromWallet->id,
                'to_wallet_id'    => $toWallet->id,
                'amount'          => $amount,
                'currency'        => $fromWallet->currency,
                'status'          => $transferStatus::COMPLETED,
                'idempotency_key' => $options['idempotency_key'] ?? null,
                'meta'            => $options['meta'] ?? null,
            ]);

            DB::afterCommit(function () use ($transfer): void {
                Event::dispatch(new TransferCompleted($transfer));
            });

            return $this;
        });
    }

    public function history(int $limit = 50, ?string $label = null, ?string $currency = null): Collection
    {
        $model = $this->resolveWallet($label, $currency);

        return $model->entries()->orderByDesc('id')->limit($limit)->get();
    }

    public function historyBetween(string $fromDate, string $toDate, ?string $label = null, ?string $currency = null): Collection
    {
        $model = $this->resolveWallet($label, $currency);

        return $model->entries()
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->orderByDesc('id')
            ->get();
    }

    public function paginateHistory(int $perPage = 50, ?string $label = null, ?string $currency = null): LengthAwarePaginator
    {
        $model = $this->resolveWallet($label, $currency);

        return $model->entries()->orderByDesc('id')->paginate($perPage);
    }

    public function cursorHistory(int $perPage = 50, ?string $label = null, ?string $currency = null): CursorPaginator
    {
        $model = $this->resolveWallet($label, $currency);

        return $model->entries()->orderByDesc('id')->cursorPaginate($perPage);
    }

    public function balance(?string $label = null, ?string $currency = null): string
    {
        $model = $this->resolveWallet($label, $currency);

        return (string) $model->balance;
    }

    public function balances(): Collection
    {
        return $this->owner
            ->wallets()
            ->orderBy('label')
            ->get(['id', 'label', 'currency', 'balance']);
    }

    public function totalBalanceByCurrency(): Collection
    {
        return $this->owner
            ->wallets()
            ->selectRaw('currency, SUM(balance) as total_balance')
            ->groupBy('currency')
            ->get();
    }

    public function wallets(): Collection
    {
        return $this->owner->wallets()->orderBy('label')->get();
    }

    public function ensureWallet(string $label, string $currency): Model
    {
        return $this->owner->wallet($label, strtoupper($currency));
    }

    protected function resolveWallet(?string $label = null, ?string $currency = null): Model
    {
        $finalLabel    = $label ?: $this->label;
        $finalCurrency = strtoupper($currency ?: $this->currency);

        return $this->owner->wallet($finalLabel, $finalCurrency);
    }

    protected function normalizePositiveAmount(string $amount): string
    {
        if (!is_numeric($amount)) {
            $invalidClass = InvalidAmount::class;
            throw new $invalidClass();
        }

        $precision  = (int) config('wallet.precision', 8);
        $normalized = number_format((float) $amount, $precision, '.', '');

        if (bccomp($normalized, '0', $precision) <= 0) {
            $invalidClass = InvalidAmount::class;
            throw new $invalidClass();
        }

        return $normalized;
    }

    protected function assertCurrency(string $left, string $right): void
    {
        if (strtoupper($left) !== strtoupper($right)) {
            $mismatchClass = CurrencyMismatch::class;
            throw new $mismatchClass();
        }
    }

    protected function applyDebitOnLocked(Model $model, string $amount, array $options, string $direction): Model
    {
        $precision     = (int) config('wallet.precision', 8);
        $allowNegative = (bool) config('wallet.allow_negative', false);
        $newBalance    = bcsub((string) $model->balance, $amount, $precision);

        if (!$allowNegative && bccomp($newBalance, '0', $precision) < 0) {
            throw new InsufficientFunds();
        }

        $entryType   = config('wallet.enums.entry_type');
        $entryStatus = config('wallet.enums.entry_status');

        $entry = $model->entries()->create([
            'wallet_id'       => $model->id,
            'uuid'            => (string) Str::uuid(),
            'type'            => $entryType::DEBIT,
            'status'          => $entryStatus::COMPLETED,
            'amount'          => bcmul($amount, '-1', $precision),
            'balance_after'   => $newBalance,
            'currency'        => $model->currency,
            'reference_type'  => $options['reference_type'] ?? null,
            'reference_id'    => $options['reference_id'] ?? null,
            'idempotency_key' => empty($options['idempotency_key'])
                ? null
                : $options['idempotency_key'] . '-out',
            'meta' => ['direction' => $direction] + ($options['meta'] ?? []),
        ]);

        $model->update(['balance' => $newBalance]);

        DB::afterCommit(function () use ($entry): void {
            Event::dispatch(new EntryRecorded($entry));
        });

        return $entry;
    }

    protected function applyCreditOnLocked(Model $model, string $amount, array $options, string $direction): Model
    {
        $precision  = (int) config('wallet.precision', 8);
        $newBalance = bcadd((string) $model->balance, $amount, $precision);

        $entryType   = config('wallet.enums.entry_type');
        $entryStatus = config('wallet.enums.entry_status');

        $entry = $model->entries()->create([
            'wallet_id'       => $model->id,
            'uuid'            => (string) Str::uuid(),
            'type'            => $entryType::CREDIT,
            'status'          => $entryStatus::COMPLETED,
            'amount'          => $amount,
            'balance_after'   => $newBalance,
            'currency'        => $model->currency,
            'reference_type'  => $options['reference_type'] ?? null,
            'reference_id'    => $options['reference_id'] ?? null,
            'idempotency_key' => empty($options['idempotency_key'])
                ? null
                : $options['idempotency_key'] . '-in',
            'meta' => ['direction' => $direction] + ($options['meta'] ?? []),
        ]);

        $model->update(['balance' => $newBalance]);

        DB::afterCommit(function () use ($entry): void {
            Event::dispatch(new EntryRecorded($entry));
        });

        return $entry;
    }
}
