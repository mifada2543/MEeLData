<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan `deleted_by` pada `trainee_tasks`.
 *
 * Nilai `null` berarti task dihapus oleh sistem (auto-arsip task terlambat),
 * sedangkan UUID berarti dihapus oleh user. Kolom ini hanya informational:
 * hak restore tetap milik admin dan tidak bergantung pada kolom ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainee_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('trainee_tasks', 'deleted_by')) {
                $table->foreignId('deleted_by')
                    ->nullable()
                    ->after('deleted_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('trainee_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('trainee_tasks', 'deleted_by')) {
                $table->dropConstrainedForeignId('deleted_by');
            }
        });
    }
};
