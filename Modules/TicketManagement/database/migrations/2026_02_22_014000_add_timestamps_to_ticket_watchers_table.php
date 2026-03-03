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
        Schema::table('ticket_watchers', function (Blueprint $table) {
            // Check if timestamps already exist before adding them
            if (!Schema::hasColumn('ticket_watchers', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_watchers', function (Blueprint $table) {
            // Check if timestamps exist before dropping them
            if (Schema::hasColumn('ticket_watchers', 'created_at')) {
                $table->dropTimestamps();
            }
        });
    }
};
