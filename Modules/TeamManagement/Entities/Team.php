<?php 

namespace Modules\TeamManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\UserManagement\Entities\User;
use Modules\OrganizationManagement\Entities\Organisation;

class Team extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'status',
        'organisation_id',
        'team_code',
        'department',
        'type',
        'team_lead_name',
        'team_lead_email',
        'max_members',
        'current_members',
        'is_active_team',
        'color_code',
        'avatar_url',
        'permissions',
        'access_level',
        'billing_cycle',
        'monthly_cost',
        'next_billing_date',
        'subscription_status',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active_team' => 'boolean',
        'max_members' => 'integer',
        'current_members' => 'integer',
        'monthly_cost' => 'decimal:2',
        'next_billing_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the users that belong to the team.
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'team_users')
                    ->withTimestamps()
                    ->withPivot('created_at', 'updated_at');
    }

    /**
     * Get the organization that owns the team.
     */
    public function organisation()
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    /**
     * Get the team lead (user).
     */
    public function teamLead()
    {
        return $this->belongsTo(User::class, 'team_lead_email', 'email');
    }

    /**
     * Scope a query to only include active teams.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include teams of a specific type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include teams of a specific organization.
     */
    public function scopeOfOrganisation($query, $organisationId)
    {
        return $query->where('organisation_id', $organisationId);
    }

    /**
     * Check if team is at capacity.
     */
    public function isAtCapacity()
    {
        return $this->max_members && $this->current_members >= $this->max_members;
    }

    /**
     * Get team member count.
     */
    public function getMemberCountAttribute()
    {
        return $this->users()->count();
    }

    /**
     * Add a user to the team.
     */
    public function addUser($user)
    {
        if (!$this->users()->where('user_id', $user->id)->exists()) {
            $this->users()->attach($user->id);
            $this->increment('current_members');
        }
    }

    /**
     * Remove a user from the team.
     */
    public function removeUser($user)
    {
        if ($this->users()->where('user_id', $user->id)->exists()) {
            $this->users()->detach($user->id);
            $this->decrement('current_members');
        }
    }

    /**
     * Get team status as human readable.
     */
    public function getStatusAttribute($value)
    {
        return ucfirst($value);
    }

    /**
     * Get team type as human readable.
     */
    public function getTypeAttribute($value)
    {
        return ucfirst($value);
    }
}