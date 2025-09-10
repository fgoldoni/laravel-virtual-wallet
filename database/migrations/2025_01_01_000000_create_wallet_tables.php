<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $prefix = config('wallet.table_prefix', 'wallet_');

        Schema::create($prefix . 'wallets', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->morphs('owner');
            $blueprint->string('label')->default('main');
            $blueprint->string('currency', 3)->index();
            $blueprint->decimal('balance', 20, 8)->default(0);
            $blueprint->json('meta')->nullable();
            $blueprint->timestamps();
            $blueprint->unique(['owner_type','owner_id','label','currency']);
        });

        Schema::create($prefix . 'entries', function (Blueprint $blueprint) use ($prefix): void {
            $blueprint->id();
            $blueprint->foreignId('wallet_id')->constrained($prefix . 'wallets')->cascadeOnDelete();
            $blueprint->uuid()->unique();
            $blueprint->string('type');
            $blueprint->string('status')->default('COMPLETED');
            $blueprint->decimal('amount', 20, 8);
            $blueprint->decimal('balance_after', 20, 8);
            $blueprint->string('currency', 3);
            $blueprint->nullableMorphs('reference');
            $blueprint->string('idempotency_key')->nullable();
            $blueprint->json('meta')->nullable();
            $blueprint->timestamps();
            $blueprint->index(['wallet_id','id']);
            $blueprint->index(['wallet_id','created_at']);
            $blueprint->unique(['wallet_id','idempotency_key']);
        });

        Schema::create($prefix . 'transfers', function (Blueprint $blueprint) use ($prefix): void {
            $blueprint->id();
            $blueprint->uuid()->unique();
            $blueprint->foreignId('from_wallet_id')->constrained($prefix . 'wallets')->cascadeOnDelete();
            $blueprint->foreignId('to_wallet_id')->constrained($prefix . 'wallets')->cascadeOnDelete();
            $blueprint->decimal('amount', 20, 8);
            $blueprint->string('currency', 3);
            $blueprint->string('status')->default('COMPLETED');
            $blueprint->string('idempotency_key')->nullable()->unique();
            $blueprint->json('meta')->nullable();
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        $prefix = config('wallet.table_prefix', 'wallet_');
        Schema::dropIfExists($prefix . 'transfers');
        Schema::dropIfExists($prefix . 'entries');
        Schema::dropIfExists($prefix . 'wallets');
    }
};
