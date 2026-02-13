<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settlement_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name');
            $table->string('account_number')->unique();
            $table->string('account_name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->decimal('daily_limit', 15, 2)->nullable(); 
            $table->decimal('daily_total', 15, 2)->default(0); 
            $table->date('daily_total_date')->nullable(); 
            $table->integer('priority')->default(1); 
            $table->integer('usage_count')->default(0); 
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlement_accounts');
    }
};