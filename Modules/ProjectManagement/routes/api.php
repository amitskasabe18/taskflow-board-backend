<?php

use Illuminate\Support\Facades\Route;
use Modules\ProjectManagement\Http\Controllers\ProjectController;

/*
|--------------------------------------------------------------------------
| Project API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->prefix('v1/projects')->group(function () {
    Route::post('/', [ProjectController::class, 'store']); // Create project
    Route::get('/', [ProjectController::class, 'index']); // Get all projects
    
    // User-specific project endpoints (MUST be before /{uuid} routes)
    Route::get('/my', [ProjectController::class, 'myProjects']); // Get user's projects
    Route::get('/invitations/my', [ProjectController::class, 'getMyInvitations']); // Get user's pending invitations
    
    // User management endpoints (MUST be before /{uuid} routes)
    Route::get('/users/search', [ProjectController::class, 'searchUsers']); // Search users by email
    Route::post('/users/add-to-org', [ProjectController::class, 'addUserToOrganization']); // Add user to organization
    
    // Invitation acceptance/rejection (token-based, no auth on project needed)
    Route::post('/invitations/{token}/accept', [ProjectController::class, 'acceptInvitation']); // Accept invitation
    Route::post('/invitations/{token}/reject', [ProjectController::class, 'rejectInvitation']); // Reject invitation
    
    // Specific project endpoints (parameterized routes come AFTER specific routes)
    Route::get('/{uuid}', [ProjectController::class, 'show']); // Get specific project
    Route::get('/{uuid}/members', [ProjectController::class, 'getProjectMembers']); // Get project members
    Route::put('/{uuid}', [ProjectController::class, 'update']); // Update project
    Route::delete('/{uuid}', [ProjectController::class, 'destroy']); // Delete project
    Route::get('/{uuid}/role', [ProjectController::class, 'getUserRole']); // Get user's role in project
    
    // Project enrollment endpoints
    Route::post('/{uuid}/join', [ProjectController::class, 'join']); // Join project
    Route::delete('/{uuid}/leave', [ProjectController::class, 'leave']); // Leave project
    
    // Project invitation management
    Route::post('/{uuid}/invitations', [ProjectController::class, 'sendInvitation']); // Send invitation
    Route::get('/{uuid}/invitations', [ProjectController::class, 'getProjectInvitations']); // Get project invitations
    Route::delete('/{uuid}/invitations/{invitationUuid}', [ProjectController::class, 'cancelInvitation']); // Cancel invitation
    
    // Project-specific user management
    Route::post('/{project_uuid}/add-user', [ProjectController::class, 'addUserToProject']); // Add user to project
});
