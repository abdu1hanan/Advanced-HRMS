<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlackMessageEdit extends Model
{
    use HasFactory;

    protected $fillable = [
        'slack_message_id',
        'previous_text',
        'new_text',
        'edited_at',
    ];

    protected $casts = [
        'edited_at' => 'datetime',
    ];

    /**
     * Get the parent slack message record.
     */
    public function slackMessage(): BelongsTo
    {
        return $this->belongsTo(SlackMessage::class);
    }
}
