<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('airtime_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('airtime_stock_id')->constrained()->onDelete('cascade');
            $table->string('customer_phone', 11);
            $table->decimal('amount', 10, 2);
            $table->enum('network', ['MTN', 'GLO', 'AIRTEL', '9MOBILE']);
            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->string('reference')->unique();
            $table->string('vtu_reference')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['airtime_stock_id', 'status']);
            $table->index(['network', 'status']);
            $table->index('customer_phone');
            $table->index('reference');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('airtime_distributions');
    }
};