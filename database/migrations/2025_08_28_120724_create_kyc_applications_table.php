<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('kyc_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('business_registration_number')->nullable();
            $table->string('tax_identification_number')->nullable();
            $table->string('state')->nullable();
            $table->text('address')->nullable();
            $table->enum('signature_type', ['upload', 'initials'])->nullable();
            $table->string('signature_file_url')->nullable();
            $table->string('initials_text')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'rejected'])->default('pending');
            $table->integer('current_step')->default(1); 
            $table->boolean('step_1_completed')->default(false);
            $table->boolean('step_2_completed')->default(false);
            $table->boolean('step_3_completed')->default(false);
            
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('kyc_applications');
    }
};