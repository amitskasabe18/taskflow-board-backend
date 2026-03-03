<?php

namespace Modules\ProjectManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\OrganizationManagement\Entities\Organisation;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'status',
        'priority',
        'start_date',
        'end_date',
        'budget',
        'currency',
        'metadata',
        'organisation_id',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'budget' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the organization that owns the project.
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * Get the user who created the project.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\Modules\UserManagement\Entities\User::class, 'created_by');
    }

    /**
     * Get the users assigned to the project.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(\Modules\UserManagement\Entities\User::class, 'project_user')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Scope to get active projects.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get projects by priority.
     */
    public function scopePriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope to get projects by status.
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Get formatted budget amount.
     */
    public function getFormattedBudgetAttribute(): string
    {
        if (!$this->budget) {
            return '$0.00';
        }

        return $this->currency . number_format($this->budget, 2);
    }

    /**
     * Get priority label with styling.
     */
    public function getPriorityLabelAttribute(): string
    {
        $labels = [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'urgent' => 'Urgent',
        ];

        return $labels[$this->priority] ?? 'Medium';
    }

    /**
     * Get status label with styling.
     */
    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'active' => 'Active',
            'completed' => 'Completed',
            'archived' => 'Archived',
            'on_hold' => 'On Hold',
        ];

        return $labels[$this->status] ?? 'Active';
    }

    /**
     * Check if project is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->end_date && $this->end_date->isPast();
    }

    /**
     * Get days until deadline.
     */
    public function getDaysUntilDeadlineAttribute(): int
    {
        if (!$this->end_date) {
            return 0;
        }

        return max(0, $this->end_date->diffInDays(now()));
    }

    /**
     * Get progress percentage based on start and end dates.
     */
    public function getProgressAttribute(): int
    {
        if (!$this->start_date || !$this->end_date) {
            return 0;
        }

        $total = $this->start_date->diffInDays($this->end_date);
        $elapsed = $this->start_date->diffInDays(now());

        return min(100, max(0, round(($elapsed / $total) * 100)));
    }

    protected static function boot()
    {
        // generate UUID for uuid column
        parent::boot();
        
        static::creating(function ($model) {
            $model->uuid = \Illuminate\Support\Str::uuid();
        });
    }
}
