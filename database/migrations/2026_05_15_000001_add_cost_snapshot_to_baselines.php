<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_baselines', function (Blueprint $table) {
            if (! Schema::hasColumn('project_baselines', 'value_amount_base')) {
                $table->decimal('value_amount_base', 15, 2)->nullable()->after('end_planned_base');
            }
        });

        Schema::table('task_baselines', function (Blueprint $table) {
            if (! Schema::hasColumn('task_baselines', 'budget_cost_base')) {
                $table->decimal('budget_cost_base', 15, 2)->nullable()->after('planned_effort_hours');
            }
        });

        DB::statement('
            UPDATE project_baselines
            SET value_amount_base = projects.value_amount
            FROM projects
            WHERE projects.id = project_baselines.project_id
              AND project_baselines.value_amount_base IS NULL
        ');

        DB::statement('
            UPDATE task_baselines
            SET budget_cost_base = tasks.budget_cost
            FROM tasks
            WHERE tasks.id = task_baselines.task_id
              AND task_baselines.budget_cost_base IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('task_baselines', function (Blueprint $table) {
            if (Schema::hasColumn('task_baselines', 'budget_cost_base')) {
                $table->dropColumn('budget_cost_base');
            }
        });

        Schema::table('project_baselines', function (Blueprint $table) {
            if (Schema::hasColumn('project_baselines', 'value_amount_base')) {
                $table->dropColumn('value_amount_base');
            }
        });
    }
};
