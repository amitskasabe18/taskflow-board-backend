<?php

namespace Modules\Admin\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Modules\Admin\Entities\Admin;

class AdminSecurityService
{
    /**
     * Get device fingerprint from request
     */
    public static function getDeviceFingerprint($request): string
    {
        $userAgent = $request->userAgent();
        $ip = $request->ip();
        
        // Create a unique fingerprint based on various factors
        $fingerprint = md5($userAgent . $ip . $request->header('Accept-Language'));
        
        return $fingerprint;
    }

    /**
     * Get location information from IP
     */
    public static function getLocationFromIP(string $ip): array
    {
        try {
            // Use a geolocation service (you can use any service you prefer)
            // This is a placeholder implementation
            $response = Http::timeout(5)->get("http://ip-api.com/json/{$ip}");
            
            if ($response->successful()) {
                $data = $response->json();
                
                return [
                    'country' => $data['country'] ?? 'Unknown',
                    'country_code' => $data['countryCode'] ?? 'Unknown',
                    'region' => $data['regionName'] ?? 'Unknown',
                    'city' => $data['city'] ?? 'Unknown',
                    'latitude' => $data['lat'] ?? null,
                    'longitude' => $data['lon'] ?? null,
                    'timezone' => $data['timezone'] ?? 'Unknown',
                    'isp' => $data['isp'] ?? 'Unknown'
                ];
            }
        } catch (\Exception $e) {
            Log::error("Failed to get location for IP {$ip}: " . $e->getMessage());
        }

        return [
            'country' => 'Unknown',
            'country_code' => 'Unknown',
            'region' => 'Unknown',
            'city' => 'Unknown',
            'latitude' => null,
            'longitude' => null,
            'timezone' => 'Unknown',
            'isp' => 'Unknown'
        ];
    }

    /**
     * Detect suspicious login patterns
     */
    public static function detectSuspiciousActivity(Admin $admin, array $loginData): array
    {
        $warnings = [];
        $riskScore = 0;

        // Check for new IP address
        $lastLoginIP = $admin->last_login_ip['ip'] ?? null;
        if ($lastLoginIP && $lastLoginIP !== $loginData['ip']) {
            $warnings[] = 'Login from new IP address';
            $riskScore += 20;
        }

        // Check for new device
        if (!$admin->isKnownDevice($loginData['device_fingerprint'])) {
            $warnings[] = 'Login from new device';
            $riskScore += 25;
        }

        // Check for unusual location
        $lastLocation = $admin->last_login_location;
        $currentLocation = $loginData['location'] ?? null;
        
        if ($lastLocation && $currentLocation) {
            $distance = $this->calculateDistance(
                $lastLocation['latitude'] ?? 0,
                $lastLocation['longitude'] ?? 0,
                $currentLocation['latitude'] ?? 0,
                $currentLocation['longitude'] ?? 0
            );

            // If login is from more than 1000km away in less than 1 hour
            if ($distance > 1000 && $admin->last_login_at && $admin->last_login_at->diffInHours(now()) < 1) {
                $warnings[] = 'Impossible travel detected';
                $riskScore += 40;
            }
        }

        // Check for multiple failed attempts
        if ($admin->failed_login_attempts > 3) {
            $warnings[] = 'Multiple failed login attempts';
            $riskScore += 15;
        }

        // Check for rapid OTP requests
        if ($admin->otp_requests_count > 5) {
            $warnings[] = 'Rapid OTP requests';
            $riskScore += 10;
        }

        // Check for suspicious user agent
        $userAgent = $loginData['user_agent'] ?? '';
        if ($this->isSuspiciousUserAgent($userAgent)) {
            $warnings[] = 'Suspicious user agent detected';
            $riskScore += 15;
        }

        // Check for VPN/Proxy
        if ($this->isVPNOrProxy($loginData['ip'])) {
            $warnings[] = 'VPN or proxy detected';
            $riskScore += 10;
        }

        return [
            'warnings' => $warnings,
            'risk_score' => min($riskScore, 100),
            'risk_level' => $this->getRiskLevel($riskScore)
        ];
    }

    /**
     * Calculate distance between two coordinates
     */
    private static function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        if ($lat1 == 0 || $lon1 == 0 || $lat2 == 0 || $lon2 == 0) {
            return 0;
        }

        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + 
                cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;

