<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanType extends Model
{
    protected $fillable = [
        'name',
        'interest_rate',
        'approval_type',
        'requires_guarantors',
        'required_guarantors_count',
        'guarantor_qualifications',
        'type',
        'payment_type',
        'is_active',
    ];

    protected $casts = [
        'requires_guarantors'       => 'boolean',
        'guarantor_qualifications'  => 'array', // This will auto-convert JSON to an array.
        'is_active'                 => 'boolean',
    ];

    public function loans() {
        return $this->hasMany(Loan::class);
    }
}
