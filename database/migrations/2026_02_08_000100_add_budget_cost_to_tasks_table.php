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
            if (! Schema::hasColumn('tasks', 'budget_cost')) {
                $table->decimal('budget_cost', 15, 2)->default(0)->after('percent_complete');
                $table->index(['budget_cost']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (Schema::hasColumn('tasks', 'budget_cost')) {
                $table->dropIndex(['budget_cost']);
                $table->dropColumn('budget_cost');
            }
        });
    }
};

