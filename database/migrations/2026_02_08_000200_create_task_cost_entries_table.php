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
        Schema::create('task_cost_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->date('incurred_on');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('category', 50)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'incurred_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_cost_entries');
    }
};

