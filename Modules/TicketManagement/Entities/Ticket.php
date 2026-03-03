<?php

namespace Modules\TicketManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'key',
        'key_sequence',
        'title',
        'description',
        'priority',
        'type',
        'resolution_status',
        'status_id',
        'project_id',
        'reporter_id',
        'assignee_id',
        'sprint_id',
        'parent_id',
        'story_points',
        'original_estimate_minutes',
        'remaining_estimate_minutes',
        'due_date',
        'start_date',
        'resolved_at',
        'closed_at',
        'resolution_note',
        'environment',
        'position',
        'is_archived',
    ];

    protected $casts = [
        'due_date' => 'date',
        'start_date' => 'date',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'original_estimate_minutes' => 'integer',
        'remaining_estimate_minutes' => 'integer',
        'story_points' => 'decimal:2',
        'position' => 'float',
        'is_archived' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the project that owns the ticket.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(\Modules\ProjectManagement\Entities\Project::class);
    }

    /**
     * Get the status of the ticket.
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get the user who reported the ticket.
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(\Modules\UserManagement\Entities\User::class, 'reporter_id');
    }

    /**
     * Get the user assigned to the ticket.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(\Modules\UserManagement\Entities\User::class, 'assignee_id');
    }

    /**
     * Get the sprint this ticket belongs to.
     */
    public function sprint(): BelongsTo
    {
        return $this->belongsTo(\Modules\ProjectManagement\Entities\Sprint::class);
    }

    /**
     * Get the parent ticket (for epics/stories).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'parent_id');
    }

    /**
     * Get child tickets (for epics/stories).
     */
    public function children(): HasMany
    {
        return $this->hasMany(Ticket::class, 'parent_id');
    }

    /**
     * Get the labels associated with the ticket.
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'ticket_labels')
            ->withTimestamps();
    }

    /**
     * Get the watchers of the ticket.
     */
    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(\Modules\UserManagement\Entities\User::class, 'ticket_watchers')
            ->withTimestamps();
    }

    /**
     * Get the linked tickets.
     */
    public function linkedTickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'ticket_links', 'source_ticket_id', 'target_ticket_id')
            ->withPivot('type', 'created_by')
            ->withTimestamps();
    }

    /**
     * Get the time logs for the ticket.
     */
    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class);
    }

    /**
     * Get the comments for the ticket.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    /**
     * Get the attachments for the ticket.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    /**
     * Get the history entries for the ticket.
     */
    public function history(): HasMany
    {
        return $this->hasMany(TicketHistory::class);
    }

    /**
     * Scope to get active tickets (not archived).
     */
    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    /**
     * Scope to get tickets by priority.
     */
    public function scopePriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope to get tickets by type.
     */
    public function scopeType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get tickets by status.
     */
    public function scopeStatus($query, $status)
    {
        return $query->whereHas('status', function ($query) use ($status) {
            $query->where('name', $status);
        });
    }

    /**
     * Scope to get tickets for a specific project.
     */
    public function scopeForProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope to get tickets in a sprint.
     */
    public function scopeInSprint($query, $sprintId)
    {
        return $query->where('sprint_id', $sprintId);
    }

    /**
     * Scope to get tickets not in any sprint (backlog).
     */
    public function scopeInBacklog($query)
    {
        return $query->whereNull('sprint_id');
    }

    /**
     * Scope to get tickets assigned to a user.
     */
    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assignee_id', $userId);
    }

    /**
     * Scope to get tickets reported by a user.
     */
    public function scopeReportedBy($query, $userId)
    {
        return $query->where('reporter_id', $userId);
    }

    /**
     * Get formatted ticket key.
     */
    public function getFormattedKeyAttribute(): string
    {
        return $this->key;
    }

    /**
     * Get human-readable priority.
     */
    public function getHumanPriorityAttribute(): string
    {
        return ucfirst($this->priority);
    }

    /**
     * Get human-readable type.
     */
    public function getHumanTypeAttribute(): string
    {
        return ucfirst($this->type);
    }

    /**
     * Check if ticket is resolved.
     */
    public function isResolved(): bool
    {
        return !is_null($this->resolved_at);
    }

    /**
     * Check if ticket is closed.
     */
    public function isClosed(): bool
    {
        return !is_null($this->closed_at);
    }

    /**
     * Get total time spent from time logs.
     */
    public function getTimeSpentAttribute(): int
    {
        return $this->timeLogs()->sum('minutes');
    }

    /**
     * Get total time spent in hours.
     */
    public function getTimeSpentHoursAttribute(): float
    {
        return $this->time_spent / 60;
    }
}
