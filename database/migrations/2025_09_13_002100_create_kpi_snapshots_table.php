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
        Schema::create('kpi_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('reporting_periods')->cascadeOnDelete();
            $table->unsignedInteger('tasks_total')->default(0);
            $table->unsignedInteger('tasks_done')->default(0);
            $table->unsignedInteger('overdue_count')->default(0);
            $table->decimal('avg_cycle_time_days', 6, 2)->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'period_id']);
            $table->index(['project_id']);
            $table->index(['period_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_snapshots');
    }
};
