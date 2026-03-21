<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin Security Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for admin security features including scoring weights,
    | rate limiting, and monitoring thresholds.
    |
    */

    'security_score' => [
        'two_factor' => 20,
        'email_verified' => 15,
        'phone_verified' => 15,
        'device_verification' => 20,
        'no_failed_attempts' => 15,
        'recent_review' => 15,
    ],

    'online_threshold_minutes' => 5,

    'rate_limiting' => [
        'otp_daily_limit' => 10,
        'otp_cooldown_seconds' => 60,
        'login_attempts_limit' => 5,
        'lockout_duration_minutes' => 60,
        'otp_attempts_limit' => 3,
        'otp_window_minutes' => 10,
    ],

    'sessions' => [
        'max_concurrent_sessions' => 3,
        'session_timeout_hours' => 24,
    ],

    'security' => [
        'require_device_verification' => true,
        'enable_login_notifications' => true,
        'new_device_alert' => true,
        'suspicious_activity_threshold' => 3,
    ],
];
