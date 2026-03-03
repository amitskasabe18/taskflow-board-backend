<?php

namespace Modules\UserManagement\Services;

use Modules\UserManagement\Entities\Otp;
use Illuminate\Support\Facades\Log;

class OtpService
{
    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Generate and send OTP to email.
     */
    public function sendOtp(string $email): array
    {
        $otp = Otp::generate($email);
        
        // Send OTP via email
        $emailSent = $this->emailService->sendOtpEmailHtml($email, $otp->otp);
        
        if ($emailSent) {
            Log::info("OTP sent successfully to: {$email}");
            
            return [
                'success' => true,
                'message' => 'OTP sent successfully to your email',
                'otp' => app()->environment('local') ? $otp->otp : null, // Show OTP only in local env
                'expires_at' => $otp->expires_at->toISOString(),
                'email' => $email
            ];
        } else {
            Log::error("Failed to send OTP to: {$email}");
            
            return [
                'success' => false,
                'message' => 'Failed to send OTP. Please try again.',
                'otp' => app()->environment('local') ? $otp->otp : null, // Show OTP in local env for testing
                'expires_at' => $otp->expires_at->toISOString(),
                'email' => $email
            ];
        }
    }

    /**
     * Verify OTP.
     */
    public function verifyOtp(string $email, string $otp): array
    {
        if (Otp::verify($email, $otp)) {
            return [
                'success' => true,
                'message' => 'OTP verified successfully'
            ];
        }

        return [
            'success' => false,
            'message' => 'Invalid or expired OTP'
        ];
    }

    /**
     * Check if email is verified.
     */
    public function isEmailVerified(string $email): bool
    {
        return Otp::isVerified($email);
    }
}
