<?php

declare(strict_types=1);

namespace Goldoni\LaravelVirtualWallet\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Entry extends Model
{
    protected $table;

    protected $fillable = [
        'wallet_id',
        'uuid',
        'type',
        'status',
        'amount',
        'balance_after',
        'currency',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'meta',
    ];

    protected $casts = [
        'meta'          => AsArrayObject::class,
        'amount'        => 'decimal:8',
        'balance_after' => 'decimal:8',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table           = config('wallet.table_prefix', 'wallet_') . 'entries';
        $this->casts['type']   = config('wallet.enums.entry_type');
        $this->casts['status'] = config('wallet.enums.entry_status');
    }

    public function wallet(): BelongsTo
    {
        $walletClass = config('wallet.models.wallet');

        return $this->belongsTo($walletClass);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
