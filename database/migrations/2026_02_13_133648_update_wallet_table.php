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
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn([
                'account_number',
                'account_name',
                'bank_name',
                'bank_code',
                'bvn',
                'nin',
                'tier',
                'daily_limit',
                'transaction_limit',
                'meta',
            ]);

            $table->renameColumn('account_balance', 'balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->renameColumn('balance', 'account_balance');
            
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_code')->nullable();
            $table->string('bvn')->nullable();
            $table->string('nin')->nullable();
            $table->string('tier')->nullable();
            $table->decimal('daily_limit', 15, 2)->nullable();
            $table->decimal('transaction_limit', 15, 2)->nullable();
            $table->json('meta')->nullable();
        });
    }
};