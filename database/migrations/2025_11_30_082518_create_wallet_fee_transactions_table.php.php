<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_fee_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            
            $table->enum('fee_type', ['withdrawal', 'deposit', 'platform', 'penalty', 'other'])->default('withdrawal');
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('reference')->unique();
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('completed');
            
            $table->timestamps();
            
            $table->index(['wallet_id', 'fee_type']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_fee_transactions');
    }
};

