<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveRequest extends Model
{
    public const STATUS_PENDING             = 'pending';
    public const STATUS_APPROVED            = 'approved';
    public const STATUS_REJECTED            = 'rejected';
    public const STATUS_REJECTED_BY_MANAGER = 'rejected_by_manager';
    public const STATUS_CANCELLED           = 'cancelled';

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
        'is_admin_marked',
        'change_request',
        'change_request_status',
        'approval_chain_target',
        'approval_chain_progress',
    ];

    protected function casts(): array
    {
        return [
            'start_date'              => 'date',
            'end_date'                => 'date',
            'days_count'              => 'decimal:1',
            'reviewed_at'             => 'datetime',
            'change_request'          => 'array',
            'approval_chain_target'   => 'integer',
            'approval_chain_progress' => 'integer',
            'is_admin_marked'         => 'boolean',
        ];
    }

    /**
     * Whether this leave was created directly by Super Admin (bypasses approval chain).
     */
    public function isAdminMarked(): bool
    {
        return (bool) $this->is_admin_marked;
    }

    // ─── Relationships ──────────────────────────────────────────────────────

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

    public function comments(): HasMany
    {
        return $this->hasMany(LeaveRequestComment::class)->latest();
    }

    public function approvalChain(): HasMany
    {
        return $this->hasMany(LeaveApprovalChain::class)->orderBy('level');
    }

    // ─── Chain Helper Methods ────────────────────────────────────────────────

    /**
     * Has this manager already recorded ANY action (approve or reject) on this request?
     */
    public function hasManagerActed(int $userId): bool
    {
        return $this->approvalChain()->where('approver_id', $userId)->exists();
    }

    /**
     * Has this manager specifically APPROVED this request (locked from rejecting)?
     */
    public function hasManagerApproved(int $userId): bool
    {
        return $this->approvalChain()
            ->where('approver_id', $userId)
            ->where('action', 'approved')
            ->exists();
    }

    /**
     * Has the chain reached its configured target level?
     */
    public function chainComplete(): bool
    {
        return $this->approval_chain_progress >= $this->approval_chain_target;
    }

    /**
     * The next expected approval level (1-indexed).
     */
    public function nextChainLevel(): int
    {
        return $this->approval_chain_progress + 1;
    }

    /**
     * Get the hierarchy level (1-indexed) at which this request was rejected by a manager.
     */
    public function rejectionLevel(): int
    {
        $rejectedStep = $this->approvalChain->firstWhere('action', 'rejected');
        if ($rejectedStep && (int) $rejectedStep->level > 0) {
            return (int) $rejectedStep->level;
        }
        return (int) $this->approval_chain_progress;
    }

    // ─── Change Request Helpers ──────────────────────────────────────────────

    public function hasPendingChangeRequest(): bool
    {
        return $this->change_request_status === 'pending';
    }

    public function hasPendingCancellation(): bool
    {
        return $this->change_request_status === 'pending' && !empty($this->change_request['request_cancellation']);
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
        if ($this->status === self::STATUS_REJECTED || $this->status === self::STATUS_REJECTED_BY_MANAGER || $this->status === self::STATUS_CANCELLED) {
            return false;
        }

        if ($this->change_request_status === 'rejected') {
            return false;
        }

        return in_array($this->change_request_status, ['pending', 'approved_by_manager']) || (!empty($this->change_request) && !in_array($this->change_request_status, ['approved', 'rejected']));
    }

    public function changeRequestBadgeText(): string
    {
        if (empty($this->change_request_status) && empty($this->change_request)) {
            return '';
        }

        $oldStatus = $this->change_request['old_status'] ?? null;
        $wasFullyApproved = ($this->status === self::STATUS_APPROVED || ($this->status === self::STATUS_CANCELLED && $oldStatus === self::STATUS_APPROVED));

        if (($this->change_request_status === 'approved' && $this->status !== self::STATUS_CANCELLED) || ($wasFullyApproved && !empty($this->change_request) && $this->change_request_status === 'approved')) {
            return 'Changes Fully Approved';
        }

        if ($this->change_request_status === 'rejected' || $this->change_request_status === 'rejected_by_manager' || $this->status === self::STATUS_REJECTED || $this->status === self::STATUS_REJECTED_BY_MANAGER) {
            return 'Changes Rejected';
        }

        $p = (int) $this->approval_chain_progress;
        $t = (int) $this->approval_chain_target;

        if ($p === 0) {
            return 'Pending Changes';
        }

        if ($t > 0 && $p < $t) {
            return "Changes Approved Level {$p}/{$t} - Pending Manager";
        }

        $displayT = ($t > 0) ? $t : max(1, $p);
        return "Changes Approved Level {$displayT}/{$displayT} - Pending CEO";
    }

    public function getFormattedDaysCountAttribute(): string
    {
        $num = (float) $this->days_count;
        $formattedNum = ($num == (int)$num) ? (int)$num : $num;
        $unit = ($formattedNum === 1) ? 'day' : 'days';
        return "{$formattedNum} {$unit}";
    }

    /**
     * Contextual banner message when a leave is cancelled.
     */
    public function cancellationBannerText(): string
    {
        if ($this->status !== self::STATUS_CANCELLED) {
            return '';
        }

        $userName = $this->user->name ?? 'Employee';
        $chainEntries = $this->approvalChain;
        $bossChain = app(\App\Services\HierarchyService::class)->getBossChain($this->user);

        // 1. Check if past cancellation request was approved by Super Admin
        if (!empty($this->change_request['approved_by'])) {
            $approverUser = User::find($this->change_request['approved_by']);
            $approverName = $approverUser?->name ?? 'Admin';
            return "Cancellation request for <strong>{$userName}</strong> was approved by {$approverName}.";
        }

        // 2. Check if the leave was fully approved by Super Admin prior to cancellation
        $oldStatus = $this->change_request['old_status'] ?? '';
        $saReviewer = ($this->reviewer && $this->reviewer->isSuperAdmin()) ? $this->reviewer : null;
        $isFullyApproved = ($oldStatus === self::STATUS_APPROVED || ($saReviewer && $this->reviewed_at && !in_array($oldStatus, ['rejected', 'rejected_by_manager', 'approved_by_manager', 'pending'])));

        if ($isFullyApproved) {
            return "<strong>{$userName}</strong> cancelled this fully approved leave request.";
        }

        // 3. Check if any line manager acted prior to cancellation
        $lastActionEntry = $chainEntries->sortBy('level')->last(fn($e) => in_array($e->action, ['approved', 'rejected']));

        if ($lastActionEntry) {
            $approverName = $lastActionEntry->approver?->name ?? 'Line Manager';
            $level = (int) $lastActionEntry->level;
            $target = (int) $this->approval_chain_target;
            
            if ($lastActionEntry->action === 'approved') {
                if ($target > 0 && $level < $target) {
                    return "<strong>{$userName}</strong> cancelled this leave request after <strong>{$approverName}</strong> approved [Level {$level} of {$target}].";
                } else {
                    return "<strong>{$userName}</strong> cancelled this leave request after <strong>{$approverName}</strong> approved [Level {$level} of {$target} - Pending CEO].";
                }
            } elseif ($lastActionEntry->action === 'rejected') {
                return "<strong>{$userName}</strong> cancelled this leave request after <strong>{$approverName}</strong> rejected [Level {$level} of {$target}].";
            }
        }

        // 4. Before any decision was made
        $l1Boss = $bossChain[0] ?? null;
        if ($this->approval_chain_target > 0 && $l1Boss) {
            return "<strong>{$userName}</strong> cancelled this leave request before <strong>{$l1Boss->name}</strong> made a decision.";
        }

        return "<strong>{$userName}</strong> cancelled this leave request before any decision was made.";
    }

    // ─── Model Events ────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        $clearCache = static function (self $leave) {
            $user        = $leave->user()->first();
            $slackUserId = $user?->slackMapping()->first()?->slack_user_id;
            if ($slackUserId) {
                $start   = \Carbon\Carbon::parse($leave->start_date);
                $end     = \Carbon\Carbon::parse($leave->end_date);
                $current = $start->copy();
                while ($current->lte($end)) {
                    $dateStr = $current->format('Y-m-d');
                    \Illuminate\Support\Facades\Cache::forget("healed_{$slackUserId}_{$dateStr}");
                    $current->addDay();
                }
            }
        };

        static::saved($clearCache);
        static::deleted($clearCache);
    }
}
