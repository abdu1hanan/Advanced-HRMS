<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'max_days_per_year',
        'allow_carry_forward',
        'max_carry_forward_days',
        'requires_attachment',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'allow_carry_forward' => 'boolean',
            'requires_attachment' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(LeaveSetting::class);
    }
}
