<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SystemActivity extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'activity_type',
        'command_name',
        'triggered_by',
        'triggered_by_user_id',
        'description',
        'summary_data',
        'status',
        'started_at',
        'completed_at',
        'duration_seconds',
        'success_count',
        'failure_count',
        'error_details',
        'affected_entities',
        'server_info',
    ];

    protected $casts = [
        'id' => 'string',
        'triggered_by_user_id' => 'string',
        'summary_data' => 'array',
        'affected_entities' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_seconds' => 'integer',
        'success_count' => 'integer',
        'failure_count' => 'integer',
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
     * Get the user who triggered this activity
     */
    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    /**
     * Scope to get activities by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('activity_type', $type);
    }

    /**
     * Scope to get completed activities
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to get failed activities
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get activities for a specific command
     */
    public function scopeForCommand($query, string $commandName)
    {
        return $query->where('command_name', $commandName);
    }

    /**
     * Mark activity as completed
     */
    public function markAsCompleted(array $summary = []): bool
    {
        $completedAt = now();
        $duration = $this->started_at ? $this->started_at->diffInSeconds($completedAt) : null;

        return $this->update([
            'status' => 'completed',
            'completed_at' => $completedAt,
            'duration_seconds' => $duration,
            'summary_data' => array_merge($this->summary_data ?? [], $summary),
        ]);
    }

    /**
     * Mark activity as failed
     */
    public function markAsFailed(?string $errorDetails = null): bool
    {
        $completedAt = now();
        $duration = $this->started_at ? $this->started_at->diffInSeconds($completedAt) : null;

        return $this->update([
            'status' => 'failed',
            'completed_at' => $completedAt,
            'duration_seconds' => $duration,
            'error_details' => $errorDetails,
        ]);
    }

    /**
     * Update success and failure counts
     */
    public function updateCounts(int $successCount = 0, int $failureCount = 0): bool
    {
        return $this->update([
            'success_count' => $this->success_count + $successCount,
            'failure_count' => $this->failure_count + $failureCount,
        ]);
    }

    /**
     * Create a new system activity record
     */
    public static function logActivity(
        string $activityType,
        string $description,
        string $triggeredBy = 'system',
        ?string $commandName = null,
        ?string $userId = null
    ): self {
        return static::create([
            'activity_type' => $activityType,
            'command_name' => $commandName,
            'triggered_by' => $triggeredBy,
            'triggered_by_user_id' => $userId,
            'description' => $description,
            'status' => 'started',
            'started_at' => now(),
            'server_info' => gethostname() . ' - ' . request()->ip(),
        ]);
    }

    /**
     * Get recent activities for admin dashboard
     */
    public static function getRecentActivities(int $limit = 10)
    {
        return static::with('triggeredByUser')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get activity statistics
     */
    public static function getStatistics(?\Carbon\Carbon $date = null): array
    {
        $date = $date ?? today();
        
        return [
            'total_activities' => static::whereDate('created_at', $date)->count(),
            'completed_activities' => static::whereDate('created_at', $date)->where('status', 'completed')->count(),
            'failed_activities' => static::whereDate('created_at', $date)->where('status', 'failed')->count(),
            'average_duration' => static::whereDate('created_at', $date)->where('status', 'completed')->avg('duration_seconds'),
            'command_executions' => static::whereDate('created_at', $date)->where('activity_type', 'command_executed')->count(),
        ];
    }
}