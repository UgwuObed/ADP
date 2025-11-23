<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Networks table (MTN, Glo, Airtel, 9mobile)
        Schema::create('networks', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // MTN, Glo, etc
            $table->string('code')->unique(); // mtn, glo, etc
            $table->string('logo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('airtime_enabled')->default(true);
            $table->boolean('data_enabled')->default(true);
            $table->timestamps();
        });

        // Data plans table
        Schema::create('data_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // 1GB, 2GB, etc
            $table->string('data_code'); // Code to send to API
            $table->decimal('amount', 12, 2); // Face value
            $table->decimal('cost_price', 12, 2); // What you pay Topupbox
            $table->string('validity'); // 30 days, 7 days, etc
            $table->string('plan_type')->default('sme'); // sme, gifting, corporate
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // VTU Transactions
        Schema::create('vtu_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->string('provider_reference')->nullable();
            $table->enum('type', ['airtime', 'data', 'electricity', 'cable', 'betting']);
            $table->string('network')->nullable(); 
            $table->string('phone')->nullable();
            $table->string('meter_number')->nullable();
            $table->string('customer_name')->nullable();
            $table->decimal('amount', 12, 2); 
            $table->decimal('cost_price', 12, 2); 
            $table->decimal('profit', 12, 2)->default(0); 
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->enum('status', ['pending', 'success', 'failed', 'refunded'])->default('pending');
            $table->string('electricity_token')->nullable();
            $table->string('data_plan')->nullable();
            $table->json('api_response')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'type']);
            $table->index('created_at');
        });

        // Distributor pricing 
        Schema::create('distributor_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('product_type'); 
            $table->string('network')->nullable();
            $table->foreignId('data_plan_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('discount_percent', 5, 2)->default(0); 
            $table->decimal('custom_price', 12, 2)->nullable(); 
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['user_id', 'product_type', 'network', 'data_plan_id'], 'unique_distributor_pricing');
        });

        // Commission/Discount settings 
        Schema::create('commission_settings', function (Blueprint $table) {
            $table->id();
            $table->string('product_type'); 
            $table->string('network')->nullable();
            $table->decimal('default_discount_percent', 5, 2)->default(3); 
            $table->decimal('min_amount', 12, 2)->default(50);
            $table->decimal('max_amount', 12, 2)->default(50000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_settings');
        Schema::dropIfExists('distributor_pricing');
        Schema::dropIfExists('vtu_transactions');
        Schema::dropIfExists('electricity_providers');
        Schema::dropIfExists('data_plans');
        Schema::dropIfExists('networks');
    }
};
