<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LoanNotification extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'loan_id',
        'user_id',
        'notification_type',
        'channel',
        'phone_number',
        'message',
        'status',
        'amount_due',
        'outstanding_balance',
        'due_date',
        'days_until_due',
        'loan_number',
        'metadata',
        'sent_at',
        'failure_reason',
        'command_triggered_by',
    ];

    protected $casts = [
        'id' => 'string',
        'loan_id' => 'string',
        'user_id' => 'string',
        'amount_due' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'due_date' => 'date',
        'days_until_due' => 'integer',
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Str::uuid()->toString();
            }
        });
    }

    /**
     * Get the loan that this notification belongs to
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Get the user that this notification was sent to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get notifications by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('notification_type', $type);
    }

    /**
     * Scope to get notifications by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get notifications sent today
     */
    public function scopeSentToday($query)
    {
        return $query->whereDate('sent_at', today());
    }

    /**
     * Scope to get failed notifications
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Mark notification as sent
     */
    public function markAsSent(): bool
    {
        return $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark notification as failed
     */
    public function markAsFailed(?string $reason = null): bool
    {
        return $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
        ]);
    }

    /**
     * Get summary statistics for admin dashboard
     */
    public static function getDailySummary(?\Carbon\Carbon $date = null): array
    {
        $date = $date ?? today();
        
        return [
            'total_sent' => static::whereDate('sent_at', $date)->count(),
            'reminders_sent' => static::whereDate('sent_at', $date)->whereIn('notification_type', ['early_reminder', 'final_reminder'])->count(),
            'overdue_sent' => static::whereDate('sent_at', $date)->where('notification_type', 'overdue')->count(),
            'failed_count' => static::whereDate('created_at', $date)->where('status', 'failed')->count(),
            'success_rate' => static::whereDate('created_at', $date)->count() > 0 
                ? (static::whereDate('sent_at', $date)->count() / static::whereDate('created_at', $date)->count()) * 100 
                : 0
        ];
    }
}