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
    Schema::create('api_credential_stocks', function (Blueprint $table) {
        $table->id();
        $table->foreignId('api_credential_id')->constrained()->cascadeOnDelete();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete(); 
        $table->string('network'); 
        $table->string('type')->default('airtime'); 
        $table->decimal('balance', 15, 2)->default(0);
        $table->decimal('total_allocated', 15, 2)->default(0); 
        $table->decimal('total_sold', 15, 2)->default(0);
        $table->timestamps();

        $table->unique(['api_credential_id', 'network', 'type']);
        $table->index(['user_id', 'network', 'type']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_credential_stocks');
    }
};
