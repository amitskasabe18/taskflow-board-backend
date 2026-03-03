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
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->enum('user_type', ['private', 'governmental', 'other'])->default('private');
            $table->string('logo')->nullable();
            $table->enum('plan', ['free', 'standard', 'premium'])->default('free');
            $table->date('plan_start_date')->nullable();
            $table->date('plan_end_date')->nullable();
            $table->string('plan_next_bill_date')->nullable();
            $table->decimal('plan_next_bill_amount', 10, 2)->nullable();
            $table->enum('plan_next_bill_status', ['pending', 'paid', 'overdue'])->nullable();
            $table->string('website_url')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code')->nullable();
            $table->integer('max_users')->nullable();
            $table->integer('max_projects')->nullable();
            $table->integer('max_storage_mb')->nullable();
            $table->boolean('is_trial')->default(false);
            $table->date('trial_end_date')->nullable();
            $table->timestamps();
            
            // Soft deletes
            $table->softDeletes();
            
            // Indexes for performance
            $table->index('uuid');
            $table->index('status');
            $table->index('user_type');
            $table->index('plan');
            $table->index(['status', 'plan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organisations');
    }
};
