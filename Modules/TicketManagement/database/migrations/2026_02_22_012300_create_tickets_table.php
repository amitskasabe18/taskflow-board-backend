<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create tickets table
 * 
 * NOTE: This migration depends on the sprints table being created first.
 * The sprints table is created in migration 2026_02_22_012000_create_sprints_table.php
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Human-readable key derived from project (e.g. "PLN-123")
            // Generated at app layer: project.key + tickets.key_sequence
            $table->string('key')->unique(); // "PLN-123"
            $table->integer('key_sequence'); // Auto-increment per project for key generation

            // ── Core fields ───────────────────────────────────────────────────
            $table->string('title');
            $table->text('description')->nullable();

            // ── Enums (PHP Enum + string column + check constraint) ───────────
            $table->string('priority')->default('medium');
            $table->string('type')->default('task');
            $table->string('resolution_status')->nullable();

            // ── Status (lookup table — user-customizable per project) ─────────
            $table->foreignId('status_id')->constrained('statuses')->onDelete('restrict');

            // ── Relationships ─────────────────────────────────────────────────
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('reporter_id')
                  ->nullable() // nullable so user deletion doesn't cascade-destroy tickets
                  ->constrained('users')
                  ->onDelete('set null');
            $table->foreignId('assignee_id')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');
            $table->foreignId('sprint_id')
                  ->nullable()
                  ->constrained('sprints')
                  ->onDelete('set null');

            // ── Parent-child (epics → stories → subtasks) ────────────────────
            // RESTRICT not CASCADE — deleting a parent should be an explicit action
            $table->foreignId('parent_id')
                  ->nullable()
                  ->constrained('tickets')
                  ->onDelete('restrict');

            // ── Time tracking ─────────────────────────────────────────────────
            // time_spent_minutes is DERIVED — computed from time_logs sum, not stored here
            $table->decimal('story_points', 4, 1)->nullable(); // supports 0.5, 1.5 etc.
            $table->integer('original_estimate_minutes')->nullable();
            $table->integer('remaining_estimate_minutes')->nullable();
            // NOTE: time_spent_minutes intentionally omitted — use time_logs table

            // ── Dates ─────────────────────────────────────────────────────────
            $table->date('due_date')->nullable();   // date not timestamp — time-of-day is irrelevant
            $table->date('start_date')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            // ── Metadata ──────────────────────────────────────────────────────
            $table->text('resolution_note')->nullable(); // free-text resolution description
            $table->string('environment')->nullable();   // "production", "staging", "local"

            // ── Board ordering ────────────────────────────────────────────────
            // Float allows fractional positioning (1.0, 1.5, 2.0)
            // Avoids bulk re-numbering on every drag-and-drop reorder
            $table->float('position', 10, 5)->default(0);

            $table->boolean('is_archived')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ───────────────────────────────────────────────────────
            $table->index(['project_id', 'status_id']);
            $table->index(['project_id', 'sprint_id', 'position']); // board fetch
            $table->index(['assignee_id', 'status_id']);
            $table->index(['reporter_id']);
            $table->index(['parent_id']);                            // subtask lookups
            $table->index(['project_id', 'is_archived']);           // default list filter
            $table->index(['project_id', 'due_date']);
            $table->index(['type', 'priority']);
            $table->index(['sprint_id', 'position']);
            $table->index(['project_id', 'key_sequence']);           // key generation
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
