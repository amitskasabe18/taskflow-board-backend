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
        Schema::create('ticket_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('field_name');          // "status", "assignee_id", "priority"
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('change_type');         // "created", "updated", "deleted"
            $table->timestamp('changed_at')->useCurrent();
            $table->timestamps();

            $table->index(['ticket_id', 'changed_at']);
            $table->index(['user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_histories');
    }
};
