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
        Schema::create('ticket_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_ticket_id')->constrained('tickets')->onDelete('cascade');
            $table->foreignId('target_ticket_id')->constrained('tickets')->onDelete('cascade');
            $table->enum('type', [
                'blocks',
                'is_blocked_by',
                'duplicates',
                'is_duplicated_by',
                'relates_to',
                'clones',
            ]);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['source_ticket_id', 'target_ticket_id', 'type']);
            $table->index(['target_ticket_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_links');
    }
};
