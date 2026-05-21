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
        Schema::table('milestones', function (Blueprint $table) {
            // Indexes to speed up common milestone filters
            $table->index('project_id', 'milestones_project_id_index');
            $table->index('status', 'milestones_status_index');
            $table->index('due_planned', 'milestones_due_planned_index');
            $table->index('due_actual', 'milestones_due_actual_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->dropIndex('milestones_project_id_index');
            $table->dropIndex('milestones_status_index');
            $table->dropIndex('milestones_due_planned_index');
            $table->dropIndex('milestones_due_actual_index');
        });
    }
};
