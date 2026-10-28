<?php

namespace App\Events;

use App\Models\Announcement;
use Illuminate\Foundation\Events\Dispatchable;

class TicketCalled
{
    use Dispatchable;

    public function __construct(public readonly Announcement $announcement)
    {
    }
}
