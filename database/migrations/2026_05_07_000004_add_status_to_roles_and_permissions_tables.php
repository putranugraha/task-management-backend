<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $roleTable = config('permission.table_names.roles', 'roles');
        $permissionTable = config('permission.table_names.permissions', 'permissions');

        Schema::table($roleTable, function (Blueprint $table) use ($roleTable) {
            if (!Schema::hasColumn($roleTable, 'status')) {
                $table->string('status', 50)->default('Aktif')->after('guard_name');
                $table->index('status', "{$roleTable}_status_index");
            }
        });

        Schema::table($permissionTable, function (Blueprint $table) use ($permissionTable) {
            if (!Schema::hasColumn($permissionTable, 'status')) {
                $table->string('status', 50)->default('Aktif')->after('guard_name');
                $table->index('status', "{$permissionTable}_status_index");
            }
        });

        DB::table($roleTable)->whereNull('status')->update(['status' => 'Aktif']);
        DB::table($permissionTable)->whereNull('status')->update(['status' => 'Aktif']);
    }

    public function down(): void
    {
        $roleTable = config('permission.table_names.roles', 'roles');
        $permissionTable = config('permission.table_names.permissions', 'permissions');

        Schema::table($permissionTable, function (Blueprint $table) use ($permissionTable) {
            if (Schema::hasColumn($permissionTable, 'status')) {
                $table->dropIndex("{$permissionTable}_status_index");
                $table->dropColumn('status');
            }
        });

        Schema::table($roleTable, function (Blueprint $table) use ($roleTable) {
            if (Schema::hasColumn($roleTable, 'status')) {
                $table->dropIndex("{$roleTable}_status_index");
                $table->dropColumn('status');
            }
        });
    }
};
