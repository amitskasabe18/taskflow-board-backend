<?php

namespace Modules\Admin\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Admin extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $table = 'admins';

    protected $attributes = [
        'daily_otp_limit' => 5,
        'max_concurrent_sessions' => 3,
        'failed_login_attempts' => 0,
        'otp_attempts' => 0,
        'otp_requests_count' => 0,
        'is_active' => true,
        'is_verified' => false,
        'require_device_verification' => true,
        'enable_login_notifications' => true,
    ];

    protected $fillable = [
        'uuid', 'email', 'first_name', 'last_name', 'phone', 'profile_photo_path',
        'is_active', 'is_verified', 'email_verified_at', 'phone_verified_at',
        'verification_code', 'verification_code_expires_at',
        'last_login_at', 'last_successful_login_at', 'last_failed_login_at',
        'failed_login_attempts', 'locked_until',
        'active_sessions', 'max_concurrent_sessions', 'last_session_activity',
        'known_devices', 'last_login_ip', 'last_login_device', 'last_login_location',
        'new_device_alert', 'current_otp', 'otp_sent_at', 'otp_expires_at',
        'otp_attempts', 'otp_locked_until', 'otp_delivery_methods',
        'security_settings', 'require_device_verification', 'enable_login_notifications',
        'preferred_otp_method', 'login_history', 'security_events',
        'last_password_change_at', 'last_security_review_at',
        'otp_requests_count', 'last_otp_request_at', 'daily_otp_limit'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'verification_code_expires_at' => 'datetime',
        'last_login_at' => 'datetime',
        'last_successful_login_at' => 'datetime',
        'last_failed_login_at' => 'datetime',
        'locked_until' => 'datetime',
        'last_session_activity' => 'datetime',
        'otp_sent_at' => 'datetime',
        'otp_expires_at' => 'datetime',
        'otp_locked_until' => 'datetime',
        'last_password_change_at' => 'datetime',
        'last_security_review_at' => 'datetime',
        'last_otp_request_at' => 'datetime',
        'is_active' => 'boolean',
        'is_verified' => 'boolean',
        'new_device_alert' => 'boolean',
        'require_device_verification' => 'boolean',
        'enable_login_notifications' => 'boolean',
        'active_sessions' => 'array',
        'known_devices' => 'array',
        'last_login_ip' => 'array',
        'last_login_device' => 'array',
        'last_login_location' => 'array',
        'otp_delivery_methods' => 'array',
        'security_settings' => 'array',
        'login_history' => 'array',
        'security_events' => 'array'
    ];

    protected $hidden = [
        'current_otp', 'verification_code', 'known_devices'
    ];

    // Generate OTP (returns plain OTP to send, stores hash)
    public function generateOTP(): string
    {
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        $this->current_otp = Hash::make($otp); // store hash only
        $this->otp_sent_at = now();
        $this->otp_expires_at = now()->addMinutes(10);
        $this->otp_attempts = 0;
        $this->save();

        // Log OTP for debugging
        Log::info("OTP generated for admin {$this->email}: {$otp}");
        
        return $otp; // return plain OTP to send to user
    }

    // Verify OTP using hash check
    public function verifyOTP(string $otp): bool
    {
        if ($this->isOTPLocked()) {
            return false;
        }

        if ($this->otp_expires_at && $this->otp_expires_at->isPast()) {
            $this->incrementOTPAttempts();
            return false;
        }

        if (Hash::check($otp, $this->current_otp)) { // use Hash::check
            $this->clearOTP();
            return true;
        }

        $this->incrementOTPAttempts();
        return false;
    }

    // Check if OTP is locked
    public function isOTPLocked(): bool
    {
        return $this->otp_locked_until && $this->otp_locked_until->isFuture();
    }

    // Increment OTP attempts and lock if necessary
    public function incrementOTPAttempts(): void
    {
        $this->otp_attempts++;
        
        if ($this->otp_attempts >= 5) {
            $this->otp_locked_until = now()->addMinutes(30);
        }
        
        $this->save();
    }

    // Clear OTP after successful verification
    public function clearOTP(): void
    {
        $this->current_otp = null;
        $this->otp_sent_at = null;
        $this->otp_expires_at = null;
        $this->otp_attempts = 0;
        $this->save();
    }

    // Check if account is locked due to failed attempts
    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    // Increment failed login attempts
    public function incrementFailedLoginAttempts(): void
    {
        $this->failed_login_attempts++;
        $this->last_failed_login_at = now();
        
        if ($this->failed_login_attempts >= 5) {
            $this->locked_until = now()->addHours(1);
        }
        
        $this->save();
    }

    // Reset failed login attempts after successful login
    public function resetFailedLoginAttempts(): void
    {
        $this->failed_login_attempts = 0;
        $this->last_successful_login_at = now();
        $this->save();
    }

    // Record login attempt (uses server-side IP)
    public function recordLoginAttempt(array $data): void
    {
        $loginHistory = $this->login_history ?? [];
        
        $loginHistory[] = [
            'attempt_at' => now()->toISOString(),
            'ip' => request()->ip(), // always resolve server-side
            'user_agent' => $data['user_agent'] ?? null,
            'device_fingerprint' => $data['device_fingerprint'] ?? null,
            'location' => $data['location'] ?? null,
            'success' => $data['success'] ?? false,
            'failure_reason' => $data['failure_reason'] ?? null,
        ];

        // Keep only last 50 attempts
        $this->login_history = array_slice($loginHistory, -50);
        $this->save();
    }

    // Add security event
    public function addSecurityEvent(string $event, array $data = []): void
    {
        $securityEvent = [
            'timestamp' => now()->toISOString(),
            'event' => $event,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'data' => $data
        ];

        $events = $this->security_events ?? [];
        array_unshift($events, $securityEvent);
        
        // Keep only last 100 events
        $this->security_events = array_slice($events, 0, 100);
        $this->save();
    }

    // Check if device is known (using hash comparison)
    public function isKnownDevice(string $deviceFingerprint): bool
    {
        foreach ($this->known_devices ?? [] as $device) {
            if (Hash::check($deviceFingerprint, $device)) {
                return true;
            }
        }
        return false;
    }

    // Add known device (stores hash)
    public function addKnownDevice(string $deviceFingerprint): void
    {
        $knownDevices = $this->known_devices ?? [];
        
        if (!$this->isKnownDevice($deviceFingerprint)) {
            $knownDevices[] = Hash::make($deviceFingerprint); // hash it
            $this->known_devices = $knownDevices;
            $this->save();
        }
    }

    // Check if admin can request OTP (fixed logic)
    public function canRequestOTP(): bool
    {
        if ($this->isOTPLocked()) {
            return false;
        }

        // Reset counter at start of new day
        if ($this->last_otp_request_at && !$this->last_otp_request_at->isToday()) {
            $this->otp_requests_count = 0;
            $this->save();
        }

        // Check daily limit with fallback default
        if ($this->otp_requests_count >= ($this->daily_otp_limit ?? 5)) {
            return false;
        }

        // Enforce 60-second cooldown between requests
        if ($this->last_otp_request_at && 
            $this->last_otp_request_at->diffInSeconds(now()) < 60) {
            return false;
        }

        return true;
    }

    // Record OTP request
    public function recordOTPRequest(): void
    {
        // Reset daily counter if it's a new day
        if ($this->last_otp_request_at && !$this->last_otp_request_at->isToday()) {
            $this->otp_requests_count = 0;
        }

        $this->otp_requests_count++;
        $this->last_otp_request_at = now();
        $this->save();
    }

    // Update login tracking
    public function updateLoginTracking(array $loginData): void
    {
        $this->last_login_at = now();
        $this->last_login_ip = ['ip' => $loginData['ip'] ?? null, 'timestamp' => now()];
        $this->last_login_device = [
            'user_agent' => $loginData['user_agent'] ?? null,
            'device_fingerprint' => $loginData['device_fingerprint'] ?? null,
            'timestamp' => now()
        ];
        $this->last_login_location = $loginData['location'] ?? null;
        $this->save();
    }

    // Add active session (returns false if limit reached)
    public function addActiveSession(string $sessionId): bool
    {
        $activeSessions = $this->active_sessions ?? [];
        $maxSessions = $this->max_concurrent_sessions ?? 3;

        if (count($activeSessions) >= $maxSessions) {
            return false; // let controller reject or notify user
        }

        $activeSessions[] = [
            'session_id' => $sessionId,
            'created_at' => now()->toISOString(),
            'last_activity' => now()->toISOString()
        ];

        $this->active_sessions = $activeSessions;
        $this->last_session_activity = now();
        $this->save();

        return true;
    }

    // Remove active session
    public function removeActiveSession(string $sessionId): void
    {
        $activeSessions = $this->active_sessions ?? [];
        $activeSessions = array_filter($activeSessions, function($session) use ($sessionId) {
            return $session['session_id'] !== $sessionId;
        });

        $this->active_sessions = array_values($activeSessions);
        $this->save();
    }

    // Get full name
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    // Check if admin is online (configurable threshold)
    public function isOnline(): bool
    {
        $threshold = config('admin.online_threshold_minutes', 5);
        return $this->last_session_activity && 
               $this->last_session_activity->diffInMinutes(now()) < $threshold;
    }

    // JWT Interface Methods
    public function getJWTIdentifier()
    {
        return $this->uuid;
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'role' => 'admin',
            'email' => $this->email,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        
        static::creating(function ($admin) {
            $admin->uuid = $admin->uuid ?? Str::uuid()->toString();
        });
    }

    // Get security score (using config weights)
    public function getSecurityScore(): int
    {
        $weights = config('admin.security_score', [
            'two_factor' => 20,
            'email_verified' => 15,
            'phone_verified' => 15,
            'device_verification' => 20,
            'no_failed_attempts' => 15,
            'recent_review' => 15,
        ]);
        
        $score = 0;

        $score += $weights['two_factor'];
        if ($this->email_verified_at) $score += $weights['email_verified'];
        if ($this->phone_verified_at) $score += $weights['phone_verified'];
        if ($this->require_device_verification) $score += $weights['device_verification'];
        if ($this->failed_login_attempts === 0) $score += $weights['no_failed_attempts'];
        if ($this->last_security_review_at?->diffInDays(now()) < 30) {
            $score += $weights['recent_review'];
        }

        return min($score, 100);
    }
}
