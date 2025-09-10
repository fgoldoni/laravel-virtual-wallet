<?php

declare(strict_types=1);

namespace Goldoni\LaravelVirtualWallet\Enums;

enum EntryType: string
{
    case CREDIT = 'CREDIT';
    case DEBIT  = 'DEBIT';
}
