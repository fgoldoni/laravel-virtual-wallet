<?php

declare(strict_types=1);

use Goldoni\LaravelVirtualWallet\Enums\EntryStatus;
use Goldoni\LaravelVirtualWallet\Enums\EntryType;
use Goldoni\LaravelVirtualWallet\Enums\TransferStatus;
use Goldoni\LaravelVirtualWallet\Models\Entry;
use Goldoni\LaravelVirtualWallet\Models\Transfer;
use Goldoni\LaravelVirtualWallet\Models\Wallet;

return [
    'allow_negative'   => false,

    'default_currency' => 'EUR',

    'precision'        => 8,

    'table_prefix'     => 'wallet_',

    'models'           => [

        'wallet'   => Wallet::class,

        'entry'    => Entry::class,

        'transfer' => Transfer::class,

    ],
    'enums' => [

        'entry_type'      => EntryType::class,

        'entry_status'    => EntryStatus::class,

        'transfer_status' => TransferStatus::class,

    ],
];
