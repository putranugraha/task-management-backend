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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'division_id')) {
                $table->foreignId('division_id')->nullable()->after('password_hash')->constrained('divisions')->nullOnDelete();
            } else {
                $table->foreign('division_id')->references('id')->on('divisions')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'division_id')) {
                try {
                    $table->dropForeign(['division_id']);
                } catch (\Throwable $e) {
                    // Foreign key might not exist; ignore.
                }

                if (!Schema::hasColumn('users', 'password_hash')) {
                    $table->dropColumn('division_id');
                }
            }
        });
    }
};
