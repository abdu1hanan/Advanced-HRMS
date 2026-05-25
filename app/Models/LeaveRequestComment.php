<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequestComment extends Model
{
    protected $fillable = ['leave_request_id', 'user_id', 'comment'];

    public function leaveRequest()
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}