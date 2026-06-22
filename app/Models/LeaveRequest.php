<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REJECTED_BY_MANAGER = 'rejected_by_manager';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'days_count',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_comment',
        'change_request',
        'change_request_status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days_count' => 'decimal:1',
            'reviewed_at' => 'datetime',
            'change_request' => 'array',
        ];
    }

    public function hasPendingChangeRequest(): bool
    {
        return $this->change_request_status === 'pending';
    }

    public function hasApprovedManagerChangeRequest(): bool
    {
        return $this->change_request_status === 'approved_by_manager';
    }

    public function hasRejectedManagerChangeRequest(): bool
    {
        return $this->change_request_status === 'rejected_by_manager';
    }

    public function hasOutstandingChangeRequest(): bool
    {
        return in_array($this->change_request_status, ['pending', 'approved_by_manager', 'rejected_by_manager']);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function comments()
    {
        return $this->hasMany(LeaveRequestComment::class)->latest();
    }
}
