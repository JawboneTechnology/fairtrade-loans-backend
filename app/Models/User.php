<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'created_by',
        'first_name',
        'middle_name',
        'last_name',
        'phone_number',
        'address',
        'dob',
        'passport_image',
        'gender',
        'email',
        'national_id',
        'years_of_employment',
        'password',
        'verification_code',
        'employer_id',
        'old_employee_id',
        'employee_id',
        'salary',
        'loan_limit',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function guaranteedLoans()
    {
        return $this->belongsToMany(Loan::class, 'loan_guarantors', 'guarantor_id', 'loan_id')
            ->withPivot(['status', 'loan_number', 'guarantor_liability_amount'])
            ->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'employee_id');
    }

    public function dependants(): HasMany
    {
        return $this->hasMany(Dependant::class);
    }

    public function grants(): HasMany
    {
        return $this->hasMany(\App\Models\Grant::class, 'user_id');
    }
}
