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
        Schema::table('projects', function (Blueprint $table) {
            // Indexes to speed up common project filters
            $table->index('status', 'projects_status_index');
            $table->index('division_owner_id', 'projects_division_owner_id_index');
            $table->index('client_name', 'projects_client_name_index');
            $table->index('start_planned', 'projects_start_planned_index');
            $table->index('end_planned', 'projects_end_planned_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex('projects_status_index');
            $table->dropIndex('projects_division_owner_id_index');
            $table->dropIndex('projects_client_name_index');
            $table->dropIndex('projects_start_planned_index');
            $table->dropIndex('projects_end_planned_index');
        });
    }
};
