<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guarantor extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'loan_guarantors';
    protected $casts = [
        'guarantor_liability_amount' => 'decimal:2'
    ];

    protected $fillable = [
        'loan_id',
        'guarantor_id',
        'loan_number',
        'guarantor_liability_amount',
        'status'
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = $model->id ?? Str::uuid()->toString();
        });
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guarantor_id');
    }

    public function guarantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guarantor_id');
    }
}
