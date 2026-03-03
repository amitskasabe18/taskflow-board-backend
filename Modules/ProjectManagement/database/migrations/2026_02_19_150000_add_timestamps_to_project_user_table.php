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
        Schema::table('project_user', function (Blueprint $table) {
            if (!Schema::hasColumn('project_user', 'created_at')) {
                $table->timestamps(); // Add created_at and updated_at columns
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_user', function (Blueprint $table) {
            if (Schema::hasColumn('project_user', 'created_at')) {
                $table->dropTimestamps(); // Remove created_at and updated_at columns
            }
        });
    }
};
