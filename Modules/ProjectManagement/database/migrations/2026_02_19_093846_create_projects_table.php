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
            $table->uuid('uuid')->unique();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('status', 50)->default('active'); // active, completed, archived, on_hold
            $table->string('priority', 20)->default('medium'); // low, medium, high, urgent
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->json('metadata')->nullable(); // For storing additional project data
            
            // Foreign key to organization
            $table->foreignId('organisation_id')
                ->constrained('organisations')
                ->onDelete('cascade');
            
            // Project lead/manager
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index('uuid');
            $table->index('status');
            $table->index('priority');
            $table->index('organisation_id');
            $table->index('created_by');
            $table->index(['status', 'priority']);
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
