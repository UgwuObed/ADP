<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); 
            $table->text('description')->nullable();
            $table->timestamps();
        });

        DB::table('kyc_settings')->insert([
            [
                'key' => 'default_verification_method',
                'value' => 'manual',
                'type' => 'string',
                'description' => 'Default KYC verification method (manual or automated)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'default_kyc_provider',
                'value' => 'youverify',
                'type' => 'string',
                'description' => 'Default KYC provider for automated verification',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'auto_approve_threshold',
                'value' => '85',
                'type' => 'string',
                'description' => 'Minimum verification score for auto-approval (0-100)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'require_manual_review',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Require manual review even for automated verifications',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_settings');
    }
};
