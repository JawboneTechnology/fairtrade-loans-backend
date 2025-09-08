<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    // Fillable
    protected $fillable = [
        'email',
        'reset_code',
        'expires_at',
    ];

    // Casts
    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
