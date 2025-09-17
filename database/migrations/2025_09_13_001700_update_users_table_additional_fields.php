<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'password_hash')) {
                $table->string('password_hash')->nullable()->after('email');
            }
        });

        if (Schema::hasColumn('users', 'password') && Schema::hasColumn('users', 'password_hash')) {
            DB::statement('UPDATE users SET password_hash = password WHERE password_hash IS NULL');

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('password');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'division_id')) {
                $table->foreignId('division_id')->nullable()->after('password_hash')->constrained('divisions')->nullOnDelete();
            }

            if (!Schema::hasColumn('users', 'job_title')) {
                $table->string('job_title')->nullable()->after('division_id');
            }

            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('job_title');
            }

            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('is_active');
            }

            if (!Schema::hasColumn('users', 'status')) {
                $table->string('status', 50)->default('Aktif')->after('last_login_at');
            }
        });

        DB::table('users')->whereNull('is_active')->update(['is_active' => true]);
        DB::table('users')->whereNull('status')->update(['status' => 'Aktif']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'password')) {
                $table->string('password')->nullable()->after('email');
            }
        });

        if (Schema::hasColumn('users', 'password_hash') && Schema::hasColumn('users', 'password')) {
            DB::statement('UPDATE users SET password = password_hash WHERE password IS NULL');
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'password_hash')) {
                $table->dropColumn('password_hash');
            }
            if (Schema::hasColumn('users', 'last_login_at')) {
                $table->dropColumn('last_login_at');
            }
            if (Schema::hasColumn('users', 'is_active')) {
                $table->dropColumn('is_active');
            }
            if (Schema::hasColumn('users', 'job_title')) {
                $table->dropColumn('job_title');
            }
            if (Schema::hasColumn('users', 'division_id')) {
                try {
                    $table->dropForeign(['division_id']);
                } catch (\Throwable $e) {
                    // ignore
                }
                $table->dropColumn('division_id');
            }
        });
    }
};
