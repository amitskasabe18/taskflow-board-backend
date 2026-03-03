<?php

use Illuminate\Support\Facades\Route;
use Modules\OrganizationManagement\Http\Controllers\OrganisationController;

/*
|--------------------------------------------------------------------------
| Organization API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Public route for creating organization during signup
Route::prefix('v1/organisations')->group(function () {
    Route::post('/create-for-signup', [OrganisationController::class, 'createForSignup']); // Public: Create org before signup
});

Route::middleware('auth:api')->prefix('v1/organisations')->group(function () {
    Route::post('/', [OrganisationController::class, 'store']); // Create organization
    Route::get('/', [OrganisationController::class, 'show']); // Get user's organization
    Route::put('/', [OrganisationController::class, 'update']); // Update organization
    Route::get('/check', [OrganisationController::class, 'check']); // Check if user has organization
    
    // Invitation routes
    Route::prefix('invitations')->group(function () {
        // Admin/Manager: Send and manage invitations
        Route::post('/', [OrganisationController::class, 'sendInvitation']); // Send invitation
        Route::get('/sent', [OrganisationController::class, 'getSentInvitations']); // Get all invitations sent by org
        Route::delete('/{uuid}', [OrganisationController::class, 'cancelInvitation']); // Cancel invitation
        
        // User: View and respond to invitations
        Route::get('/my', [OrganisationController::class, 'getMyInvitations']); // Get user's pending invitations
        Route::post('/{token}/accept', [OrganisationController::class, 'acceptInvitation']); // Accept invitation
        Route::post('/{token}/reject', [OrganisationController::class, 'rejectInvitation']); // Reject invitation
    });
});
