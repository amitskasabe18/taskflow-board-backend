<?php

use Illuminate\Support\Facades\Route;
use Modules\Admin\Http\Controllers\AdminAuthController;
use Modules\Admin\Http\Controllers\AdminController;

/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your admin module. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('v1/admin')->group(function () {
    
    // Public authentication routes
    Route::prefix('auth')->group(function () {
        Route::post('/send-otp', [AdminAuthController::class, 'sendOTP']);
        Route::post('/verify-otp', [AdminAuthController::class, 'verifyOTP']);
    });

    // Protected admin routes
    Route::middleware(['jwt.auth:admin', 'jwt.refresh', 'throttle:60,1'])->group(function () {
        
        // Authentication routes
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AdminAuthController::class, 'logout']);
            Route::get('/profile', [AdminAuthController::class, 'profile']);
        });
        
        // Security and monitoring routes
        Route::prefix('security')->group(function () {
            Route::get('/login-history', [AdminAuthController::class, 'loginHistory']);
            Route::get('/security-events', [AdminAuthController::class, 'securityEvents']);
            Route::get('/active-sessions', [AdminAuthController::class, 'activeSessions']);
            Route::delete('/sessions/{sessionId}', [AdminAuthController::class, 'revokeSession']);
        });
        
        // Admin management routes
        Route::prefix('management')->group(function () {
            Route::get('/', [AdminController::class, 'index']);
            Route::post('/', [AdminController::class, 'store']);
            Route::get('/{uuid}', [AdminController::class, 'show']);
            Route::put('/{uuid}', [AdminController::class, 'update']);
            Route::delete('/{uuid}', [AdminController::class, 'destroy']);
            
            // Security management
            Route::put('/{uuid}/security-settings', [AdminController::class, 'updateSecuritySettings']);
            Route::post('/{uuid}/lock', [AdminController::class, 'lockAccount']);
            Route::post('/{uuid}/unlock', [AdminController::class, 'unlockAccount']);
            Route::post('/{uuid}/reset-failed-attempts', [AdminController::class, 'resetFailedAttempts']);
            Route::post('/{uuid}/force-logout', [AdminController::class, 'forceLogout']);
            Route::delete('/{uuid}/revoke-all-sessions', [AdminController::class, 'revokeAllSessions']);
            
            // Device management
            Route::get('/{uuid}/known-devices', [AdminController::class, 'getKnownDevices']);
            Route::delete('/{uuid}/known-devices/{deviceFingerprint}', [AdminController::class, 'removeKnownDevice']);
            Route::post('/{uuid}/clear-known-devices', [AdminController::class, 'clearKnownDevices']);
            
            // Verification management
            Route::post('/{uuid}/verify-email', [AdminController::class, 'verifyEmail']);
            Route::post('/{uuid}/verify-phone', [AdminController::class, 'verifyPhone']);
            Route::post('/{uuid}/send-verification-email', [AdminController::class, 'sendVerificationEmail']);
            Route::post('/{uuid}/send-verification-sms', [AdminController::class, 'sendVerificationSMS']);
        });
        
        // Analytics and reporting
        Route::prefix('analytics')->group(function () {
            Route::get('/dashboard', [AdminController::class, 'dashboard']);
            Route::get('/login-statistics', [AdminController::class, 'loginStatistics']);
            Route::get('/security-report', [AdminController::class, 'securityReport']);
            Route::get('/activity-logs', [AdminController::class, 'activityLogs']);
        });
    });
});
