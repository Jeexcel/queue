<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $fillable = [
        'queue_session_id', 'sequence_number', 'queue_order', 'ticket_number', 'request_key', 'status',
        'assigned_staff_id', 'service_desk_id', 'issued_at', 'called_at', 'accepted_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'called_at' => 'datetime', 'accepted_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function queueSession(): BelongsTo
    {
        return $this->belongsTo(QueueSession::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    public function serviceDesk(): BelongsTo
    {
        return $this->belongsTo(ServiceDesk::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class);
    }
}
