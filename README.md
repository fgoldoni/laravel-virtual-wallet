# 🧾 Laravel Virtual Wallet

> Virtual multi-wallets for Laravel — credit, debit, transfer.  
> Atomic, idempotent, configurable, and PSR-12 compliant.

---

## ✨ Features

- ✅ Multi-wallet by **`label + currency`**
- ✅ Immutable **entries log** with running balance
- ✅ **Atomic** operations with per-wallet locking
- ✅ **Idempotency** via `idempotency_key`
- ✅ **Configurable** enums, models, and table prefix
- ✅ **Fluent** API:
  ```php
  Wallet::for($user)->label('main')->currency('EUR')->credit('100.00');
````

---

## ⚙️ Requirements

* PHP **8.2+**
* Laravel (provided by the host application)
* PHP extension **ext-bcmath**
* Any DB supported by Laravel that handles `decimal(20,8)`

---

## 🚀 Quick Start

1. **Install** (path repo)

```bash
composer require goldoni/laravel-virtual-wallet:dev-main
```

2. **Publish config**

```bash
php artisan vendor:publish --tag=wallet-config
```

3. **Migrate**

```bash
php artisan migrate
```

Default tables (with prefix `wallet_`):

* `wallet_wallets`, `wallet_entries`, `wallet_transfers`

---

## 🧩 Configuration

`config/wallet.php`

```php
return [
    'allow_negative' => false,
    'default_currency' => 'EUR',
    'precision' => 8,
    'table_prefix' => 'wallet_',

    'models' => [
        'wallet' => Goldoni\LaravelVirtualWallet\Models\Wallet::class,
        'entry' => Goldoni\LaravelVirtualWallet\Models\Entry::class,
        'transfer' => Goldoni\LaravelVirtualWallet\Models\Transfer::class,
    ],

    'enums' => [
        'entry_type' => Goldoni\LaravelVirtualWallet\Enums\EntryType::class,
        'entry_status' => Goldoni\LaravelVirtualWallet\Enums\EntryStatus::class,
        'transfer_status' => Goldoni\LaravelVirtualWallet\Enums\TransferStatus::class,
    ],
];
```

**Notes**

* `table_prefix`: change to avoid table name conflicts.
* `allow_negative`: allow overdraft on debit.
* `precision`: monetary scale (columns are `decimal(20,8)`).

---

## 🧱 Add the Trait to Your Owner

```php
use Goldoni\LaravelVirtualWallet\Traits\HasWallets;

class User extends Authenticatable
{
    use HasWallets;
}
```

The trait adds:

* `wallets()` morphMany relation
* `wallet(string $label = 'main', ?string $currency = null)` creator/getter

---

## 🛠️ Usage

```php
use Goldoni\LaravelVirtualWallet\Facades\Wallet;
```

### Credit

```php
Wallet::for($user)
    ->label('main')
    ->currency('EUR')
    ->credit('100.00', ['idempotency_key' => 'dep-100']);
```

### Debit

```php
Wallet::for($user)
    ->label('main')
    ->currency('EUR')
    ->debit('25.00', ['idempotency_key' => 'wd-25']);
```

### Transfer

```php
Wallet::for($buyer)
    ->label('main')
    ->currency('EUR')
    ->transfer(
        toOwner: $seller,
        amount: '35.00',
        options: ['idempotency_key' => 'order-500'],
        fromLabel: 'main',
        toLabel: 'revenue'
    );
```

### History & Balances

```php
$entries = Wallet::for($user)->label('main')->currency('EUR')->history(50);
$balance = Wallet::for($user)->label('main')->currency('EUR')->balance();
$wallets = Wallet::for($user)->wallets();
$totals  = Wallet::for($user)->totalBalanceByCurrency();
```

---

## 📚 Examples

### Chain `credit`, `debit`, `transfer` (no `idempotency_key`)

```php
Wallet::for($user)
    ->label('main')
    ->currency('EUR')
    ->credit('100.00')
    ->debit('25.00')
    ->transfer($seller, '30.00', options: [], fromLabel: 'main', toLabel: 'revenue');
```

### Multi-wallet on the same owner

```php
// Fund two wallets
Wallet::for($user)->label('main')->currency('EUR')->credit('200.00');
Wallet::for($user)->label('savings')->currency('EUR')->credit('50.00');

// Move funds from main to savings
Wallet::for($user)
    ->label('main')
    ->currency('EUR')
    ->transfer($user, '25.00', options: [], fromLabel: 'main', toLabel: 'savings');