        return $miles * 1.609344; // Convert to kilometers
    }

    /**
     * Check if user agent is suspicious
     */
    private static function isSuspiciousUserAgent(string $userAgent): bool
    {
        $suspiciousPatterns = [
            'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget',
            'python', 'java', 'perl', 'ruby', 'php', 'node'
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP is from VPN or proxy
     */
    private static function isVPNOrProxy(string $ip): bool
    {
        try {
            // Use a VPN/proxy detection service
            // This is a placeholder implementation
            $response = Http::timeout(5)->get("http://ip-api.com/json/{$ip}?fields=proxy,hosting");
            
            if ($response->successful()) {
                $data = $response->json();
                return ($data['proxy'] ?? false) || ($data['hosting'] ?? false);
            }
        } catch (\Exception $e) {
            Log::error("Failed to check VPN/proxy for IP {$ip}: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Get risk level based on score
     */
    private static function getRiskLevel(int $score): string
    {
        if ($score >= 70) {
            return 'high';
        } elseif ($score >= 40) {
            return 'medium';
        } elseif ($score >= 20) {
            return 'low';
        }
        
        return 'minimal';
    }

    /**
     * Generate secure OTP
     */
    public static function generateSecureOTP(): string
    {
        // Use cryptographically secure random number generator
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Validate OTP format
     */
    public static function validateOTPFormat(string $otp): bool
    {
        return preg_match('/^\d{6}$/', $otp);
    }

    /**
     * Check for brute force patterns
     */
    public static function detectBruteForce(string $ip): bool
    {
        $key = "brute_force_admin_{$ip}";
        $attempts = Cache::get($key, 0);
        
        if ($attempts >= 10) {
            return true;
        }

        // Increment attempts
        Cache::put($key, $attempts + 1, now()->addMinutes(15));
        
        return false;
    }

    /**
     * Check for distributed brute force
     */
    public static function detectDistributedBruteForce(string $email): bool
    {
        $key = "distributed_brute_force_admin_{$email}";
        $attempts = Cache::get($key, 0);
        
        if ($attempts >= 20) {
            return true;
        }

        // Increment attempts
        Cache::put($key, $attempts + 1, now()->addMinutes(30));
        
        return false;
    }

    /**
     * Get security recommendations
     */
    public static function getSecurityRecommendations(Admin $admin): array
    {
        $recommendations = [];

        if (!$admin->email_verified_at) {
            $recommendations[] = 'Verify your email address';
        }

        if (!$admin->phone_verified_at && $admin->phone) {
            $recommendations[] = 'Verify your phone number';
        }

        if (!$admin->require_device_verification) {
            $recommendations[] = 'Enable device verification';
        }

        if (!$admin->enable_login_notifications) {
            $recommendations[] = 'Enable login notifications';
        }

        if ($admin->preferred_otp_method === 'email') {
            $recommendations[] = 'Consider using SMS OTP for additional security';
        }

        if (count($admin->known_devices ?? []) > 10) {
            $recommendations[] = 'Review and remove unknown devices';
        }

        if ($admin->last_security_review_at && $admin->last_security_review_at->diffInDays(now()) > 90) {
            $recommendations[] = 'Perform a security review';
        }

        return $recommendations;
    }

    /**
     * Log security event
     */
    public static function logSecurityEvent(string $event, array $data = []): void
    {
        $logData = [
            'timestamp' => now()->toISOString(),
            'event' => $event,
            'data' => $data
        ];

        Log::channel('security')->info('Admin Security Event', $logData);
    }

    /**
     * Check for account takeover indicators
     */
    public static function checkAccountTakeoverIndicators(Admin $admin, array $loginData): array
    {
        $indicators = [];
        $severity = 'low';

        // Check for multiple concurrent sessions from different IPs
        $activeSessions = $admin->active_sessions ?? [];
        $uniqueIPs = [];
        
        foreach ($activeSessions as $session) {
            // This would require storing IP with sessions - simplified for now
            $uniqueIPs[] = $session['ip'] ?? 'unknown';
        }

        if (count(array_unique($uniqueIPs)) > 3) {
            $indicators[] = 'Multiple concurrent sessions from different IPs';
            $severity = 'medium';
        }

        // Check for rapid password changes (if implemented)
        // Check for unusual login times
        $currentHour = now()->hour;
        $lastLoginHour = $admin->last_login_at?->hour;
        
        if ($lastLoginHour && abs($currentHour - $lastLoginHour) > 12) {
            $indicators[] = 'Login at unusual time';
            $severity = 'low';
        }

        return [
            'indicators' => $indicators,
            'severity' => $severity
        ];
    }
}
