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
    Schema::create('api_usage_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('api_credential_id')->constrained()->cascadeOnDelete();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete(); 
        $table->string('endpoint');           
        $table->string('method', 10);
        $table->string('ip_address', 45)->nullable();
        $table->json('request_payload')->nullable();   
        $table->json('response_payload')->nullable();
        $table->integer('response_code');
        $table->integer('response_time_ms')->nullable();
        $table->string('status');             
        $table->string('reference')->nullable();       
        $table->string('sale_type')->nullable();       
        $table->timestamps();

        $table->index(['api_credential_id', 'created_at']);
        $table->index(['user_id', 'created_at']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_usage_logs');
    }
};
