<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueSetting extends Model
{
    protected $fillable = ['organization_name', 'logo_url', 'ticket_prefix', 'skip_policy', 'announcement_language', 'announcement_voice'];
}
