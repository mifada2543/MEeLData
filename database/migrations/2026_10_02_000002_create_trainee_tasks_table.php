<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel utama aplikasi: data tugas trainee.
     * PK memakai UUID (public identifier) dan soft delete.
     */
    public function up(): void
    {
        Schema::create('trainee_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('trainee_id')->constrained('users')->cascadeOnDelete();
            $table->string('task');
            $table->text('desc')->nullable();
            $table->dateTime('start_date');
            $table->dateTime('deadline');
            $table->enum('status', ['progress', 'done', 'late', 'canceled'])->default('progress');
            $table->timestamps();
            $table->softDeletes();

            $table->index('trainee_id');
            $table->index(['trainee_id', 'status', 'deadline']);
            $table->index('status');
            $table->index('deadline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainee_tasks');
    }
};
