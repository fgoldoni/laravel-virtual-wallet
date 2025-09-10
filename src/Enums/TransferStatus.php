<?php

declare(strict_types=1);

namespace Goldoni\LaravelVirtualWallet\Enums;

enum TransferStatus: string
{
    case PENDING   = 'PENDING';
    case COMPLETED = 'COMPLETED';
    case FAILED    = 'FAILED';
}
