<?php

declare(strict_types=1);

namespace Goldoni\LaravelVirtualWallet\Models\Concerns;

use Illuminate\Support\Str;

trait HasExtraUlid
{
    protected static function bootHasExtraUlid(): void
    {
        static::creating(function ($model): void {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
