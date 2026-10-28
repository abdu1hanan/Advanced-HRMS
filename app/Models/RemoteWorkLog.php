<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class RemoteWorkLog extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'notes',
        'marked_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    protected static function booted(): void
    {
        $clearCache = static function (self $record) {
            $user = $record->user()->first();
            $slackUserId = $user?->slackMapping()->first()?->slack_user_id;
            if ($slackUserId && $record->date) {
                $dateStr = $record->date->format('Y-m-d');
                Cache::forget("healed_{$slackUserId}_{$dateStr}");
            }
        };

        static::saved($clearCache);
        static::deleted($clearCache);
    }
}
