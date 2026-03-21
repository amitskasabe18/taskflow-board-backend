<?php

namespace Modules\ProjectManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\OrganizationManagement\Entities\Organisation;
use Modules\ProjectManagement\Entities\ProjectInvitation;
use Modules\UserManagement\Entities\User;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;

class ProjectController extends Controller
{
    /**
     * Create a new project.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Validate request data
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'shortcode' => 'nullable|string|max:10|unique:projects,shortcode',
                'description' => 'nullable|string|max:2000',
                'status' => 'sometimes|in:active,completed,archived,on_hold',
                'priority' => 'sometimes|in:low,medium,high,urgent,critical',
                'start_date' => 'sometimes|date|after_or_equal:today',
                'end_date' => 'sometimes|date|after:start_date',
                'budget' => 'sometimes|numeric|min:0|max:9999999999.99',
                'currency' => 'sometimes|string|size:3',
                'metadata' => 'sometimes|array',
                'team_members' => 'sometimes|array',
                'team_members.*' => 'exists:users,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Check if user belongs to an organization
            if (!$user->organisation_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You must belong to an organization to create projects',
                ], 403);
            }

            // Create the project
            $project = DB::table('projects')->insert([
                'uuid' => Str::uuid(),
                'name' => $request->name,
                'shortcode' => $request->shortcode,
                'description' => $request->description,
                'status' => $request->status ?? 'active',
                'priority' => $request->priority ?? 'medium',
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'budget' => $request->budget,
                'currency' => $request->currency ?? 'USD',
                'metadata' => $request->metadata,
                'organisation_id' => $user->organisation_id,
                'created_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Get the inserted project ID
            $projectId = DB::getPdo()->lastInsertId();

            // Add team members if provided
            if ($request->has('team_members') && is_array($request->team_members)) {
                $teamMembers = [];
                foreach ($request->team_members as $memberId) {
                    $teamMembers[] = [
                        'project_id' => $projectId,
                        'user_id' => $memberId,
                        'role' => 'member',
                        'joined_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                
                // Add the creator as a manager
                $teamMembers[] = [
                    'project_id' => $projectId,
                    'user_id' => $user->id,
                    'role' => 'manager',
                    'joined_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                
                DB::table('project_user')->insert($teamMembers);
            } else {
                // Add only the creator as manager
                DB::table('project_user')->insert([
                    'project_id' => $projectId,
                    'user_id' => $user->id,
                    'role' => 'manager',
                    'joined_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Get the created project for response
            $project = DB::table('projects')->where('id', $projectId)->first();

            return response()->json([
                'success' => true,
                'message' => 'Project created successfully',
                'data' => [
                    'project' => $project,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create project',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get all projects for the authenticated user's organization.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user->organisation_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You must belong to an organization to view projects',
                ], 403);
            }

            // Get projects where user is a member using pure database query
            $projects = DB::table('projects')
                ->join('project_user', 'projects.id', '=', 'project_user.project_id')
                ->where('project_user.user_id', $user->id)
                ->where('projects.organisation_id', $user->organisation_id)
                ->select('projects.*', 'project_user.role', 'project_user.joined_at')
                ->orderBy('project_user.joined_at', 'desc')
                ->paginate($request->per_page ?? 15);

            return response()->json([
                'success' => true,
                'message' => 'Projects retrieved successfully',
                'data' => [
                    'projects' => $projects,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve projects',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get projects where user is involved.
     */
    public function myProjects(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user->organisation_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You must belong to an organization to view projects',
                ], 403);
            }

            // Get projects where user is a member using pure database query
            $projects = DB::table('projects')
                ->join('project_user', 'projects.id', '=', 'project_user.project_id')
                ->where('project_user.user_id', $user->id)
                ->where('projects.organisation_id', $user->organisation_id)
                ->select('projects.*', 'project_user.role as user_role', 'project_user.joined_at as user_joined_at')
                ->orderBy('project_user.joined_at', 'desc')
                ->paginate($request->per_page ?? 15);

            // Load relationships for response
            $projects->getCollection()->transform(function ($project) {
                $project->users = DB::table('users')
                    ->join('project_user', 'users.id', '=', 'project_user.user_id')
                    ->where('project_user.project_id', $project->id)
                    ->select('users.*', 'project_user.role', 'project_user.joined_at')
                    ->get();
                
                $project->created_by = DB::table('users')
                    ->where('users.id', $project->created_by)
                    ->first();
                
                return $project;
            });

            return response()->json([
                'success' => true,
                'message' => 'Your projects retrieved successfully',
                'data' => [
                    'projects' => $projects,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve your projects',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get user's role in a specific project.
     */
    public function getUserRole(string $uuid): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $project = DB::table('projects')
                ->where('uuid', $uuid)
                ->first();

            // Check if user belongs to same organization
            if (!$project || $project->organisation_id !== $user->organisation_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found',
                ], 404);
            }

            // Find user in the project users collection
            $userInProject = DB::table('project_user')
                ->where('project_id', $project->id)
                ->where('user_id', $user->id)
                ->first();

            if (!$userInProject) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a member of this project',
                ], 404);
            }

            // Get user details
            $userDetails = DB::table('users')
                ->where('id', $userInProject->user_id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Your role in project retrieved successfully',
                'data' => [
                    'project' => [
                        'uuid' => $project->uuid,
                        'name' => $project->name,
                    ],
                    'user' => [
                        'id' => $userInProject->user_id,
                        'name' => $userDetails->name,
                        'email' => $userDetails->email,
                        'role' => $userInProject->role,
                        'joined_at' => $userInProject->joined_at,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user role',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get a specific project.
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $user = Auth::user();

            $project = \Modules\ProjectManagement\Entities\Project::with(['users', 'createdBy'])
                ->where('uuid', $uuid)
                ->where('organisation_id', $user->organisation_id)
                ->first();

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Project retrieved successfully',
                'data' => [
                    'project' => $project,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve project',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get project members.
     */
    public function getProjectMembers(string $uuid): JsonResponse
    {
        try {
            $user = Auth::user();

            // Check if project exists and user has access
            $project = \Modules\ProjectManagement\Entities\Project::where('uuid', $uuid)
                ->where('organisation_id', $user->organisation_id)
                ->first();

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found',
                ], 404);
            }

            // Check if user is a member of this project
            $isMember = $project->users()
                ->where('user_id', $user->id)
                ->exists();

            if (!$isMember && $project->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to view project members',
                ], 403);
            }

            // Get all project members with their roles and join dates
            $members = $project->users()
                ->select([
                    'users.id',
                    'users.uuid',
                    'users.first_name',
                    'users.last_name',
                    'users.email',
                    'users.profile_photo_path',
                    'project_user.role',
                    'project_user.joined_at'
                ])
                ->orderBy('project_user.joined_at', 'asc')
                ->get();

            // Get project creator for owner comparison
            $projectCreatorId = $project->created_by;

            // Format member data
            $formattedMembers = $members->map(function ($member) use ($user, $projectCreatorId) {
                return [
                    'id' => $member->id,
                    'uuid' => $member->uuid,
                    'name' => $member->first_name . ' ' . $member->last_name,
                    'first_name' => $member->first_name,
                    'last_name' => $member->last_name,
                    'email' => $member->email,
                    'profile_photo_path' => $member->profile_photo_path,
                    'role' => $member->pivot->role,
                    'joined_at' => $member->pivot->joined_at,
                    'is_current_user' => $member->id === $user->id,
                    'is_project_owner' => $member->id === $projectCreatorId,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Project members retrieved successfully',
                'data' => [
                    'project' => [
                        'uuid' => $project->uuid,
                        'name' => $project->name,
                    ],
                    'members' => $formattedMembers,
                    'total_members' => $formattedMembers->count(),
                    'current_user_role' => $formattedMembers->firstWhere('is_current_user', true)?->role ?? null,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve project members',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update a project.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $project = DB::table('projects')->where('uuid', $uuid)->first();

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found',
                ], 404);
            }

            // Check if user belongs to the same organization
            if ($project->organisation_id !== $user->organisation_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found',
                ], 404);
            }

            // Validate request data
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'shortcode' => 'sometimes|nullable|string|max:10|unique:projects,shortcode,' . ($project->id ?? ''),
                'description' => 'sometimes|nullable|string|max:2000',
                'status' => 'sometimes|in:active,completed,archived,on_hold',
                'priority' => 'sometimes|in:low,medium,high,urgent,critical',
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date|after:start_date',
                'budget' => 'sometimes|numeric|min:0|max:9999999999.99',
                'currency' => 'sometimes|string|size:3',
                'metadata' => 'sometimes|array',
                'message' => 'Project updated successfully',
                'data' => [
                    'project' => $project,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update project',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Delete a project.
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $project = DB::table('projects')->where('uuid', $uuid)->first();

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found',
                ], 404);
            }

            // Check if user belongs to the same organization
            if ($project->organisation_id !== $user->organisation_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found',
                ], 404);
            }

            // Check if user is the creator or has permission
            if ($project->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to delete this project',
                ], 403);
            }

            DB::table('projects')->where('uuid', $uuid)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Project deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete project',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Join a project.
     */
    public function join(string $uuid): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $project = DB::table('projects')->where('uuid', $uuid)->first();

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found',
                ], 404);
            }

            // Check if user belongs to same organization
            if ($project->organisation_id !== $user->organisation_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found',
                ], 404);
            }

            // Check if user is already a member
            $existingMember = DB::table('project_user')
                ->where('project_id', $project->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existingMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are already a member of this project',
                ], 400);
            }

            // Add user to project as member
            $insertResult = DB::table('project_user')->insert([
                'project_id' => $project->id,
                'user_id' => $user->id,
                'role' => 'member',
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Debug: Check if insert was successful
            if (!$insertResult) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to join project - insert failed',
                    'debug' => [
                        'project_id' => $project->id,
                        'user_id' => $user->id,
                        'user_organisation_id' => $user->organisation_id,
                        'project_organisation_id' => $project->organisation_id,
                    ],
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Successfully joined the project',
                'data' => [
                    'project' => [
                        'uuid' => $project->uuid,
                        'name' => $project->name,
                    ],
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => 'member',
                        'joined_at' => now(),
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to join project',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Leave a project.
     */
    public function leave(string $uuid): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $project = DB::table('projects')->where('uuid', $uuid)->first();

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found',
                ], 404);
            }

            // Check if user belongs to same organization
            if ($project->organisation_id !== $user->organisation_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found',
                ], 404);
            }

            // Check if user is a member
            $existingMember = DB::table('project_user')
                ->where('project_id', $project->id)
                ->where('user_id', $user->id)
                ->first();

            if (!$existingMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not a member of this project',
                ], 400);
            }

            // Check if user is the creator/manager
            if ($project->created_by === $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project creators cannot leave their own project',
                ], 403);
            }

            // Remove user from project
            DB::table('project_user')
                ->where('project_id', $project->id)
                ->where('user_id', $user->id)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Successfully left the project',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to leave project',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Search users by email.
     */
    public function searchUsers(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user->organisation_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You must belong to an organization to search users',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'email' => 'required|string|email|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $email = $request->email;

            // Search for user by exact email match (across all organizations)
            $searchedUser = DB::table('users')
                ->where('email', $email)
                ->where('id', '!=', $user->id) // Exclude the current user
                ->select('id', 'email', 'first_name', 'last_name', 'organisation_id')
                ->first();

            $users = [];
            if ($searchedUser) {
                // Check if user is in the same organization
                $searchedUser->in_organization = $searchedUser->organisation_id === $user->organisation_id;
                $users[] = $searchedUser;
            }

            return response()->json([
                'success' => true,
                'message' => 'Users retrieved successfully',
                'data' => [
                    'users' => $users,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search users',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Add user to organization.
     */
    public function addUserToOrganization(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user->organisation_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You must belong to an organization to add users',
                ], 403);
            }

            // Check if user has admin or manager role
            if (!$user->canManageOrganization()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only admin or manager can add users to organization',
                    'data' => [
                        'required_roles' => ['admin', 'manager'],
                        'your_role' => $user->role ?? 'member',
                    ],
                ], 403);
            }

            // Initial validation - only email is required
            $validator = Validator::make($request->all(), [
                'email' => 'required|string|email|max:255',
                'first_name' => 'sometimes|string|max:255',
                'last_name' => 'sometimes|string|max:255',
                'role' => 'sometimes|string|in:admin,manager,member,viewer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Check if user already exists
            $existingUser = DB::table('users')
                ->where('email', $request->email)
                ->first();

            if ($existingUser) {
                // User exists - only email is needed
                
                // Check if user is already in the organization
                if ($existingUser->organisation_id === $user->organisation_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'User is already in your organization',
                    ], 400);
                }

                // Update user's organization
                DB::table('users')
                    ->where('id', $existingUser->id)
                    ->update([
                        'organisation_id' => $user->organisation_id,
                        'role' => $request->role ?? $existingUser->role ?? 'member',
                        'updated_at' => now(),
                    ]);

                // Get updated user data
                $updatedUser = DB::table('users')->where('id', $existingUser->id)->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Existing user added to organization successfully',
                    'data' => [
                        'user' => [
                            'id' => $updatedUser->id,
                            'email' => $updatedUser->email,
                            'first_name' => $updatedUser->first_name,
                            'last_name' => $updatedUser->last_name,
                            'role' => $updatedUser->role,
                            'organisation_id' => $user->organisation_id,
                        ],
                    ],
                ]);
            } else {
                // User doesn't exist - validate that first_name and last_name are provided
                if (!$request->first_name || !$request->last_name) {
                    return response()->json([
                        'success' => false,
                        'message' => 'User not found. Please provide first_name and last_name to create a new user.',
                        'errors' => [
                            'first_name' => !$request->first_name ? ['The first name field is required when creating a new user.'] : null,
                            'last_name' => !$request->last_name ? ['The last name field is required when creating a new user.'] : null,
                        ],
                    ], 422);
                }

                // Create new user
                $newUserId = DB::table('users')->insertGetId([
                    'uuid' => Str::uuid(),
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'email' => $request->email,
                    'organisation_id' => $user->organisation_id,
                    'role' => $request->role ?? 'member',
                    'is_active' => true,
                    'password' => bcrypt(Str::random(16)), // Generate random password
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $newUser = DB::table('users')->where('id', $newUserId)->first();

                return response()->json([
                    'success' => true,
                    'message' => 'New user created and added to organization successfully',
                    'data' => [
                        'user' => [
                            'id' => $newUser->id,
                            'email' => $newUser->email,
                            'first_name' => $newUser->first_name,
                            'last_name' => $newUser->last_name,
                            'organisation_id' => $user->organisation_id,
                            'role' => $newUser->role,
                        ],
                    ],
                ], 201);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add user to organization',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Add organization user to project.
     */
    public function addUserToProject(Request $request, string $uuid): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user->organisation_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You must belong to an organization to add users to projects',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|exists:users,id',
                'role' => 'sometimes|string|in:member,lead,manager,viewer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Check if project exists and belongs to user's organization
            $project = DB::table('projects')
                ->where('uuid', $uuid)
                ->where('organisation_id', $user->organisation_id)
                ->first();

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found or you do not have access',
                ], 404);
            }

            // Check if target user belongs to the same organization
            $targetUser = DB::table('users')
                ->where('id', $request->user_id)
                ->where('organisation_id', $user->organisation_id)
                ->first();

            if (!$targetUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found in your organization',
                ], 404);
            }

            // Check if user is already in the project
            $existingMember = DB::table('project_user')
                ->where('project_id', $project->id)
                ->where('user_id', $request->user_id)
                ->first();

            if ($existingMember) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is already a member of this project',
                ], 400);
            }

            // Add user to project
            DB::table('project_user')->insert([
                'project_id' => $project->id,
                'user_id' => $request->user_id,
                'role' => $request->role ?? 'member',
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User added to project successfully',
                'data' => [
                    'project' => [
                        'uuid' => $project->uuid,
                        'name' => $project->name,
                    ],
                    'user' => [
                        'id' => $targetUser->id,
                        'email' => $targetUser->email,
                        'first_name' => $targetUser->first_name,
                        'last_name' => $targetUser->last_name,
                        'role' => $request->role ?? 'member',
                        'joined_at' => now(),
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add user to project',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Send project invitation to a user.
     */
    public function sendInvitation(Request $request, string $uuid): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'role' => 'sometimes|in:member,lead,viewer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Get project
            $project = DB::table('projects')->where('uuid', $uuid)->first();

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found',
                ], 404);
            }

            // Check if user has access to project (is a member)
            $userInProject = DB::table('project_user')
                ->where('project_id', $project->id)
                ->where('user_id', $user->id)
                ->first();

            if (!$userInProject) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this project',
                ], 403);
            }

            // Check if user is manager or lead (only they can invite)
            if (!in_array($userInProject->role, ['manager', 'lead'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only project managers and leads can send invitations',
                ], 403);
            }

            // Check if invitee exists (can be from any organization or no organization)
            $invitee = User::where('email', $request->email)->first();

            if (!$invitee) {
                return response()->json([
                    'success' => false,
                    'message' => 'User with this email does not exist',
                ], 404);
            }

            // Check if user is already in project
            $alreadyInProject = DB::table('project_user')
                ->where('project_id', $project->id)
                ->where('user_id', $invitee->id)
                ->exists();

            if ($alreadyInProject) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is already a member of this project',
                ], 400);
            }

            // Check if there's already a pending invitation
            $existingInvitation = ProjectInvitation::where('project_id', $project->id)
                ->where('email', $request->email)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->first();

            if ($existingInvitation) {
                return response()->json([
                    'success' => false,
                    'message' => 'An active invitation already exists for this user',
                ], 400);
            }

            // Create invitation  
            $invitation = ProjectInvitation::create([
                'project_id' => $project->id,
                'email' => $request->email,
                'token' => ProjectInvitation::generateToken(),
                'invited_by' => $user->id,
                'role' => $request->role ?? 'member',
                'expires_at' => now()->addDays(7), // 7 days expiry
            ]);

            // Send email notification
            $invitationUrl = 'http://localhost:8080/projects/invitation/' . $invitation->token;
            
            try {
                Mail::raw(
                    "You've been invited to join the project '{$project->name}'.\n\n" .
                    "Invited by: {$user->first_name} {$user->last_name}\n" .
                    "Role: {$invitation->role}\n\n" .
                    "Click the link below to accept the invitation:\n" .
                    $invitationUrl . "\n\n" .
                    "This invitation will expire on " . $invitation->expires_at->format('F j, Y \a\t g:i A') . ".",
                    function ($message) use ($request, $project) {
                        $message->to($request->email)
                            ->subject("Invitation to join '{$project->name}' project");
                    }
                );
            } catch (\Exception $e) {
                // Log email error but don't fail the invitation
                \Log::warning('Failed to send project invitation email: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Invitation sent successfully',
                'data' => [
                    'invitation' => [
                        'uuid' => $invitation->uuid,
                        'email' => $invitation->email,
                        'role' => $invitation->role,
                        'project_name' => $project->name,
                        'expires_at' => $invitation->expires_at,
                        'invitation_url' => $invitationUrl,
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
     * Get all invitations for a project.
     */
    public function getProjectInvitations(string $uuid): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $project = DB::table('projects')->where('uuid', $uuid)->first();

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found',
                ], 404);
            }

            // Check if user has access to project
            $userInProject = DB::table('project_user')
                ->where('project_id', $project->id)
                ->where('user_id', $user->id)
                ->first();

            if (!$userInProject) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this project',
                ], 403);
            }

            // Get invitations
            $invitations = ProjectInvitation::where('project_id', $project->id)
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
     * Accept project invitation.
     */
    public function acceptInvitation(string $token): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $invitation = ProjectInvitation::where('token', $token)
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

            $project = DB::table('projects')->where('id', $invitation->project_id)->first();

            // Check if user already in project
            $alreadyInProject = DB::table('project_user')
                ->where('project_id', $invitation->project_id)
                ->where('user_id', $user->id)
                ->exists();

            if ($alreadyInProject) {
                $invitation->markAsAccepted();
                return response()->json([
                    'success' => false,
                    'message' => 'You are already a member of this project',
                ], 400);
            }

            // If user is not in the project's organization, add them to it
            if (!$user->organisation_id || $user->organisation_id !== $project->organisation_id) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'organisation_id' => $project->organisation_id,
                        'updated_at' => now(),
                    ]);
            }

            // Add user to project
            DB::table('project_user')->insert([
                'project_id' => $invitation->project_id,
                'user_id' => $user->id,
                'role' => $invitation->role,
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $invitation->markAsAccepted();

            return response()->json([
                'success' => true,
                'message' => 'Invitation accepted successfully. You have been added to the organization and project!',
                'data' => [
                    'project' => [
                        'uuid' => $project->uuid,
                        'name' => $project->name,
                    ],
                    'role' => $invitation->role,
                    'organization_added' => (!$user->organisation_id || $user->organisation_id !== $project->organisation_id),
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
     * Reject project invitation.
     */
    public function rejectInvitation(string $token): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $invitation = ProjectInvitation::where('token', $token)
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
     * Cancel/revoke a project invitation.
     */
    public function cancelInvitation(string $uuid, string $invitationUuid): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $project = DB::table('projects')->where('uuid', $uuid)->first();

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found',
                ], 404);
            }

            // Check if user has access to project and is manager/lead
            $userInProject = DB::table('project_user')
                ->where('project_id', $project->id)
                ->where('user_id', $user->id)
                ->first();

            if (!$userInProject || !in_array($userInProject->role, ['manager', 'lead'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only project managers and leads can cancel invitations',
                ], 403);
            }

            $invitation = ProjectInvitation::where('uuid', $invitationUuid)
                ->where('project_id', $project->id)
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

    /**
     * Get user's pending project invitations.
     */
    public function getMyInvitations(): JsonResponse
    {
        try {
            $user = Auth::user();

            $invitations = ProjectInvitation::where('email', $user->email)
                ->valid()
                ->with(['project:id,uuid,name,description', 'inviter:id,first_name,last_name,email'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($invitation) {
                    return [
                        'uuid' => $invitation->uuid,
                        'token' => $invitation->token,
                        'project' => [
                            'uuid' => $invitation->project->uuid,
                            'name' => $invitation->project->name,
                            'description' => $invitation->project->description,
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
                'message' => 'Your project invitations retrieved successfully',
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
}