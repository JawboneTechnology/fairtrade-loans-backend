<?php

namespace App\Models;

use App\Helpers\GrantHelper;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrantType extends Model
{
    use HasUuids, SoftDeletes;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'grant_code',
        'description',
        'max_amount',
        'requires_dependent',
        'is_active'
    ];

    protected $casts = [
        'requires_dependent' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->grant_code)) {
                $model->grant_code = GrantHelper::generateGrantCode();
            }
        });
    }

    public function grants(): HasMany
    {
        return $this->hasMany(Grant::class);
    }
}
