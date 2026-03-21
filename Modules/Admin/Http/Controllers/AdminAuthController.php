<?php

namespace Modules\Admin\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Routing\Controller;
use Carbon\Carbon;
use Modules\Admin\Entities\Admin;
use Modules\Admin\Services\AdminSecurityService;

class AdminAuthController extends Controller
{
    /**
     * Send OTP for admin login
     */
    public function sendOTP(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:admins,email',
                'method' => 'sometimes|in:email,sms,both',
                'device_fingerprint' => 'required|string',
                'user_agent' => 'required|string',
                'ip' => 'required|ip'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $admin = Admin::where('email', $request->email)->first();

            if (!$admin) {
                // Always return success to prevent email enumeration
                return response()->json([
                    'success' => true,
                    'message' => 'If the email exists, an OTP has been sent'
                ]);
            }

            // Check if admin is active
            if (!$admin->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is deactivated'
                ], 403);
            }

            // Check if account is locked
            if ($admin->isLocked()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is temporarily locked. Please try again later.',
                    'locked_until' => $admin->locked_until
                ], 423);
            }

            // Check rate limiting
            if (!$admin->canRequestOTP()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many OTP requests. Please try again later.',
                    'otp_locked_until' => $admin->otp_locked_until
                ], 429);
            }

            // Generate OTP
            $otp = $admin->generateOTP();
            $admin->recordOTPRequest();

            // Prepare delivery methods
            $method = $request->method ?? $admin->preferred_otp_method;
            $deliveryMethods = [];

            if ($method === 'email' || $method === 'both') {
                $this->sendOTPEmail($admin, $otp);
                $deliveryMethods[] = 'email';
            }

            if ($method === 'sms' || $method === 'both') {
                if ($admin->phone) {
                    $this->sendOTPSMS($admin, $otp);
                    $deliveryMethods[] = 'sms';
                } else {
                    Log::warning("SMS OTP requested but no phone number for admin: {$admin->email}");
                }
            }

            // Record security event
            $admin->addSecurityEvent('otp_sent', [
                'method' => $method,
                'ip' => $request->ip,
                'device_fingerprint' => $request->device_fingerprint
            ]);

            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully',
                'delivery_methods' => $deliveryMethods,
                'expires_at' => $admin->otp_expires_at
            ]);

        } catch (\Exception $e) {
            Log::error('Admin OTP send error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP'
            ], 500);
        }
    }

    /**
     * Verify OTP and login admin
     */
    public function verifyOTP(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:admins,email',
                'otp' => 'required|string|size:6',
                'device_fingerprint' => 'required|string',
                'user_agent' => 'required|string',
                'ip' => 'required|ip',
                'location' => 'sometimes|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $admin = Admin::where('email', $request->email)->first();

            if (!$admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Check if admin is active
            if (!$admin->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is deactivated'
                ], 403);
            }

            // Check if account is locked
            if ($admin->isLocked()) {
                $admin->recordLoginAttempt([
                    'ip' => $request->ip,
                    'user_agent' => $request->user_agent,
                    'device_fingerprint' => $request->device_fingerprint,
                    'location' => $request->location,
                    'success' => false
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Account is temporarily locked',
                    'locked_until' => $admin->locked_until
                ], 423);
            }

            // Verify OTP
            if (!$admin->verifyOTP($request->otp)) {
                $admin->incrementFailedLoginAttempts();
                $admin->recordLoginAttempt([
                    'ip' => $request->ip,
                    'user_agent' => $request->user_agent,
                    'device_fingerprint' => $request->device_fingerprint,
                    'location' => $request->location,
                    'success' => false
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired OTP',
                    'attempts_remaining' => max(0, 5 - $admin->otp_attempts)
                ], 401);
            }

            // Check device verification
            $isNewDevice = false;
            if ($admin->require_device_verification && !$admin->isKnownDevice($request->device_fingerprint)) {
                $isNewDevice = true;
                
                // Send new device alert
                if ($admin->enable_login_notifications) {
                    $this->sendNewDeviceAlert($admin, $request);
                }
            }

            // Reset failed attempts
            $admin->resetFailedLoginAttempts();

            // Update login tracking
            $loginData = [
                'ip' => $request->ip,
                'user_agent' => $request->user_agent,
                'device_fingerprint' => $request->device_fingerprint,
                'location' => $request->location
            ];
            $admin->updateLoginTracking($loginData);

            // Add device to known devices
            if ($isNewDevice) {
                $admin->addKnownDevice($request->device_fingerprint);
            }

            // Record successful login
            $admin->recordLoginAttempt([
                ...$loginData,
                'success' => true
            ]);

            // Generate session token
            $sessionToken = $this->generateSessionToken($admin);
            
            // Add active session
            $sessionId = Str::uuid()->toString();
            $admin->addActiveSession($sessionId);

            // Record security event
            $admin->addSecurityEvent('login_success', [
                'ip' => $request->ip,
                'device_fingerprint' => $request->device_fingerprint,
                'new_device' => $isNewDevice,
                'session_id' => $sessionId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'admin' => [
                        'id' => $admin->id,
                        'uuid' => $admin->uuid,
                        'email' => $admin->email,
                        'first_name' => $admin->first_name,
                        'last_name' => $admin->last_name,
                        'full_name' => $admin->full_name,
                        'profile_photo_path' => $admin->profile_photo_path,
                        'is_verified' => $admin->is_verified,
                        'last_login_at' => $admin->last_login_at,
                        'security_score' => $admin->getSecurityScore()
                    ],
                    'token' => $sessionToken,
                    'session_id' => $sessionId,
                    'expires_at' => now()->addHours(24),
                    'new_device' => $isNewDevice,
                    'security_warnings' => $this->getSecurityWarnings($admin)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin OTP verification error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Login failed'
            ], 500);
        }
    }

    /**
     * Logout admin
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $sessionId = $request->header('X-Session-ID');
            $admin = $request->user();

            if ($admin && $sessionId) {
                $admin->removeActiveSession($sessionId);
                
                $admin->addSecurityEvent('logout', [
                    'ip' => $request->ip(),
                    'session_id' => $sessionId
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Logout successful'
            ]);

        } catch (\Exception $e) {
            Log::error('Admin logout error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Logout failed'
            ], 500);
        }
    }

    /**
     * Get admin profile with security info
     */
    public function profile(Request $request): JsonResponse
    {
        try {
            $admin = $request->user();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'admin' => [
                        'id' => $admin->id,
                        'uuid' => $admin->uuid,
                        'email' => $admin->email,
                        'first_name' => $admin->first_name,
                        'last_name' => $admin->last_name,
                        'full_name' => $admin->full_name,
                        'phone' => $admin->phone,
                        'profile_photo_path' => $admin->profile_photo_path,
                        'is_active' => $admin->is_active,
                        'is_verified' => $admin->is_verified,
                        'email_verified_at' => $admin->email_verified_at,
                        'phone_verified_at' => $admin->phone_verified_at,
                        'last_login_at' => $admin->last_login_at,
                        'last_successful_login_at' => $admin->last_successful_login_at,
                        'failed_login_attempts' => $admin->failed_login_attempts,
                        'is_locked' => $admin->isLocked(),
                        'locked_until' => $admin->locked_until,
                        'active_sessions_count' => count($admin->active_sessions ?? []),
                        'known_devices_count' => count($admin->known_devices ?? []),
                        'security_score' => $admin->getSecurityScore(),
                        'is_online' => $admin->isOnline(),
                        'security_settings' => $admin->security_settings,
                        'preferred_otp_method' => $admin->preferred_otp_method,
                        'enable_login_notifications' => $admin->enable_login_notifications,
                        'require_device_verification' => $admin->require_device_verification
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin profile error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get profile'
            ], 500);
        }
    }

    /**
     * Get login history
     */
    public function loginHistory(Request $request): JsonResponse
    {
        try {
            $admin = $request->user();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'login_history' => $admin->login_history ?? [],
                    'total_attempts' => count($admin->login_history ?? []),
                    'successful_logins' => count(array_filter($admin->login_history ?? [], fn($login) => $login['success'])),
                    'failed_logins' => count(array_filter($admin->login_history ?? [], fn($login) => !$login['success']))
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin login history error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get login history'
            ], 500);
        }
    }

    /**
     * Get security events
     */
    public function securityEvents(Request $request): JsonResponse
    {
        try {
            $admin = $request->user();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'security_events' => $admin->security_events ?? [],
                    'total_events' => count($admin->security_events ?? [])
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin security events error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get security events'
            ], 500);
        }
    }

    /**
     * Get active sessions
     */
    public function activeSessions(Request $request): JsonResponse
    {
        try {
            $admin = $request->user();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'active_sessions' => $admin->active_sessions ?? [],
                    'max_concurrent_sessions' => $admin->max_concurrent_sessions,
                    'current_session_id' => $request->header('X-Session-ID')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Admin active sessions error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get active sessions'
            ], 500);
        }
    }

    /**
     * Revoke a session
     */
    public function revokeSession(Request $request, string $sessionId): JsonResponse
    {
        try {
            $admin = $request->user();
            
            $admin->removeActiveSession($sessionId);
            
            $admin->addSecurityEvent('session_revoked', [
                'revoked_session_id' => $sessionId,
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Session revoked successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Admin session revoke error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke session'
            ], 500);
        }
    }

    /**
     * Send OTP email
     */
    private function sendOTPEmail(Admin $admin, string $otp): void
    {
        try {
            // Implement email sending logic here
            // This is a placeholder - implement your email service
            
            Log::info("OTP email sent to admin: {$admin->email}");
            
        } catch (\Exception $e) {
            Log::error("Failed to send OTP email to admin: {$admin->email} - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send OTP SMS
     */
    private function sendOTPSMS(Admin $admin, string $otp): void
    {
        try {
            // Implement SMS sending logic here
            // This is a placeholder - implement your SMS service
            
            Log::info("OTP SMS sent to admin: {$admin->phone}");
            
        } catch (\Exception $e) {
            Log::error("Failed to send OTP SMS to admin: {$admin->phone} - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send new device alert
     */
    private function sendNewDeviceAlert(Admin $admin, Request $request): void
    {
        try {
            // Implement new device alert logic here
            
            Log::info("New device alert sent to admin: {$admin->email}");
            
        } catch (\Exception $e) {
            Log::error("Failed to send new device alert: " . $e->getMessage());
        }
    }

    /**
     * Generate JWT session token
     */
    private function generateSessionToken(Admin $admin): string
    {
        $customClaims = [
            'session_id' => Str::uuid()->toString(),
            'jti' => Str::uuid()->toString(), // JWT ID for revocation
            'type' => 'session',
            'device_fingerprint' => request('device_fingerprint'),
            'ip' => request()->ip(),
        ];

        return JWTAuth::fromUser($admin, $customClaims);
    }

    /**
     * Get security warnings
     */
    private function getSecurityWarnings(Admin $admin): array
    {
        $warnings = [];

        if ($admin->failed_login_attempts > 0) {
            $warnings[] = 'Recent failed login attempts detected';
        }

        if (!$admin->email_verified_at) {
            $warnings[] = 'Email not verified';
        }

        if (!$admin->phone_verified_at && $admin->phone) {
            $warnings[] = 'Phone not verified';
        }

        if ($admin->last_security_review_at && $admin->last_security_review_at->diffInDays(now()) > 90) {
            $warnings[] = 'Security review overdue';
        }

        return $warnings;
    }
}
