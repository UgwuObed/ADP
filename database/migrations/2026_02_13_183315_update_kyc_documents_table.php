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
        Schema::table('kyc_documents', function (Blueprint $table) {
            $table->string('cloudinary_public_id')->nullable()->after('file_url');
        });
        
        Schema::table('kyc_applications', function (Blueprint $table) {
            $table->string('cloudinary_signature_id')->nullable()->after('signature_file_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kyc_documents', function (Blueprint $table) {
            $table->dropColumn('cloudinary_public_id');
        });
        
        Schema::table('kyc_applications', function (Blueprint $table) {
            $table->dropColumn('cloudinary_signature_id');
        });
    }
};