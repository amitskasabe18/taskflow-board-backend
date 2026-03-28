<?php

use Illuminate\Support\Facades\Route;
use Modules\UserManagement\Http\Controllers\UserManagementController;

Route::prefix('v1')->group(function () {
    Route::prefix('users')->group(function () {
        Route::prefix('auth')->group(function () {
            Route::post('send-otp', [UserManagementController::class, 'sendOtp']);
            Route::post('verify-otp', [UserManagementController::class, 'verifyOtp']);
            Route::post('register', [UserManagementController::class, 'register']);
            Route::post('login', [UserManagementController::class, 'login']);
            Route::post('logout', [UserManagementController::class, 'logout']);
        });
        Route::middleware('auth:api')->group(function () {
            Route::get('roles', [UserManagementController::class, 'getRoles']); // Get all roles - BEFORE {projectId} to prevent conflicts
            Route::get('{projectId}', [UserManagementController::class, 'getUsers']);
            Route::get('me', [UserManagementController::class, 'me']);
            Route::prefix('statistics')->group(function () {
                Route::get('my', [UserManagementController::class, 'getMyStatistics']);
            });
        });
    });
});
