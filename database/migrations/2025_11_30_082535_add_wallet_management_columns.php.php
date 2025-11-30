<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->boolean('is_frozen')->default(false)->after('is_active');
            $table->text('freeze_reason')->nullable()->after('is_frozen');
            $table->foreignId('frozen_by')->nullable()->after('freeze_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('frozen_at')->nullable()->after('frozen_by');
            
            $table->integer('withdrawal_count_today')->default(0)->after('account_balance');
            $table->integer('withdrawal_count_this_month')->default(0)->after('withdrawal_count_today');
            $table->date('last_withdrawal_date')->nullable()->after('withdrawal_count_this_month');
            
            $table->date('last_deposit_fee_charged')->nullable()->after('last_withdrawal_date');
            $table->date('last_platform_fee_charged')->nullable()->after('last_deposit_fee_charged');
            
            $table->boolean('has_suspicious_activity')->default(false)->after('is_active');
            $table->timestamp('last_activity_at')->nullable()->after('has_suspicious_activity');
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropForeign(['frozen_by']);
            $table->dropColumn([
                'is_frozen',
                'freeze_reason',
                'frozen_by',
                'frozen_at',
                'withdrawal_count_today',
                'withdrawal_count_this_month',
                'last_withdrawal_date',
                'last_deposit_fee_charged',
                'last_platform_fee_charged',
                'has_suspicious_activity',
                'last_activity_at',
            ]);
        });
    }
};
