<?php

namespace Modules\OrganizationManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organisation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'status',
        'user_type',
        'logo',
        'plan',
        'plan_start_date',
        'plan_end_date',
        'plan_next_bill_date',
        'plan_next_bill_amount',
        'plan_next_bill_status',
        'website_url',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'max_users',
        'max_projects',
        'max_storage_mb',
        'is_trial',
        'trial_end_date',
    ];

    protected $casts = [
        'is_trial' => 'boolean',
        'max_users' => 'integer',
        'max_projects' => 'integer',
        'max_storage_mb' => 'integer',
        'plan_next_bill_amount' => 'decimal:2',
        'plan_start_date' => 'date',
        'plan_end_date' => 'date',
        'plan_next_bill_date' => 'date',
        'trial_end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get teams that belong to organization.
     */
    public function teams()
    {
        return $this->hasMany('Modules\TeamManagement\Entities\Team', 'organisation_id');
    }

    /**
     * Get users that belong to organization.
     */
    public function users()
    {
        return $this->hasMany('Modules\UserManagement\Entities\User', 'organisation_id');
    }

    /**
     * Get active teams for this organization.
     */
    public function activeTeams()
    {
        return $this->teams()->active();
    }

    /**
     * Get total users count for this organization.
     */
    public function getUserCountAttribute()
    {
        return $this->users()->count();
    }

    /**
     * Get total teams count for this organization.
     */
    public function getTeamCountAttribute()
    {
        return $this->teams()->count();
    }

    /**
     * Scope a query to only include active organizations.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include organizations of a specific type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('user_type', $type);
    }

    /**
     * Scope a query to only include organizations on specific plan.
     */
    public function scopeOfPlan($query, $plan)
    {
        return $query->where('plan', $plan);
    }

    /**
     * Scope a query to only include organizations on trial.
     */
    public function scopeOnTrial($query)
    {
        return $query->where('is_trial', true);
    }

    /**
     * Check if organization is on trial.
     */
    public function isOnTrial()
    {
        return $this->is_trial && $this->trial_end_date && $this->trial_end_date->isFuture();
    }

    /**
     * Check if organization plan is expired.
     */
    public function isPlanExpired()
    {
        return $this->plan_end_date && $this->plan_end_date->isPast();
    }

    /**
     * Get remaining trial days.
     */
    public function getRemainingTrialDaysAttribute()
    {
        if (!$this->is_trial || !$this->trial_end_date) {
            return 0;
        }
        return max(0, $this->trial_end_date->diffInDays(now()));
    }

    /**
     * Get remaining plan days.
     */
    public function getRemainingPlanDaysAttribute()
    {
        if (!$this->plan_end_date) {
            return null;
        }
        return max(0, $this->plan_end_date->diffInDays(now()));
    }

    /**
     * Check if organization can add more users.
     */
    public function canAddUsers()
    {
        return !$this->max_users || $this->users_count < $this->max_users;
    }

    /**
     * Check if organization can create more projects.
     */
    public function canCreateProjects()
    {
        return !$this->max_projects || $this->teams_count < $this->max_projects;
    }

    /**
     * Get organization status as human readable.
     */
    public function getStatusAttribute($value)
    {
        return ucfirst($value);
    }

    /**
     * Get organization type as human readable.
     */
    public function getUserTypeAttribute($value)
    {
        return ucfirst($value);
    }

    /**
     * Get organization plan as human readable.
     */
    public function getPlanAttribute($value)
    {
        return ucfirst($value);
    }

    /**
     * Get plan next bill status as human readable.
     */
    public function getPlanNextBillStatusAttribute($value)
    {
        return $value ? ucfirst($value) : null;
    }
}
