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
        Schema::table('tasks', function (Blueprint $table) {
            // Indexes to speed up common filters
            $table->index('project_id', 'tasks_project_id_index');
            $table->index('status', 'tasks_status_index');
            $table->index('priority', 'tasks_priority_index');
            $table->index('end_planned', 'tasks_end_planned_index');
            $table->index('end_actual', 'tasks_end_actual_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_project_id_index');
            $table->dropIndex('tasks_status_index');
            $table->dropIndex('tasks_priority_index');
            $table->dropIndex('tasks_end_planned_index');
            $table->dropIndex('tasks_end_actual_index');
        });
    }
};
