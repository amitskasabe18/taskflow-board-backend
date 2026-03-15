<?php

namespace Modules\TicketManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Status extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'slug',
        'category',
        'color',
        'position',
        'is_default',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope to get status by name.
     */
    public function scopeByName($query, $name)
    {
        return $query->where('name', $name);
    }
}
