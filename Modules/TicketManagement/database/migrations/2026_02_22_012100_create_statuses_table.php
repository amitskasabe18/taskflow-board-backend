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
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                  ->nullable() // null = global default statuses
                  ->constrained('projects')
                  ->onDelete('cascade');

            $table->string('name');               // "In Review", "QA", "Waiting for Customer"
            $table->string('slug');               // "in_review" — used in code/API
            $table->enum('category', [            // Fixed app-level grouping for board columns
                'todo',
                'in_progress',
                'done',
            ])->default('todo');
            $table->string('color', 7)->nullable(); // hex e.g. "#60a5fa"
            $table->integer('position')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['project_id', 'slug']);
            $table->index(['project_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statuses');
    }
};
