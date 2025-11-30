<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
   
        try {
            DB::statement("ALTER TABLE kyc_applications MODIFY COLUMN status ENUM('pending', 'under_review', 'approved', 'resubmission_required') DEFAULT 'pending'");
        } catch (\Exception $e) {
        }

        Schema::table('kyc_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('kyc_applications', 'verification_method')) {
                $table->enum('verification_method', ['manual', 'automated'])->default('manual')->after('status');
            }
            
            if (!Schema::hasColumn('kyc_applications', 'kyc_provider')) {
                $table->string('kyc_provider')->nullable()->after('verification_method');
            }
            
            if (!Schema::hasColumn('kyc_applications', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('kyc_provider')->constrained('users')->nullOnDelete();
            }
            
            if (!Schema::hasColumn('kyc_applications', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
            
            if (!Schema::hasColumn('kyc_applications', 'admin_notes')) {
                $table->text('admin_notes')->nullable()->after('reviewed_at');
            }
            
            if (!Schema::hasColumn('kyc_applications', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('admin_notes');
            }
            
            if (!Schema::hasColumn('kyc_applications', 'verification_response')) {
                $table->json('verification_response')->nullable()->after('rejection_reason');
            }
            
            if (!Schema::hasColumn('kyc_applications', 'verification_score')) {
                $table->decimal('verification_score', 5, 2)->nullable()->after('verification_response');
            }
        });

        try {
            DB::statement("ALTER TABLE kyc_applications MODIFY COLUMN status ENUM('pending', 'under_review', 'approved', 'resubmission_required') DEFAULT 'pending'");
        } catch (\Exception $e) {
            \Log::error('Failed to modify status column: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        Schema::table('kyc_applications', function (Blueprint $table) {
            $columns = [
                'verification_method',
                'kyc_provider',
                'reviewed_by',
                'reviewed_at',
                'admin_notes',
                'rejection_reason',
                'verification_response',
                'verification_score'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('kyc_applications', $column)) {
                    if ($column === 'reviewed_by') {
                        $table->dropForeign(['reviewed_by']);
                    }
                    $table->dropColumn($column);
                }
            }
        });
        
        DB::statement("ALTER TABLE kyc_applications MODIFY COLUMN status ENUM('pending', 'in_progress', 'completed', 'rejected') DEFAULT 'pending'");
    }
};