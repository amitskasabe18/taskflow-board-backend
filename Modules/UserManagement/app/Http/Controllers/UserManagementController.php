<?php

namespace Modules\UserManagement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Helpers\ApiResponse;
use Modules\UserManagement\Services\OtpService;
use Modules\TicketManagement\Entities\Ticket;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;

class UserManagementController extends Controller
{
    protected $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        // Check if user already exists with this email
        $existingUser = User::where('email', $request->email)->first();
        if ($existingUser) {
            return ApiResponse::error(null, 'A user with this email already exists. Please login instead.', 409);
        }

        $result = $this->otpService->sendOtp($request->email);

        if ($result['success']) {
            return ApiResponse::success([
                'message' => $result['message'],
                'expires_at' => $result['expires_at'],
                'email' => $result['email'],
                'otp' => $result['otp'] // Only shown in local environment
            ], $result['message']);
        }

        return ApiResponse::error(null, $result['message'], 500);
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|digits_between:4,8'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $result = $this->otpService->verifyOtp($request->email, $request->otp);

        if ($result['success']) {
            return ApiResponse::success(null, $result['message']);
        }

        return ApiResponse::error(null, $result['message'], 400);
    }
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return ApiResponse::error(null, 'Invalid credentials', 401);
        }

        $user = auth('api')->user();

        $userData = [
            'user' => $user,
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60 // minutes to seconds
        ];

        return ApiResponse::success($userData, 'Login successful');
    }

    public function getUsers($projectId)
    {
        // If projectId is "me", return current user
        if ($projectId === 'me') {
            $user = \Illuminate\Support\Facades\Auth::user();
            return ApiResponse::success([$user], 'Current user retrieved successfully');
        }
        
        // Get users for a specific project by checking project_user relationships
        // Handle both UUID and numeric project IDs
        if (is_numeric($projectId)) {
            $project = \Modules\ProjectManagement\Entities\Project::find($projectId);
        } else {
            $project = \Modules\ProjectManagement\Entities\Project::where('uuid', $projectId)->first();
        }
        
        if (!$project) {
            return ApiResponse::error('Project not found', 404);
        }
        
        $users = $project->users()->get();
        return ApiResponse::success($users, 'Users retrieved successfully');
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'organisation_uuid' => 'nullable|string|exists:organisations,uuid', // Organization UUID is now optional
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        // Check if user already exists with this email
        $existingUser = User::where('email', $request->email)->first();
        if ($existingUser) {
            return ApiResponse::error(null, 'A user with this email already exists. Please login instead.', 409);
        }

        // Check if email is verified via OTP
        if (!$this->otpService->isEmailVerified($request->email)) {
            return ApiResponse::error(null, 'Email not verified. Please verify OTP first.', 422);
        }

        // Determine organization and role based on whether organisation_uuid is provided
        $organisationId = null;
        $role = 'member'; // Default role for individual users

        if ($request->organisation_uuid) {
            // Find the organization
            $organisation = \Modules\OrganizationManagement\Entities\Organisation::where('uuid', $request->organisation_uuid)->first();

            if (!$organisation) {
                return ApiResponse::error(null, 'Organization not found.', 404);
            }

            $organisationId = $organisation->id;
            $role = 'admin'; // User who creates org becomes admin
        }

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'organisation_id' => $organisationId, // Link user to organization (or null)
            'role' => $role, // 'admin' if creating org, 'user' if individual
            'is_active' => true,
        ]);

        // Create JWT token for the newly registered user
        $token = JWTAuth::fromUser($user);

        // Load the user with organisation relationship
        $user->load('organisation');

        $userData = [
            'user' => $user,
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60 // minutes to seconds
        ];

        return ApiResponse::success($userData, 'Register successful', 201);
    }

    public function getMyStatistics()
    {
        $me = Auth::user();
        // from ticket table, get count of tickets created by me, assigned to me, and resolved by me
        $createdTickets = Ticket::where('reporter_id', $me->id)->count();
        $assignedTickets = Ticket::where('assignee_id', $me->id)->count();
        $resolvedTickets = Ticket::where('assignee_id', $me->id)->whereNotNull('resolved_at')->count();
        
        return ApiResponse::success([
            'created_tickets' => $createdTickets,
            'assigned_tickets' => $assignedTickets,
            'resolved_tickets' => $resolvedTickets
        ], 'My statistics retrieved successfully');
    }

    public function me(Request $request)
    {
        $user = $request->user();
        return ApiResponse::success($user, 'User retrieved successfully');
    }

    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return ApiResponse::success(null, 'Logout successful');
    }
}
