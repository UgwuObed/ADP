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
        Schema::create('wallet_funding_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('wallet_id')->constrained()->onDelete('cascade');
            $table->string('reference')->unique()->index();
            $table->decimal('amount', 15, 2);
            $table->decimal('actual_amount_paid', 15, 2)->nullable(); 
            $table->string('bank_account_number');
            $table->string('bank_name');
            $table->string('bank_account_name');
            $table->enum('status', ['pending', 'confirmed', 'rejected', 'expired'])->default('pending')->index();
            $table->text('proof_of_payment')->nullable(); 
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('confirmed_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_funding_requests');
    }
};