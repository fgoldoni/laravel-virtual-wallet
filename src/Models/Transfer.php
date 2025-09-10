<?php

declare(strict_types=1);

namespace Goldoni\LaravelVirtualWallet\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    protected $table;

    protected $fillable = [
        'uuid',
        'from_wallet_id',
        'to_wallet_id',
        'amount',
        'currency',
        'status',
        'idempotency_key',
        'meta'
    ];

    protected $casts = [
        'meta'   => 'array',
        'amount' => 'decimal:8'
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table           = config('wallet.table_prefix', 'wallet_') . 'transfers';
        $this->casts['status'] = config('wallet.enums.transfer_status');
    }

    public function fromWallet(): BelongsTo
    {
        $walletClass = config('wallet.models.wallet');

        return $this->belongsTo($walletClass, 'from_wallet_id');
    }

    public function toWallet(): BelongsTo
    {
        $walletClass = config('wallet.models.wallet');

        return $this->belongsTo($walletClass, 'to_wallet_id');
    }
}
