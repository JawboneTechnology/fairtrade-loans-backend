<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Grant extends Model
{
    use HasUuids, SoftDeletes;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'grant_type_id',
        'dependent_id',
        'amount',
        'reason',
        'status',
        'admin_notes',
        'approval_date',
        'cancelled_date',
        'disbursement_date'
    ];

    protected $casts = [
        'approval_date' => 'date',
        'disbursement_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dependent(): BelongsTo
    {
        return $this->belongsTo(Dependant::class);
    }

    public function grantType(): BelongsTo
    {
        return $this->belongsTo(GrantType::class);
    }
}
