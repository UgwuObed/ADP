<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->string('otp', 4)->nullable()->after('token');   
            $table->boolean('is_used')->default(false)->after('otp');
            $table->timestamp('expires_at')->nullable()->after('is_used');
            $table->timestamp('updated_at')->nullable()->after('created_at');
        });
    }

    public function down()
    {
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->dropColumn(['otp', 'is_used', 'expires_at']);
        });
    }
};
