<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('super_admin')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
            $table->unsignedBigInteger('created_by')->nullable()->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('created_by');
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });


        User::query()->update([
            'role' => 'super_admin',
            'is_active' => true
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['role', 'is_active', 'created_by', 'last_login_at']);
        });
    }
};
