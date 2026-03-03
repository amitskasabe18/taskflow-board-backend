<?php 

namespace Modules\UserManagement\Entities;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Modules\TeamManagement\Entities\Team;
use Modules\OrganizationManagement\Entities\Organisation;
use Modules\ProjectManagement\Entities\Project;
class User extends Authenticatable implements JWTSubject
{

    protected $fillable = [
        'uuid',
        'first_name',
        'last_name',
        'email',
        'role',
        'organisation_id',
        'is_active',
        'profile_photo_path',
        'password',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is a manager.
     */
    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    /**
     * Check if user can manage organization (admin or manager).
     */
    public function canManageOrganization(): bool
    {
        return in_array($this->role, ['admin', 'manager']);
    }

    /**
     * Check if user has specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user has any of the given roles.
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class);
    }

    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    public function projects()
    {
        return $this->belongsToMany(\Modules\ProjectManagement\Entities\Project::class, 'project_user')
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    //
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return an array with custom claims to be added to the JWT token.
     */
    public function getJWTCustomClaims()
    {
        return [];
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
