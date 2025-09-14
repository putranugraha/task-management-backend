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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('Medium');
            $table->string('status', 50)->default('Planned');
            $table->date('start_planned')->nullable();
            $table->date('end_planned')->nullable();
            $table->unsignedInteger('duration_planned')->nullable();
            $table->date('start_actual')->nullable();
            $table->date('end_actual')->nullable();
            $table->unsignedInteger('duration_actual')->nullable();
            $table->unsignedTinyInteger('percent_complete')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};

