<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlackLeaveSyncMessage extends Model
{
    use HasFactory;

    protected $table = 'slack_leave_sync_messages';

    protected $fillable = [
        'event_id',
        'slack_user_id',
        'channel_id',
        'message_text',
        'slack_ts',
        'message_at',
        'is_edited',
        'is_deletion_locked',
    ];

    protected $casts = [
        'message_at' => 'datetime',
        'is_edited' => 'boolean',
        'is_deletion_locked' => 'boolean',
    ];

    /**
     * Get the user mapping for this message.
     */
    public function mapping()
    {
        return $this->belongsTo(SlackUserMapping::class, 'slack_user_id', 'slack_user_id');
    }
}
