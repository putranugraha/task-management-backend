<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop existing FK to allow altering the column
        Schema::table('task_baselines', function (Blueprint $table) {
            $table->dropForeign(['baseline_id']);
        });

        // Alter column to be nullable without requiring doctrine/dbal
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE task_baselines ALTER COLUMN baseline_id DROP NOT NULL');
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE task_baselines MODIFY baseline_id BIGINT UNSIGNED NULL');
        } else {
            // Fallback attempt (may require doctrine/dbal in other drivers)
            DB::statement('ALTER TABLE task_baselines ALTER COLUMN baseline_id DROP NOT NULL');
        }

        // Re-add FK with NULL on delete behavior
        Schema::table('task_baselines', function (Blueprint $table) {
            $table->foreign('baseline_id')
                ->references('id')
                ->on('project_baselines')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Revert to NOT NULL and cascade on delete
        Schema::table('task_baselines', function (Blueprint $table) {
            $table->dropForeign(['baseline_id']);
        });

        // Remove rows that would violate NOT NULL on revert (defensive)
        DB::statement('DELETE FROM task_baselines WHERE baseline_id IS NULL');

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE task_baselines ALTER COLUMN baseline_id SET NOT NULL');
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE task_baselines MODIFY baseline_id BIGINT UNSIGNED NOT NULL');
        } else {
            DB::statement('ALTER TABLE task_baselines ALTER COLUMN baseline_id SET NOT NULL');
        }

        Schema::table('task_baselines', function (Blueprint $table) {
            $table->foreign('baseline_id')
                ->references('id')
                ->on('project_baselines')
                ->cascadeOnDelete();
        });
    }
};
