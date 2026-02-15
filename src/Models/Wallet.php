<?php

declare(strict_types=1);

namespace Goldoni\LaravelVirtualWallet\Models;

use Goldoni\LaravelVirtualWallet\Models\Concerns\HasExtraUlid;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Wallet extends Model
{
    use HasExtraUlid;

    protected $table;

    protected $fillable = [
        'label',
        'ulid',
        'currency',
        'balance',
        'meta',
    ];

    protected $casts = [
        'meta'    => AsArrayObject::class,
        'balance' => 'decimal:8',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = config('wallet.table_prefix', 'wallet_') . 'wallets';
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function entries(): HasMany
    {
        $entryClass = config('wallet.models.entry');

        return $this->hasMany($entryClass);
    }

    public function fromTransfers(): HasMany
    {
        $transferClass = config('wallet.models.transfer');

        return $this->hasMany($transferClass, 'from_wallet_id');
    }

    public function toTransfers(): HasMany
    {
        $transferClass = config('wallet.models.transfer');

        return $this->hasMany($transferClass, 'to_wallet_id');
    }
}
