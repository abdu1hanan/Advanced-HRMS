<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequestComment extends Model
{
    protected $fillable = ['leave_request_id', 'user_id', 'comment', 'is_hidden_from_employee'];

    protected $casts = [
        'is_hidden_from_employee' => 'boolean',
    ];

    public function leaveRequest()
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getFormattedCreatedAtAttribute(): string
    {
        if (!$this->created_at) {
            return '';
        }

        return $this->created_at->isAfter(now()->subHours(24))
            ? $this->created_at->diffForHumans()
            : $this->created_at->format('M d, Y');
    }
}