<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlackUserMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'slack_user_id',
        'user_id',
        'slack_username',
    ];

    /**
     * Get the HRMS User associated with this Slack User ID.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
