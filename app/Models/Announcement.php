<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    public $timestamps = false;

    protected $fillable = ['event_uuid', 'ticket_id', 'service_desk_id', 'event_type', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function serviceDesk(): BelongsTo
    {
        return $this->belongsTo(ServiceDesk::class);
    }
}
