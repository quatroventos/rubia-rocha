<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->where(['guard_name' => 'web', 'name' => 'admin'])->update(['name' => 'root']);
        DB::table('roles')->where(['guard_name' => 'web', 'name' => 'editor'])->update(['name' => 'user']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('roles')->where(['guard_name' => 'web', 'name' => 'root'])->update(['name' => 'admin']);
        DB::table('roles')->where(['guard_name' => 'web', 'name' => 'user'])->update(['name' => 'editor']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
