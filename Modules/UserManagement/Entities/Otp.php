<?php

namespace Modules\UserManagement\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'otp',
        'expires_at',
        'verified',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified' => 'boolean',
    ];

    /**
     * Generate a new OTP for the given email.
     */
    public static function generate(string $email): self
    {
        // Delete any existing unverified OTPs for this email
        self::where('email', $email)->where('verified', false)->delete();

        return self::create([
            'email' => $email,
            'otp' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'expires_at' => now()->addMinutes(10), // OTP expires in 10 minutes
        ]);
    }

    /**
     * Verify OTP for the given email.
     */
    public static function verify(string $email, string $otp): bool
    {
        $otpRecord = self::where('email', $email)
            ->where('otp', $otp)
            ->where('verified', false)
            ->where('expires_at', '>', now())
            ->first();

        if ($otpRecord) {
            $otpRecord->verified = true;
            $otpRecord->save();
            return true;
        }

        return false;
    }

    /**
     * Check if email has a verified OTP.
     */
    public static function isVerified(string $email): bool
    {
        return self::where('email', $email)
            ->where('verified', true)
            ->where('expires_at', '>', now())
            ->exists();
    }
}
