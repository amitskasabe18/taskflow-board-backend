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
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            
            // Basic admin information
            $table->string('uuid')->unique();
            $table->string('email')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone')->unique()->nullable();
            $table->string('profile_photo_path')->nullable();
            
            // Security and authentication
            $table->boolean('is_active')->default(true);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('verification_code')->nullable();
            $table->timestamp('verification_code_expires_at')->nullable();
            
            // Login tracking
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_successful_login_at')->nullable();
            $table->timestamp('last_failed_login_at')->nullable();
            $table->integer('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            
            // Session tracking
            $table->json('active_sessions')->nullable(); // Store active session IDs
            $table->integer('max_concurrent_sessions')->default(3);
            $table->timestamp('last_session_activity')->nullable();
            
            // Device and location tracking
            $table->json('known_devices')->nullable(); // Store trusted device fingerprints
            $table->json('last_login_ip')->nullable();
            $table->json('last_login_device')->nullable(); // User agent, device info
            $table->json('last_login_location')->nullable(); // Country, city, coordinates
            $table->boolean('new_device_alert')->default(true);
            
            // OTP tracking
            $table->string('current_otp')->nullable();
            $table->timestamp('otp_sent_at')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->integer('otp_attempts')->default(0);
            $table->timestamp('otp_locked_until')->nullable();
            $table->json('otp_delivery_methods')->nullable(); // SMS, email, etc.
            
            // Security settings
            $table->json('security_settings')->nullable(); // 2FA preferences, alerts
            $table->boolean('require_device_verification')->default(true);
            $table->boolean('enable_login_notifications')->default(true);
            $table->string('preferred_otp_method')->default('email'); // email, sms, both
            
            // Audit trail
            $table->json('login_history')->nullable(); // Last 50 login attempts
            $table->json('security_events')->nullable(); // Security-related events
            $table->timestamp('last_password_change_at')->nullable();
            $table->timestamp('last_security_review_at')->nullable();
            
            // Rate limiting
            $table->integer('otp_requests_count')->default(0);
            $table->timestamp('last_otp_request_at')->nullable();
            $table->integer('daily_otp_limit')->default(10);
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['email', 'is_active']);
            $table->index(['last_login_at']);
            $table->index(['last_successful_login_at']);
            $table->index(['locked_until']);
            $table->index(['otp_expires_at']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
