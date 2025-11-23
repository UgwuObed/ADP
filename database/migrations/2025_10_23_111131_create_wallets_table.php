<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('account_number')->unique();
            $table->string('account_name');
            $table->string('bank_name')->default('VFD Microfinance Bank');
            $table->string('bank_code')->default('999999');
            $table->string('bvn')->nullable();
            $table->string('nin')->nullable();
            $table->enum('tier', ['1', '2', '3'])->default('1');
            $table->decimal('daily_limit', 15, 2)->default(30000);
            $table->decimal('transaction_limit', 15, 2)->default(30000);
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable(); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};