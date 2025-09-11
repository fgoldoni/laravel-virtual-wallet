<?php

declare(strict_types=1);

namespace Goldoni\LaravelVirtualWallet\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Wallet extends Model
{
    protected $table;

    protected $fillable = [
        'label',
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
}
