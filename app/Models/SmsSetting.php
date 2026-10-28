<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsSetting extends Model
{
    protected $fillable = ['provider', 'credentials', 'sender_id', 'endpoint'];

    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array'];
    }
}
