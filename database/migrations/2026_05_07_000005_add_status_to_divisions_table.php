<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            if (!Schema::hasColumn('divisions', 'status')) {
                $table->string('status', 50)->default('Aktif')->after('description');
                $table->index('status', 'divisions_status_index');
            }
        });

        DB::table('divisions')->whereNull('status')->update(['status' => 'Aktif']);
    }

    public function down(): void
    {
        Schema::table('divisions', function (Blueprint $table) {
            if (Schema::hasColumn('divisions', 'status')) {
                $table->dropIndex('divisions_status_index');
                $table->dropColumn('status');
            }
        });
    }
};
