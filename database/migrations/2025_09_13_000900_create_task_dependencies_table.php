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
        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('depends_on_task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('type', 10)->default('FS'); // FS, SS, FF, SF
            $table->integer('lag_days')->default(0); // can be negative (lead)
            $table->timestamps();

            $table->unique(['task_id', 'depends_on_task_id', 'type'], 'task_dep_unique');
            // Optional: prevent self-dependency when supported by DB
            // $table->check('task_id <> depends_on_task_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_dependencies');
    }
};

