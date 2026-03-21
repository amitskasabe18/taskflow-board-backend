<?php

namespace Modules\Admin\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Admin\Entities\Admin;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create super admin Amit
        Admin::create([
            'uuid' => Str::uuid(),
            'email' => 'amit@orbitflow.in',
            'first_name' => 'Amit',
            'last_name' => 'Super Admin',
            'phone' => '+918830231066',
            'is_active' => true,
            'is_verified' => true,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            
            // Security settings
            'security_settings' => [
                'two_factor_enabled' => true,
                'login_alerts' => true,
                'session_timeout' => 24, // hours
                'require_verification_for_new_devices' => true,
                'allowed_ip_ranges' => [], // Empty means all IPs allowed
                'working_hours_only' => false,
                'auto_logout_on_inactivity' => true
            ],
            'require_device_verification' => true,
            'enable_login_notifications' => true,
            'preferred_otp_method' => 'both',
            'max_concurrent_sessions' => 5,
            'new_device_alert' => true,
            
            // Initial security data
            'known_devices' => [],
            'active_sessions' => [],
            'login_history' => [],
            'security_events' => [],
            'otp_delivery_methods' => ['email', 'sms'],
            'daily_otp_limit' => 20,
        ]);

        $this->command->info('Super Admin Amit created successfully!');
        $this->command->info('Email: amit@orbitflow.in');
        $this->command->info('Phone: +918830231066');
        $this->command->info('Use OTP-based authentication (no passwords)');
        $this->command->info('Request OTP: POST /api/v1/admin/auth/send-otp');
    }
}
