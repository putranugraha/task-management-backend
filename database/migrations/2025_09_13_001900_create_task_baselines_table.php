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
        Schema::create('task_baselines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('baseline_id')->constrained('project_baselines')->cascadeOnDelete();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->date('start_planned_base')->nullable();
            $table->date('end_planned_base')->nullable();
            $table->integer('duration_planned_base')->nullable();
            $table->decimal('weight', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['baseline_id', 'task_id']);
            $table->index(['task_id']);
            $table->index(['baseline_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_baselines');
    }
};

