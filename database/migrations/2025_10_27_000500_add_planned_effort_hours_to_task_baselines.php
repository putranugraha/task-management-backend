<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_baselines', function (Blueprint $table) {
            if (!Schema::hasColumn('task_baselines', 'planned_effort_hours')) {
                $table->decimal('planned_effort_hours', 12, 2)->nullable()->after('weight');
            }
        });
    }

    public function down(): void
    {
        Schema::table('task_baselines', function (Blueprint $table) {
            if (Schema::hasColumn('task_baselines', 'planned_effort_hours')) {
                $table->dropColumn('planned_effort_hours');
            }
        });
    }
};

