<?php

namespace Modules\Admin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Admin\Entities\Admin;

class AdminAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        $sessionId = $request->header('X-Session-ID');

        if (!$token || !$sessionId) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        try {
            // Decode and validate token
            $payload = json_decode(base64_decode($token), true);
            
            if (!$payload || 
                !isset($payload['admin_id']) || 
                !isset($payload['expires_at']) ||
                $payload['expires_at'] < now()->timestamp) {
                
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ], 401);
            }

            // Get admin
            $admin = Admin::find($payload['admin_id']);
            
            if (!$admin || !$admin->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin not found or inactive'
                ], 401);
            }

            // Check if session is still active
            $activeSessions = $admin->active_sessions ?? [];
            $sessionExists = false;
            
            foreach ($activeSessions as $session) {
                if ($session['session_id'] === $sessionId) {
                    $sessionExists = true;
                    
                    // Update last activity
                    $session['last_activity'] = now()->toISOString();
                    break;
                }
            }

            if (!$sessionExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session expired or invalid'
                ], 401);
            }

            // Update session activity
            $admin->active_sessions = $activeSessions;
            $admin->last_session_activity = now();
            $admin->save();

            // Set admin for request
            Auth::setUser($admin);
            $request->setUserResolver(function () use ($admin) {
                return $admin;
            });

            // Add security headers
            $response = $next($request);
            
            // Add security headers
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->headers->set('X-Frame-Options', 'DENY');
            $response->headers->set('X-XSS-Protection', '1; mode=block');
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

            return $response;

        } catch (\Exception $e) {
            Log::error('Admin authentication error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed'
            ], 401);
        }
    }
}
