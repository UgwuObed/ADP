<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('wallet_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['credit', 'debit']);
            $table->enum('category', ['funding', 'withdrawal', 'transfer_in', 'transfer_out', 'fee', 'reversal'])->default('funding');
            $table->decimal('amount', 15, 2);
            $table->string('reference')->unique();
            $table->string('session_id')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed', 'reversed'])->default('pending');
            $table->string('status_code')->nullable();
            $table->string('source_account_number')->nullable();
            $table->string('source_account_name')->nullable();
            $table->string('source_bank_code')->nullable();
            $table->string('source_bank_name')->nullable();
            $table->string('destination_account_number')->nullable();
            $table->string('destination_account_name')->nullable();
            $table->string('destination_bank_code')->nullable();
            $table->string('destination_bank_name')->nullable();
            $table->string('narration')->nullable();
            $table->string('transaction_channel')->nullable();
            $table->text('description')->nullable();
            $table->decimal('balance_before', 15, 2)->nullable();
            $table->decimal('balance_after', 15, 2)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('reference');
            $table->index('session_id');
            $table->index(['user_id', 'created_at']);
            $table->index(['wallet_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};