// Read balances
$mainBalance = Wallet::for($user)->label('main')->currency('EUR')->balance();
$savingsBalance = Wallet::for($user)->label('savings')->currency('EUR')->balance();
```

---

## 🧠 API at a Glance

All amounts are **strings** (avoid float pitfalls).

```php
Wallet::for(\Illuminate\Database\Eloquent\Model $owner): self
Wallet::label(string $label): self
Wallet::currency(string $currency): self

Wallet::credit(string $amount, array $options = [], ?string $label = null, ?string $currency = null): self
Wallet::debit(string $amount, array $options = [], ?string $label = null, ?string $currency = null): self
Wallet::transfer(\Illuminate\Database\Eloquent\Model $toOwner, string $amount, array $options = [], ?string $fromLabel = null, ?string $toLabel = 'main', ?string $currency = null): self

Wallet::history(int $limit = 50, ?string $label = null, ?string $currency = null): \Illuminate\Support\Collection
Wallet::historyBetween(string $from, string $to, ?string $label = null, ?string $currency = null): \Illuminate\Support\Collection
Wallet::paginateHistory(int $perPage = 50, ?string $label = null, ?string $currency = null): \Illuminate\Contracts\Pagination\LengthAwarePaginator
Wallet::cursorHistory(int $perPage = 50, ?string $label = null, ?string $currency = null): \Illuminate\Contracts\Pagination\CursorPaginator

Wallet::balance(?string $label = null, ?string $currency = null): string
Wallet::balances(): \Illuminate\Support\Collection
Wallet::totalBalanceByCurrency(): \Illuminate\Support\Collection
Wallet::wallets(): \Illuminate\Support\Collection
Wallet::ensureWallet(string $label, string $currency): \Illuminate\Database\Eloquent\Model
```

`$options` supports:

* `idempotency_key`
* `reference_type`, `reference_id`
* `meta` (array)
* optional `currency` validation override

---

## 🗄️ Data Model

| Table              | Key Columns                                                                                               | Constraints                                       |
| ------------------ | --------------------------------------------------------------------------------------------------------- | ------------------------------------------------- |
| `wallet_wallets`   | `id`, `owner_type`, `owner_id`, `label`, `currency`, `balance`, `meta`, timestamps                        | Unique: `(owner_type, owner_id, label, currency)` |
| `wallet_entries`   | `id`, `wallet_id`, `uuid`, `type`, `status`, `amount`, `balance_after`, `currency`, `reference_*`, `meta` | Unique: `(wallet_id, idempotency_key)`            |
| `wallet_transfers` | `id`, `uuid`, `from_wallet_id`, `to_wallet_id`, `amount`, `currency`, `status`, `idempotency_key`, `meta` | Unique: `idempotency_key`                         |

> Table names honor your `table_prefix`.

---

## 🧾 Enums

```php
Goldoni\LaravelVirtualWallet\Enums\EntryType::CREDIT | DEBIT
Goldoni\LaravelVirtualWallet\Enums\EntryStatus::PENDING | COMPLETED | REVERSED
Goldoni\LaravelVirtualWallet\Enums\TransferStatus::PENDING | COMPLETED | FAILED
```

Swap enum classes via `config('wallet.enums.*')`.

---

## 🔔 Events

* `EntryRecorded($entry)`
* `TransferCompleted($transfer)`

Dispatched **after commit** for consistency.

---

## 🧯 Exceptions

* `InvalidAmount` — non-numeric or non-positive amount
* `InsufficientFunds` — would go negative and `allow_negative` is false
* `CurrencyMismatch` — mismatched currencies
* `DuplicateOperation` — reused `idempotency_key`

---

## ✅ Best Practices

1. Use `idempotency_key` for all external or retryable flows.
2. Keep labels meaningful: `main`, `savings`, `revenue`, etc.
3. For multi-currency workflows, set `.currency('USD')` or `.currency('EUR')`.
4. Pass amounts as strings: `'100.00'`.

---

## 🧪 Example Controller

```php
use Goldoni\LaravelVirtualWallet\Facades\Wallet;

class WalletController
{
    public function deposit(Request $request)
    {
        $user = $request->user();

        Wallet::for($user)
            ->label('main')
            ->currency('EUR')
            ->credit($request->input('amount'), [
                'idempotency_key' => $request->header('Idempotency-Key')
            ]);

        return response()->noContent();
    }
}
```

---

## 🛠️ Custom Models

Replace Eloquent classes via `config('wallet.models.*')`.
Your replacements must keep compatible columns and relations:

* `Wallet`: `owner()` and `entries()` relations
* `Entry`: belongs to `Wallet` via `wallet_id`
* `Transfer`: uses `from_wallet_id` and `to_wallet_id`

---

## 🧹 Coding Standards

* PSR-12 compliant
* camelCase identifiers
* No abbreviations and no inline comments

---

## 📄 License

MIT
