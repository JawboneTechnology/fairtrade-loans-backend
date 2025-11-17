<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MpesaTransaction extends Model
{
    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'transaction_id';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    protected $fillable = [
        'checkout_request_id',
        'merchant_request_id',
        'mpesa_receipt_number',
        'phone_number',
        'amount',
        'account_reference',
        'transaction_description',
        'transaction_type',
        'status',
        'result_code',
        'result_desc',
        'transaction_date',
        'callback_data',
        'user_id',
        'loan_id',
        'payment_method'
    ];

    protected $casts = [
        'callback_data' => 'array',
        'transaction_date' => 'datetime',
        'amount' => 'decimal:2'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->transaction_id)) {
                $model->transaction_id = Str::uuid();
            }
        });
    }

    /**
     * Get the user who initiated the transaction
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the loan associated with the transaction
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Scope for successful transactions
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'SUCCESS');
    }

    /**
     * Scope for pending transactions
     */
    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }

    /**
     * Scope for failed transactions
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'FAILED');
    }

    /**
     * Scope for STK Push transactions
     */
    public function scopeStkPush($query)
    {
        return $query->where('transaction_type', 'STK_PUSH');
    }

    /**
     * Check if transaction is successful
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'SUCCESS';
    }

    /**
     * Check if transaction is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    /**
     * Check if transaction failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'FAILED';
    }
}
