<?php

namespace Modules\TicketManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Modules\TicketManagement\Entities\Ticket;
use Modules\TicketManagement\Entities\TicketHistory;
use Modules\TicketManagement\Entities\Status;
use Modules\ProjectManagement\Entities\Project;
use Modules\UserManagement\Entities\User;

class TicketController extends Controller
{
    /**
     * Create a new ticket
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Validate request data
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:10000',
                'type' => 'required|in:task,bug,story,epic,subtask,improvement',
                'priority' => 'required|in:lowest,low,medium,high,highest',
                'project_id' => 'required|string', // Accept both numeric ID and UUID
                'assignee_id' => 'nullable|exists:users,id',
                'sprint_id' => 'nullable|exists:sprints,id',
                'parent_id' => 'nullable|exists:tickets,id',
                'story_points' => 'nullable|numeric|min:0|max:999.9',
                'original_estimate_minutes' => 'nullable|integer|min:0',
                'due_date' => 'nullable|date|after_or_equal:today',
                'start_date' => 'nullable|date|before_or_equal:due_date',
                'labels' => 'nullable|array',
                'labels.*' => 'exists:labels,id',
                'watchers' => 'nullable|array',
                'watchers.*' => 'exists:users,id',
                'attachments' => 'nullable|array',
                'attachments.*' => 'file|max:10240', // Max 10MB per file
            ], [
                'title.required' => 'Ticket title is required',
                'title.max' => 'Ticket title cannot exceed 255 characters',
                'description.max' => 'Description cannot exceed 10,000 characters',
                'type.required' => 'Ticket type is required',
                'type.in' => 'Invalid ticket type',
                'priority.required' => 'Ticket priority is required',
                'priority.in' => 'Invalid priority level',
                'project_id.required' => 'Project is required',
                'assignee_id.exists' => 'Invalid assignee',
                'sprint_id.exists' => 'Invalid sprint',
                'parent_id.exists' => 'Invalid parent ticket',
                'story_points.numeric' => 'Story points must be a number',
                'story_points.min' => 'Story points cannot be negative',
                'story_points.max' => 'Story points cannot exceed 999.9',
                'original_estimate_minutes.min' => 'Estimate cannot be negative',
                'due_date.after_or_equal' => 'Due date cannot be in the past',
                'start_date.before_or_equal' => 'Start date must be before or equal to due date',
                'labels.*.exists' => 'Invalid label',
                'watchers.*.exists' => 'Invalid watcher',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Additional business logic validations
            // Handle both UUID and numeric project_id
            if (is_numeric($request->project_id)) {
                $project = Project::find($request->project_id);
            } else {
                $project = Project::where('uuid', $request->project_id)->first();
            }
            
            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found',
                ], 404);
            }

            // Check if user has access to the project
            if (!$project->users()->where('users.id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this project',
                ], 403);
            }

            // Validate sprint belongs to the same project
            if ($request->sprint_id) {
                $sprint = \Modules\ProjectManagement\Entities\Sprint::find($request->sprint_id);
                if (!$sprint || $sprint->project_id != $project->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Sprint does not belong to this project',
                    ], 422);
                }
            }

            // Validate parent ticket belongs to the same project
            if ($request->parent_id) {
                $parentTicket = Ticket::find($request->parent_id);
                if (!$parentTicket || $parentTicket->project_id != $project->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Parent ticket does not belong to this project',
                    ], 422);
                }

                // Prevent circular references
                if ($this->wouldCreateCircularReference($request->parent_id, $request->parent_id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This would create a circular reference in ticket hierarchy',
                    ], 422);
                }
            }

            // Validate assignee has access to the project
            if ($request->assignee_id) {
                if (!$project->users()->where('users.id', $request->assignee_id)->exists()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Assignee does not have access to this project',
                    ], 422);
                }
            }

            // Validate watchers have access to the project
            if ($request->watchers) {
                foreach ($request->watchers as $watcherId) {
                    if (!$project->users()->where('users.id', $watcherId)->exists()) {
                        return response()->json([
                            'success' => false,
                            'message' => "Watcher with ID {$watcherId} does not have access to this project",
                        ], 422);
                    }
                }
            }

            // Generate ticket key and sequence
            $ticketKeySequence = $this->generateTicketKeySequence($project->id);
            $ticketKey = $project->key . '-' . $ticketKeySequence;

            // Get default status for the project
            $defaultStatus = Status::where('slug', 'todo')->first();
            if (!$defaultStatus) {
                return response()->json([
                    'success' => false,
                    'message' => 'Default status "To Do" not found',
                ], 500);
            }

            DB::beginTransaction();

            try {
                // Create the ticket using the numeric project ID
                $ticket = Ticket::create([
                    'uuid' => \Illuminate\Support\Str::uuid(),
                    'key' => $ticketKey,
                    'key_sequence' => $ticketKeySequence,
                    'title' => $request->title,
                    'description' => $request->description,
                    'type' => $request->type,
                    'priority' => $request->priority,
                    'status_id' => $defaultStatus->id,
                    'project_id' => $project->id, // Use the numeric project ID
                    'reporter_id' => $user->id,
                    'assignee_id' => $request->assignee_id,
                    'sprint_id' => $request->sprint_id,
                    'parent_id' => $request->parent_id,
                    'story_points' => $request->story_points,
                    'original_estimate_minutes' => $request->original_estimate_minutes,
                    'remaining_estimate_minutes' => $request->original_estimate_minutes,
                    'due_date' => $request->due_date,
                    'start_date' => $request->start_date,
                    'position' => $this->generateTicketPosition($project->id, $request->sprint_id),
                ]);

                // Attach labels if provided
                if ($request->labels) {
                    $ticket->labels()->attach($request->labels);
                }

                // Attach watchers (including the reporter)
                $watchers = $request->watchers ?? [];
                if (!in_array($user->id, $watchers)) {
                    $watchers[] = $user->id;
                }
                $ticket->watchers()->attach($watchers);

                // Handle file uploads
                if ($request->hasFile('attachments')) {
                    foreach ($request->file('attachments') as $file) {
                        if ($file->isValid()) {
                            $filename = time() . '_' . $file->getClientOriginalName();
                            $path = $file->storeAs('ticket-attachments/' . $ticket->id, $filename, 'public');
                            
                            $ticket->attachments()->create([
                                'filename' => $file->getClientOriginalName(), // Store original filename
                                'disk' => 'public',
                                'path' => $path,
                                'mime_type' => $file->getMimeType(),
                                'size' => $file->getSize(),
                                'uploaded_by' => $user->id,
                            ]);
                        }
                    }
                }

                // Create history entry for ticket creation
                $ticket->history()->create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $user->id,
                    'field_name' => 'created',
                    'old_value' => null,
                    'new_value' => $ticket->title,
                    'change_type' => 'created',
                ]);

                DB::commit();

                // Load relationships for response
                $ticket->load([
                    'project',
                    'status',
                    'reporter',
                    'assignee',
                    'sprint',
                    'parent',
                    'labels',
                    'watchers',
                    'attachments.uploader',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Ticket created successfully',
                    'data' => [
                        'ticket' => $ticket,
                    ],
                ], 201);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create ticket',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * List all tickets in a specific project
     * 
     * @param string $projectId
     * @return JsonResponse
     */
    public function index(string $projectId): JsonResponse
    {
        try {
            $user = Auth::user();

            // Check if user has access to the project
            // Handle both UUID and numeric project_id
            if (is_numeric($projectId)) {
                $project = \Modules\ProjectManagement\Entities\Project::find($projectId);
            } else {
                $project = \Modules\ProjectManagement\Entities\Project::where('uuid', $projectId)->first();
            }
            
            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found',
                ], 404);
            }

            if (!$project->users()->where('users.id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this project',
                ], 403);
            }

            // Get query parameters
            $status = request()->query('status');
            $type = request()->query('type');
            $priority = request()->query('priority');
            $assignee = request()->query('assignee');
            $sprint = request()->query('sprint');
            $search = request()->query('search');
            $perPage = request()->query('per_page', 20);
            $page = request()->query('page', 1);
            $sortBy = request()->query('sort_by', 'created_at');
            $sortOrder = request()->query('sort_order', 'desc');
            $includeArchived = request()->query('include_archived', false);

          
            
            // Build query
            $query = Ticket::with([
                'status',
                'assignee',
                'sprint',
                'parent',
                'labels',
                'watchers',
                'attachments.uploader'
            ])
            ->where('project_id', $project->id);

            // Apply filters
            if ($status) {
                if (is_numeric($status)) {
                    $query->where('status_id', $status);
                } else {
                    $query->whereHas('status', function ($q) use ($status) {
                        $q->where('slug', $status);
                    });
                }
            }

            if ($type) {
                $query->where('type', $type);
            }

            if ($priority) {
                $query->where('priority', $priority);
            }

            if ($assignee) {
                $query->where('assignee_id', $assignee);
            }

            if ($sprint) {
                if ($sprint === 'backlog') {
                    $query->whereNull('sprint_id');
                } else {
                    $query->where('sprint_id', $sprint);
                }
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', '%' . $search . '%')
                      ->orWhere('description', 'like', '%' . $search . '%')
                      ->orWhere('key', 'like', '%' . $search . '%');
                });
            }

            if (!$includeArchived) {
                $query->where('is_archived', false);
            }

            // Apply sorting
            $validSortFields = ['created_at', 'updated_at', 'title', 'priority', 'type', 'position', 'key'];
            $sortBy = in_array($sortBy, $validSortFields) ? $sortBy : 'created_at';
            $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? strtolower($sortOrder) : 'desc';
            $query->orderBy($sortBy, $sortOrder);

            // Get paginated results
            $tickets = $query->paginate($perPage, ['*'], 'page', $page);

            // Calculate metrics
            $metrics = [
                'total_count' => $tickets->total(),
                'active_count' => Ticket::where('project_id', $project->id)->where('is_archived', false)->count(),
                'archived_count' => Ticket::where('project_id', $project->id)->where('is_archived', true)->count(),
                'by_status' => Ticket::where('tickets.project_id', $project->id)
                    ->where('tickets.is_archived', false)
                    ->join('statuses', 'tickets.status_id', '=', 'statuses.id')
                    ->groupBy('statuses.name')
                    ->selectRaw('statuses.name as status_name, COUNT(*) as count')
                    ->pluck('count', 'status_name')
                    ->toArray(),
                'by_type' => Ticket::where('tickets.project_id', $project->id)
                    ->where('tickets.is_archived', false)
                    ->groupBy('tickets.type')
                    ->selectRaw('tickets.type, COUNT(*) as count')
                    ->pluck('count', 'type')
                    ->toArray(),
                'by_priority' => Ticket::where('tickets.project_id', $project->id)
                    ->where('tickets.is_archived', false)
                    ->groupBy('tickets.priority')
                    ->selectRaw('tickets.priority, COUNT(*) as count')
                    ->pluck('count', 'priority')
                    ->toArray(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Tickets retrieved successfully',
                'data' => [
                    'tickets' => $tickets->items(),
                    'pagination' => [
                        'current_page' => $tickets->currentPage(),
                        'per_page' => $tickets->perPage(),
                        'total' => $tickets->total(),
                        'last_page' => $tickets->lastPage(),
                        'from' => $tickets->firstItem(),
                        'to' => $tickets->lastItem(),
                        'has_more_pages' => $tickets->hasMorePages(),
                    ],
                    'filters' => [
                        'status' => $status,
                        'type' => $type,
                        'priority' => $priority,
                        'assignee' => $assignee,
                        'sprint' => $sprint,
                        'search' => $search,
                        'include_archived' => $includeArchived,
                        'sort_by' => $sortBy,
                        'sort_order' => $sortOrder,
                    ],
                    'metrics' => $metrics,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tickets',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get detailed information about a specific ticket
     * 
     * @param string $uuid
     * @return JsonResponse
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $user = Auth::user();

            $ticket = Ticket::with([
                'project',
                'status',
                'reporter',
                'assignee',
                'sprint',
                'parent',
                'children',
                'labels',
                'watchers',
                'timeLogs' => function ($query) {
                    $query->with('user')->orderBy('created_at', 'desc');
                },
                'comments' => function ($query) {
                    $query->with('user')->orderBy('created_at', 'desc');
                },
                'attachments' => function ($query) {
                    $query->with('uploader')->orderBy('created_at', 'desc');
                },
                'history' => function ($query) {
                    $query->with('user')->orderBy('created_at', 'desc');
                },
                'linkedTickets' => function ($query) {
                    $query->withPivot('type', 'created_by')->with('linkedTickets');
                }
            ])
            ->where('uuid', $uuid)
            ->first();

            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket not found',
                ], 404);
            }

            // Check if user has access to the project
            if (!$ticket->project->users()->where('users.id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this ticket',
                ], 403);
            }

            // Calculate additional metrics
            $ticket->time_spent_minutes = $ticket->timeLogs()->sum('minutes');
            $ticket->time_spent_hours = round($ticket->time_spent_minutes / 60, 2);
            $ticket->comments_count = $ticket->comments()->count();
            $ticket->attachments_count = $ticket->attachments()->count();
            $ticket->watchers_count = $ticket->watchers()->count();
            $ticket->subtasks_count = $ticket->children()->count();

            // Get related tickets (parent and siblings)
            $relatedTickets = [];
            if ($ticket->parent_id) {
                $relatedTickets['parent'] = $ticket->parent;
                $relatedTickets['siblings'] = Ticket::where('parent_id', $ticket->parent_id)
                    ->where('id', '!=', $ticket->id)
                    ->with(['status', 'assignee'])
                    ->get();
            }
            if ($ticket->children->count() > 0) {
                $relatedTickets['subtasks'] = $ticket->children;
            }

            return response()->json([
                'success' => true,
                'message' => 'Ticket retrieved successfully',
                'data' => [
                    'ticket' => $ticket,
                    'related_tickets' => $relatedTickets,
                    'metrics' => [
                        'time_spent_minutes' => $ticket->time_spent_minutes,
                        'time_spent_hours' => $ticket->time_spent_hours,
                        'comments_count' => $ticket->comments_count,
                        'attachments_count' => $ticket->attachments_count,
                        'watchers_count' => $ticket->watchers_count,
                        'subtasks_count' => $ticket->subtasks_count,
                    ]
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve ticket',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Generate ticket key sequence for a project
     */
    private function generateTicketKeySequence(int $projectId): int
    {
        $maxSequence = Ticket::where('project_id', $projectId)
            ->max('key_sequence');
        
        return $maxSequence ? $maxSequence + 1 : 1;
    }

    /**
     * Generate ticket position for ordering
     */
    private function generateTicketPosition(int $projectId, ?int $sprintId): float
    {
        $maxPosition = Ticket::where('project_id', $projectId)
            ->where('sprint_id', $sprintId)
            ->max('position');
        
        return $maxPosition ? $maxPosition + 1.0 : 1.0;
    }

    /**
     * Check if creating a parent relationship would cause circular reference
     */
    private function wouldCreateCircularReference(int $ticketId, int $parentId, array $visited = []): bool
    {
        if (in_array($ticketId, $visited)) {
            return true;
        }

        $visited[] = $ticketId;

        $parent = Ticket::find($parentId);
        if (!$parent) {
            return false;
        }

        if ($parent->id === $ticketId) {
            return true;
        }

        if ($parent->parent_id) {
            return $this->wouldCreateCircularReference($ticketId, $parent->parent_id, $visited);
        }

        return false;
    }

    /**
     * Update ticket status (only assignee can update)
     * 
     * @param Request $request
     * @param string $ticket_id
     * @return JsonResponse
     */
    public function updateTicketStatus(Request $request, string $ticket_id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Find the ticket
            $ticket = Ticket::where('uuid', $ticket_id)->with('assignee')->first();
            
            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket not found'
                ], 404);
            }

            // Authorization check: Only assigned user can update status
            if (!$ticket->assignee_id || $ticket->assignee_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only update the status of tickets assigned to you'
                ], 403);
            }

            // Validate request data
            $validator = Validator::make($request->all(), [
                'status_id' => 'required|exists:statuses,id',
            ], [
                'status_id.required' => 'Status ID is required',
                'status_id.exists' => 'Invalid status',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verify the status belongs to the same project
            $status = Status::find($request->status_id);
            if (!$status || ($status->project_id && $status->project_id !== $ticket->project_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Status does not belong to this project'
                ], 422);
            }

            // Update ticket status
            $oldStatus = $ticket->status_id;
            $ticket->status_id = $request->status_id;
            $ticket->save();

            // Create history entry for status change
            $ticket->history()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'field_name' => 'status',
                'old_value' => $oldStatus ? $oldStatus : null,
                'new_value' => $request->status_id,
                'change_type' => 'updated',
            ]);

            // Load relationships for response
            $ticket->load(['status', 'assignee', 'project']);

            // Log the status change
            Log::info('Ticket status updated', [
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'old_status_id' => $oldStatus,
                'new_status_id' => $request->status_id,
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ticket status updated successfully',
                'data' => [
                    'ticket' => $ticket,
                    'old_status_id' => $oldStatus,
                    'new_status_id' => $request->status_id
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating ticket status: ' . $e->getMessage(), [
                'ticket_id' => $ticket_id,
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update ticket status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get ticket history
     * 
     * @param string $ticket_id
     * @return JsonResponse
     */
    public function history(string $ticket_id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Find the ticket by UUID
            $ticket = Ticket::where('uuid', $ticket_id)->first();
            
            if (!$ticket) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ticket not found'
                ], 404);
            }

            // Check if user has access to this ticket (project member)
            $project = Project::find($ticket->project_id);
            if (!$project || !$project->users()->where('users.id', $user->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this ticket'
                ], 403);
            }

            // Get ticket history with user and status details
            $history = $ticket->history()
                ->with(['user:id,first_name,last_name,email'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'field_name' => $item->field_name,
                        'old_value' => $item->old_value,
                        'new_value' => $item->new_value,
                        'change_type' => $item->change_type,
                        'created_at' => $item->created_at->toISOString(),
                        'user' => $item->user ? [
                            'id' => $item->user->id,
                            'name' => trim($item->user->first_name . ' ' . $item->user->last_name),
                            'email' => $item->user->email
                        ] : null,
                        // Additional context for specific field types
                        'field_display_name' => $this->getFieldDisplayName($item->field_name),
                        'value_display' => $this->getFieldValueDisplay($item->field_name, $item->old_value, $item->new_value),
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Ticket history retrieved successfully',
                'data' => [
                    'ticket' => [
                        'id' => $ticket->uuid,
                        'title' => $ticket->title,
                        'project_id' => $ticket->project_id
                    ],
                    'history' => $history,
                    'total_changes' => $history->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error retrieving ticket history: ' . $e->getMessage(), [
                'ticket_id' => $ticket_id,
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve ticket history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get display name for field
     */
    private function getFieldDisplayName(string $fieldName): string
    {
        $displayNames = [
            'created' => 'Ticket Created',
            'status' => 'Status',
            'assignee_id' => 'Assignee',
            'priority' => 'Priority',
            'title' => 'Title',
            'description' => 'Description',
            'story_points' => 'Story Points',
            'due_date' => 'Due Date',
            'start_date' => 'Start Date',
            'type' => 'Type',
        ];

        return $displayNames[$fieldName] ?? ucfirst($fieldName);
    }

    /**
     * Get display values for field changes
     */
    private function getFieldValueDisplay(string $fieldName, $oldValue, $newValue): array
    {
        $display = [
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ];

        // Handle special cases for different field types
        switch ($fieldName) {
            case 'status':
                if ($oldValue) {
                    $oldStatus = Status::find($oldValue);
                    $display['old_value'] = $oldStatus ? $oldStatus->name : $oldValue;
                }
                if ($newValue) {
                    $newStatus = Status::find($newValue);
                    $display['new_value'] = $newStatus ? $newStatus->name : $newValue;
                }
                break;
                
            case 'assignee_id':
                if ($oldValue) {
                    $oldUser = User::find($oldValue);
                    $display['old_value'] = $oldUser ? trim($oldUser->first_name . ' ' . $oldUser->last_name) : $oldValue;
                }
                if ($newValue) {
                    $newUser = User::find($newValue);
                    $display['new_value'] = $newUser ? trim($newUser->first_name . ' ' . $newUser->last_name) : $newValue;
                }
                break;
                
            case 'priority':
                $priorities = [
                    'lowest' => 'Lowest',
                    'low' => 'Low', 
                    'medium' => 'Medium',
                    'high' => 'High',
                    'highest' => 'Highest'
                ];
                $display['old_value'] = $priorities[$oldValue] ?? $oldValue;
                $display['new_value'] = $priorities[$newValue] ?? $newValue;
                break;
                
            case 'type':
                $types = [
                    'task' => 'Task',
                    'bug' => 'Bug',
                    'story' => 'Story', 
                    'epic' => 'Epic',
                    'subtask' => 'Sub-task',
                    'improvement' => 'Improvement'
                ];
                $display['old_value'] = $types[$oldValue] ?? $oldValue;
                $display['new_value'] = $types[$newValue] ?? $newValue;
                break;
        }

        return $display;
    }
}
