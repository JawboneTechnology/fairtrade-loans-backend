<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SmsMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipient',
        'message',
        'status',
        'provider_response',
        'sent_by',
    ];
}
