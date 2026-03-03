<?php

namespace Modules\TicketManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'minutes',
        'description',
        'ticket_id',
        'user_id',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
        'minutes' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the ticket this time log belongs to.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the user who logged the time.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\Modules\UserManagement\Entities\User::class);
    }

    /**
     * Get time in hours.
     */
    public function getHoursAttribute(): float
    {
        return $this->minutes / 60;
    }
}
