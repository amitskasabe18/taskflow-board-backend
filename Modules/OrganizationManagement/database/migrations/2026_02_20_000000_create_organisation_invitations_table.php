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
        Schema::create('organisation_invitations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained('organisations')->onDelete('cascade');
            $table->string('email'); // Email of the person being invited
            $table->string('token')->unique(); // Unique invitation token
            $table->foreignId('invited_by')->constrained('users')->onDelete('cascade'); // Who sent the invitation
            $table->enum('role', ['admin', 'manager', 'member', 'viewer'])->default('member');
            $table->enum('status', ['pending', 'accepted', 'rejected', 'expired'])->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
            
            $table->index(['email', 'status']);
            $table->index(['token', 'status']);
            $table->index('organisation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organisation_invitations');
    }
};
