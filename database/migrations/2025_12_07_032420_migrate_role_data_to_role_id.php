<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Role;

return new class extends Migration
{
    public function up()
    {
        $users = DB::table('users')->whereNull('role_id')->get();
        
        foreach ($users as $user) {
            if ($user->role) {
                $role = Role::where('name', $user->role)->first();
                if ($role) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['role_id' => $role->id]);
                }
            }
        }
    }

    public function down()
    {
        $users = DB::table('users')->whereNotNull('role_id')->get();
        
        foreach ($users as $user) {
            $role = Role::find($user->role_id);
            if ($role) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['role' => $role->name]);
            }
        }
    }
};