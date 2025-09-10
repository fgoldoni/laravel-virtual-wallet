<?php

declare(strict_types=1);

namespace Goldoni\LaravelVirtualWallet\Enums;

enum EntryStatus: string
{
    case PENDING   = 'PENDING';
    case COMPLETED = 'COMPLETED';
    case REVERSED  = 'REVERSED';
}
