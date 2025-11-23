<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributor_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('network'); 
            $table->string('type')->default('airtime'); 
            $table->decimal('balance', 14, 2)->default(0); 
            $table->decimal('total_purchased', 14, 2)->default(0); 
            $table->decimal('total_sold', 14, 2)->default(0); 
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['user_id', 'network', 'type']);
            $table->index(['user_id', 'network']);
        });

        Schema::create('stock_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->string('network');
            $table->string('type')->default('airtime'); 
            $table->decimal('amount', 14, 2); 
            $table->decimal('cost', 14, 2); 
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('wallet_balance_before', 14, 2);
            $table->decimal('wallet_balance_after', 14, 2);
            $table->decimal('stock_balance_before', 14, 2);
            $table->decimal('stock_balance_after', 14, 2);
            $table->enum('status', ['success', 'failed'])->default('success');
            $table->timestamps();
            
            $table->index(['user_id', 'network']);
            $table->index('created_at');
        });

        Schema::create('airtime_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->string('provider_reference')->nullable();
            $table->string('network');
            $table->string('phone');
            $table->decimal('amount', 14, 2); 
            $table->decimal('stock_balance_before', 14, 2);
            $table->decimal('stock_balance_after', 14, 2);
            $table->enum('status', ['pending', 'success', 'failed', 'refunded'])->default('pending');
            $table->json('api_response')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'network']);
            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });

        Schema::create('data_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('data_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('provider_reference')->nullable();
            $table->string('network');
            $table->string('phone');
            $table->string('plan_name');
            $table->decimal('amount', 14, 2); 
            $table->decimal('stock_balance_before', 14, 2);
            $table->decimal('stock_balance_after', 14, 2);
            $table->enum('status', ['pending', 'success', 'failed', 'refunded'])->default('pending');
            $table->json('api_response')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'network']);
            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_sales');
        Schema::dropIfExists('airtime_sales');
        Schema::dropIfExists('stock_purchases');
        Schema::dropIfExists('distributor_stocks');
    }
};
