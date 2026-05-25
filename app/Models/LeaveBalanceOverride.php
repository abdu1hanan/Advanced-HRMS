<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveBalanceOverride extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'leave_type_id', 'year', 'entitled'];

    protected function casts(): array
    {
        return ['entitled' => 'decimal:1'];
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
