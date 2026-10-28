<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QueueSession extends Model
{
    protected $fillable = ['label', 'status', 'ticket_prefix', 'next_sequence', 'next_queue_order', 'started_by', 'ended_by', 'started_at', 'ended_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
