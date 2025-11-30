<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_settings', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('wallet_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_global')->default(true);
            
            $table->decimal('withdrawal_fee_fixed', 12, 2)->default(0);
            $table->decimal('withdrawal_fee_percentage', 5, 2)->default(0); 
            $table->decimal('withdrawal_minimum', 12, 2)->default(100);
            $table->decimal('withdrawal_maximum', 12, 2)->default(500000);
            $table->enum('withdrawal_frequency', ['per_transaction', 'daily', 'monthly'])->default('per_transaction');
            $table->integer('withdrawal_daily_limit')->nullable(); 
            $table->integer('withdrawal_monthly_limit')->nullable(); 
            
            $table->decimal('deposit_fee_fixed', 12, 2)->default(0);
            $table->decimal('deposit_fee_percentage', 5, 2)->default(0);
            $table->enum('deposit_fee_frequency', ['per_transaction', 'monthly', 'quarterly', 'annually'])->default('per_transaction');
            $table->decimal('deposit_minimum', 12, 2)->default(100);
            $table->decimal('deposit_maximum', 12, 2)->default(1000000);
            
            $table->decimal('platform_fee_fixed', 12, 2)->default(0);
            $table->decimal('platform_fee_percentage', 5, 2)->default(0);
            $table->enum('platform_fee_type', ['per_transaction', 'monthly', 'quarterly', 'annually'])->default('per_transaction');
            $table->text('platform_fee_description')->nullable();
            
            $table->integer('settlement_lead_time_hours')->default(24);
            $table->enum('settlement_frequency', ['instant', 'daily', 'weekly', 'monthly'])->default('daily');
            $table->string('settlement_schedule')->nullable(); 
            
            $table->boolean('allow_negative_balance')->default(false);
            $table->decimal('negative_balance_limit', 12, 2)->default(0);
            $table->boolean('auto_freeze_on_suspicious')->default(true);
            $table->boolean('require_kyc_for_withdrawal')->default(true);
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            $table->unique(['wallet_id']);
            $table->index('is_global');
        });

        DB::table('wallet_settings')->insert([
            'wallet_id' => null,
            'is_global' => true,
            'withdrawal_fee_fixed' => 50.00,
            'withdrawal_fee_percentage' => 0.00,
            'withdrawal_minimum' => 100.00,
            'withdrawal_maximum' => 500000.00,
            'withdrawal_frequency' => 'per_transaction',
            'deposit_fee_fixed' => 0.00,
            'deposit_fee_percentage' => 0.00,
            'deposit_fee_frequency' => 'per_transaction',
            'deposit_minimum' => 100.00,
            'deposit_maximum' => 1000000.00,
            'platform_fee_fixed' => 0.00,
            'platform_fee_percentage' => 1.00,
            'platform_fee_type' => 'per_transaction',
            'settlement_lead_time_hours' => 24,
            'settlement_frequency' => 'daily',
            'require_kyc_for_withdrawal' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_settings');
    }
};

