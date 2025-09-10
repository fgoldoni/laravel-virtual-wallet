<?php

declare(strict_types=1);

namespace Goldoni\LaravelVirtualWallet\Events;

use Illuminate\Database\Eloquent\Model;

class EntryRecorded
{
    public function __construct(public Model $entry)
    {
    }
}
