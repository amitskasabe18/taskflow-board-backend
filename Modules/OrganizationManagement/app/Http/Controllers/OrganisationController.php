<?php

namespace Modules\OrganizationManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\OrganizationManagement\Entities\Organisation;
use Modules\OrganizationManagement\Entities\OrganisationInvitation;
use Modules\UserManagement\Entities\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OrganisationController extends Controller
{
    /**
     * Create a new organization (public endpoint for signup flow).
     * This is called before user registration.
     */
    public function createForSignup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'user_type' => 'required|in:private,governmental,other',
            'website_url' => 'nullable|url|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Create organization
            $organisation = Organisation::create([
                'uuid' => Str::uuid(),
                'name' => $request->name,
                'description' => $request->description,
                'status' => 'active',
                'user_type' => $request->user_type,
                'plan' => 'free',
                'is_trial' => true,
                'trial_end_date' => now()->addDays(30), // 30-day trial
                'website_url' => $request->website_url,
                'phone' => $request->phone,
                'address' => $request->address,
                'city' => $request->city,
                'state' => $request->state,
                'country' => $request->country,
                'postal_code' => $request->postal_code,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Organization created successfully. You can now complete your registration.',
                'data' => [
                    'organisation' => [
                        'id' => $organisation->id,
                        'uuid' => $organisation->uuid,
                        'name' => $organisation->name,
                        'description' => $organisation->description,
                        'user_type' => $organisation->user_type,
                        'plan' => $organisation->plan,
                    ],
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create organization',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Create a new organization for the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'user_type' => 'required|in:private,governmental,other',
            'website_url' => 'nullable|url|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = Auth::user();
            
            // Check if user already has an organization
            if ($user->organisation_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already belong to an organization',
                ], 403);
            }

            // Create organization
            $organisation = Organisation::create([
                'uuid' => Str::uuid(),
                'name' => $request->name,
                'description' => $request->description,
                'status' => 'active',
                'user_type' => $request->user_type,
                'plan' => 'free',
                'is_trial' => true,
                'trial_end_date' => now()->addDays(30), // 30-day trial
                'website_url' => $request->website_url,
                'phone' => $request->phone,
                'address' => $request->address,
                'city' => $request->city,
                'state' => $request->state,
                'country' => $request->country,
                'postal_code' => $request->postal_code,
            ]);

            // Update user to belong to this organization and make them admin
            $user->organisation_id = $organisation->id;
            $user->role = 'admin'; // Organization creator becomes admin
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Organization created successfully',
                'data' => [
                    'organisation' => $organisation,
                    'user' => User::find($user->id),
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create organization',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get the authenticated user's organization.
     */
    public function show(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user->organisation_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No organization found',
                ], 404);
            }

            $organisation = $user->organisation;

            return response()->json([
                'success' => true,
                'message' => 'Organization retrieved successfully',
                'data' => [
                    'organisation' => $organisation,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve organization',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the authenticated user's organization.
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'website_url' => 'nullable|url|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = Auth::user();
            
            if (!$user->organisation_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No organization found',
                ], 404);
            }

            $organisation = $user->organisation;
            
            // Update organization
            $organisation->update($request->only([
                'name',
                'description',
                'website_url',
                'phone',
                'address',
                'city',
                'state',
                'country',
                'postal_code',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Organization updated successfully',
                'data' => [
                    'organisation' => Organisation::find($organisation->id),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update organization',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Check if user has organization.
     */
    public function check(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $hasOrganisation = $user->organisation_id !== null;
            
            return response()->json([
                'success' => true,
                'message' => 'Organization status checked',
                'data' => [
                    'has_organisation' => $hasOrganisation,
                    'organisation' => $hasOrganisation ? $user->organisation : null,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check organization status',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Send invitation to user to join organization.
     */
    public function sendInvitation(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user->organisation_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You must belong to an organization to send invitations',
                ], 403);
            }

            // Check if user has admin or manager role
            if (!$user->canManageOrganization()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only admin or manager can send invitations',
                    'data' => [
                        'required_roles' => ['admin', 'manager'],
                        'your_role' => $user->role ?? 'member',
                    ],
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'email' => 'required|string|email|max:255',
                'role' => 'sometimes|string|in:admin,manager,member,viewer',
                'expires_in_hours' => 'sometimes|integer|min:1|max:168', // Max 7 days
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Check if user with email exists
            $targetUser = User::where('email', $request->email)->first();

            // If user exists and already in this organization
            if ($targetUser && $targetUser->organisation_id === $user->organisation_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is already in your organization',
                ], 400);
            }

            // Check if there's already a pending invitation
            $existingInvitation = OrganisationInvitation::where('email', $request->email)
                ->where('organisation_id', $user->organisation_id)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->first();

            if ($existingInvitation) {
                return response()->json([
                    'success' => false,
                    'message' => 'An active invitation already exists for this email',
                    'data' => [
                        'invitation' => [
                            'email' => $existingInvitation->email,
                            'role' => $existingInvitation->role,
                            'expires_at' => $existingInvitation->expires_at,
                        ],
                    ],
                ], 400);
            }

            // Create invitation
            $expiresInHours = $request->expires_in_hours ?? 72; // Default 3 days
            $invitation = OrganisationInvitation::create([
                'organisation_id' => $user->organisation_id,
                'email' => $request->email,
                'token' => OrganisationInvitation::generateToken(),
                'invited_by' => $user->id,
                'role' => $request->role ?? 'member',
                'status' => 'pending',
                'expires_at' => now()->addHours($expiresInHours),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Invitation sent successfully',
                'data' => [
                    'invitation' => [
                        'uuid' => $invitation->uuid,
                        'email' => $invitation->email,
                        'token' => $invitation->token,
                        'role' => $invitation->role,
                        'organisation' => $user->organisation->name,
                        'invited_by' => $user->first_name . ' ' . $user->last_name,
                        'expires_at' => $invitation->expires_at,
                    ],
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send invitation',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get all invitations sent by the organization.
     */
    public function getSentInvitations(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user->organisation_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You must belong to an organization',
                ], 403);
            }

            $invitations = OrganisationInvitation::where('organisation_id', $user->organisation_id)
                ->with(['inviter:id,first_name,last_name,email'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($invitation) {
                    return [
                        'uuid' => $invitation->uuid,
                        'email' => $invitation->email,
                        'role' => $invitation->role,
                        'status' => $invitation->status,
                        'invited_by' => $invitation->inviter->first_name . ' ' . $invitation->inviter->last_name,
                        'expires_at' => $invitation->expires_at,
                        'accepted_at' => $invitation->accepted_at,
                        'rejected_at' => $invitation->rejected_at,
                        'created_at' => $invitation->created_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Invitations retrieved successfully',
                'data' => [
                    'invitations' => $invitations,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve invitations',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get pending invitations for the authenticated user.
     */
    public function getMyInvitations(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $invitations = OrganisationInvitation::where('email', $user->email)
                ->valid()
                ->with(['organisation:id,name,description', 'inviter:id,first_name,last_name,email'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($invitation) {
                    return [
                        'uuid' => $invitation->uuid,
                        'token' => $invitation->token,
                        'organisation' => [
                            'id' => $invitation->organisation->id,
                            'name' => $invitation->organisation->name,
                            'description' => $invitation->organisation->description,
                        ],
                        'role' => $invitation->role,
                        'invited_by' => $invitation->inviter->first_name . ' ' . $invitation->inviter->last_name,
                        'invited_by_email' => $invitation->inviter->email,
                        'expires_at' => $invitation->expires_at,
                        'created_at' => $invitation->created_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Your invitations retrieved successfully',
                'data' => [
                    'invitations' => $invitations,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve invitations',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Accept invitation to join organization.
     */
    public function acceptInvitation(Request $request, string $token): JsonResponse
    {
        try {
            $user = Auth::user();

            $invitation = OrganisationInvitation::where('token', $token)
                ->where('email', $user->email)
                ->first();

            if (!$invitation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invitation not found',
                ], 404);
            }

            // Check if invitation is expired
            if ($invitation->isExpired()) {
                $invitation->markAsExpired();
                return response()->json([
                    'success' => false,
                    'message' => 'Invitation has expired',
                ], 400);
            }

            // Check if invitation is still pending
            if ($invitation->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Invitation has already been ' . $invitation->status,
                ], 400);
            }

            // Check if user already belongs to an organization
            if ($user->organisation_id && $user->organisation_id !== $invitation->organisation_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already belong to another organization. Please leave your current organization first.',
                ], 400);
            }

            // Accept invitation
            $user->organisation_id = $invitation->organisation_id;
            $user->role = $invitation->role;
            $user->save();

            $invitation->markAsAccepted();

            return response()->json([
                'success' => true,
                'message' => 'Invitation accepted successfully. Welcome to the organization!',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'role' => $user->role,
                        'organisation_id' => $user->organisation_id,
                    ],
                    'organisation' => $invitation->organisation,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to accept invitation',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Reject invitation to join organization.
     */
    public function rejectInvitation(Request $request, string $token): JsonResponse
    {
        try {
            $user = Auth::user();

            $invitation = OrganisationInvitation::where('token', $token)
                ->where('email', $user->email)
                ->first();

            if (!$invitation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invitation not found',
                ], 404);
            }

            // Check if invitation is still pending
            if ($invitation->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Invitation has already been ' . $invitation->status,
                ], 400);
            }

            $invitation->markAsRejected();

            return response()->json([
                'success' => true,
                'message' => 'Invitation rejected successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject invitation',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Cancel/Revoke an invitation (for organization admins/managers).
     */
    public function cancelInvitation(Request $request, string $uuid): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user->organisation_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You must belong to an organization',
                ], 403);
            }

            if (!$user->canManageOrganization()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only admin or manager can cancel invitations',
                ], 403);
            }

            $invitation = OrganisationInvitation::where('uuid', $uuid)
                ->where('organisation_id', $user->organisation_id)
                ->first();

            if (!$invitation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invitation not found',
                ], 404);
            }

            if ($invitation->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot cancel invitation that is ' . $invitation->status,
                ], 400);
            }

            $invitation->delete();

            return response()->json([
                'success' => true,
                'message' => 'Invitation cancelled successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel invitation',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
