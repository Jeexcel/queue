<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordResetOtp extends Model
{
    protected $fillable = ['user_id', 'phone', 'otp_hash', 'expires_at', 'last_sent_at', 'attempts', 'consumed_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'last_sent_at' => 'datetime', 'consumed_at' => 'datetime'];
    }
}
