<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    protected $fillable = [
        'user_id',
        'leave_type_id',
        'year',
        'entitled',
        'used',
        'pending',
        'carried_forward',
    ];

    protected function casts(): array
    {
        return [
            'entitled' => 'decimal:1',
            'used' => 'decimal:1',
            'pending' => 'decimal:1',
            'carried_forward' => 'decimal:1',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
