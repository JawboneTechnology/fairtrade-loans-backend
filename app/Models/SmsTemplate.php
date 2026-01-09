<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SmsTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'message',
        'available_variables',
        'description',
        'is_active',
    ];

    protected $casts = [
        'available_variables' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get template by type
     */
    public static function getByType(string $type): ?self
    {
        return static::where('type', $type)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Parse template message with data
     */
    public function parseMessage(array $data): string
    {
        $message = $this->message;
        
        // Replace all {{variable}} with actual values
        return preg_replace_callback('/\{\{(\w+)\}\}/', function($matches) use ($data) {
            $variable = $matches[1];
            return $data[$variable] ?? $matches[0];
        }, $message);
    }

    /**
     * Scope for active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
