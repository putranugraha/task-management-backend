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
        Schema::table('project_baselines', function (Blueprint $table) {
            $table->date('start_planned_base')->nullable()->after('note');
            $table->date('end_planned_base')->nullable()->after('start_planned_base');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_baselines', function (Blueprint $table) {
            $table->dropColumn(['start_planned_base', 'end_planned_base']);
        });
    }
};

