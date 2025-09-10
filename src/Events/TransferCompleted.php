<?php

declare(strict_types=1);

namespace Goldoni\LaravelVirtualWallet\Events;

use Illuminate\Database\Eloquent\Model;

class TransferCompleted
{
    public function __construct(public Model $transfer)
    {
    }
}
