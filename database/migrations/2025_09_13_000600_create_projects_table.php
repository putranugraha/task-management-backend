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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('client_name');
            $table->decimal('value_amount', 15, 2)->default(0);
            $table->text('scope')->nullable();
            $table->text('objective')->nullable();
            // Assuming division owner references users table; set null on delete to preserve project history
            $table->foreignId('division_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_planned')->nullable();
            $table->date('end_planned')->nullable();
            $table->string('status', 50)->default('Planned');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
