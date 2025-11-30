<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE kyc_applications MODIFY COLUMN status ENUM('pending', 'under_review', 'approved', 'rejected', 'resubmission_required', 'completed') DEFAULT 'pending'");
        
        DB::table('kyc_applications')
            ->where('status', 'completed')
            ->update(['status' => 'under_review']);
        
        DB::statement("ALTER TABLE kyc_applications MODIFY COLUMN status ENUM('pending', 'under_review', 'approved', 'rejected', 'resubmission_required') DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE kyc_applications MODIFY COLUMN status ENUM('pending', 'under_review', 'approved', 'rejected', 'resubmission_required', 'completed') DEFAULT 'pending'");
        
        DB::table('kyc_applications')
            ->where('status', 'under_review')
            ->update(['status' => 'completed']);
    }
};