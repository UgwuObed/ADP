<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('airtime_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('wallet_id')->constrained()->onDelete('cascade');
            $table->enum('network', ['MTN', 'GLO', 'AIRTEL', '9MOBILE']);
            $table->decimal('total_amount', 15, 2);
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('balance_amount', 15, 2);
            $table->integer('balance_quantity');
            $table->enum('status', ['pending', 'active', 'failed', 'exhausted'])->default('pending');
            $table->string('reference')->unique();
            $table->string('vtu_reference')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'network', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['network', 'status']);
            $table->index('reference');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airtime_stocks');
    }
};