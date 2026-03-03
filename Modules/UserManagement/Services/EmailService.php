<?php

namespace Modules\UserManagement\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailService
{
    /**
     * Send OTP email
     */
    public function sendOtpEmail(string $email, string $otp): bool
    {
        try {
            $subject = 'Your OTP Verification Code';
            $message = "Your One-Time Password (OTP) for email verification is: {$otp}\n\n";
            $message .= "This OTP will expire in 10 minutes.\n\n";
            $message .= "If you didn't request this OTP, please ignore this email.";

            Mail::raw($message, function ($mail) use ($email, $subject) {
                $mail->to($email)
                    ->subject($subject);
            });

            Log::info("OTP sent successfully to: {$email}");
            return true;

        } catch (\Exception $e) {
            Log::error("Failed to send OTP to {$email}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send HTML email with better formatting
     */
    public function sendOtpEmailHtml(string $email, string $otp): bool
    {
        try {
            $htmlContent = $this->generateOtpHtml($otp);

            Mail::html($htmlContent, function ($mail) use ($email) {
                $mail->to($email)
                    ->subject('Your OTP Verification Code');
            });

            Log::info("OTP HTML email sent successfully to: {$email}");
            return true;

        } catch (\Exception $e) {
            Log::error("Failed to send OTP HTML email to {$email}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate HTML content for OTP email
     */
    private function generateOtpHtml(string $otp): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>OTP Verification</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #007bff;'>OTP Verification Code</h2>
                
                <p>Hello,</p>
                
                <p>Your One-Time Password (OTP) for email verification is:</p>
                
                <div style='background-color: #f8f9fa; padding: 20px; text-align: center; margin: 20px 0; border-radius: 5px;'>
                    <span style='font-size: 24px; font-weight: bold; letter-spacing: 3px; color: #007bff;'>{$otp}</span>
                </div>
                
                <p>This OTP will expire in <strong>10 minutes</strong>.</p>
                
                <p>If you didn't request this OTP, please ignore this email.</p>
                
                <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                
                <p style='font-size: 12px; color: #666;'>
                    This is an automated message. Please do not reply to this email.
                </p>
            </div>
        </body>
        </html>";
    }
}
