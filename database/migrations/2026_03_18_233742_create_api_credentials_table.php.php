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
    Schema::create('api_credentials', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('label')->nullable();
        $table->string('api_key', 40)->unique();
        $table->string('api_secret_hash');        
        $table->json('scopes'); 
        $table->json('allowed_ips')->nullable();  
        $table->integer('rate_limit')->default(60); 
        $table->boolean('is_active')->default(true);
        $table->timestamp('last_used_at')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_credentials');
    }
};
