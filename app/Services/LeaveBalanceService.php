<?php

namespace App\Services;

use App\Models\LeaveBalance;
use App\Models\LeaveBalanceOverride;
use App\Models\LeaveRequest;
use App\Models\LeaveSetting;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeaveBalanceService
{
    public function initializeBalancesForUser(User $user, ?int $year = null): void
    {
        $year = $year ?? (int) now()->year;

        foreach (LeaveType::where('is_active', true)->get() as $type) {
            $this->ensureBalanceRow($user, $type, $year);
        }
    }

    public function ensureBalanceRow(User $user, LeaveType $type, int $year): LeaveBalance
    {
        $existing = LeaveBalance::query()
            ->where('user_id', $user->id)
            ->where('leave_type_id', $type->id)
            ->where('year', $year)
            ->first();

        if ($existing) {
            return $existing;
        }

        $entitled = $this->resolveEntitlement($user, $type, $year);

        return LeaveBalance::create([
            'user_id' => $user->id,
            'leave_type_id' => $type->id,
            'year' => $year,
            'entitled' => $entitled,
            'used' => 0,
            'pending' => 0,
            'carried_forward' => 0,
        ]);
    }

    public function resolveEntitlement(User $user, LeaveType $type, int $year): float
    {
        $override = LeaveBalanceOverride::query()
            ->where('user_id', $user->id)
            ->where('leave_type_id', $type->id)
            ->where('year', $year)
            ->first();

        if ($override) {
            return (float) $override->entitled;
        }

        $setting = LeaveSetting::query()->where('leave_type_id', $type->id)->first();

        return (float) ($setting?->default_entitled ?? $type->max_days_per_year);
    }

    public function availableDays(LeaveBalance $balance): float
    {
        return (float) $balance->entitled
            + (float) $balance->carried_forward
            - (float) $balance->used
            - (float) $balance->pending;
    }

    public function applyDefaultsToAll(int $year): void
    {
        // Soft delete all manual overrides for this year
        \App\Models\LeaveBalanceOverride::where('year', $year)->delete();

        User::query()->each(function (User $user) use ($year) {
            foreach (LeaveType::where('is_active', true)->get() as $type) {
                $balance = $this->ensureBalanceRow($user, $type, $year);
                $entitled = $this->resolveEntitlement($user, $type, $year);
                $pending = $this->sumPendingDays($user->id, $type->id, $year);

                $balance->update([
                    'entitled' => $entitled,
                    'pending' => $pending,
                ]);
            }
        });
    }

    public function sumPendingDays(int $userId, int $leaveTypeId, int $year): float
    {
        return (float) LeaveRequest::query()
            ->where('user_id', $userId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('status', LeaveRequest::STATUS_PENDING)
            ->whereYear('start_date', $year)
            ->sum('days_count');
    }

    public function rolloverYear(int $fromYear, int $toYear): void
    {
        DB::transaction(function () use ($fromYear, $toYear) {
            $balances = LeaveBalance::with('leaveType')
                ->where('year', $fromYear)
                ->get();

            foreach ($balances as $balance) {
                $user = User::find($balance->user_id);
                if (! $user) {
                    continue;
                }

                $newBalance = $this->ensureBalanceRow($user, $balance->leaveType, $toYear);
                $carry = 0;

                if ($balance->leaveType->allow_carry_forward) {
                    $unused = $this->availableDays($balance);
                    $maxCarry = $balance->leaveType->max_carry_forward_days;
                    $carry = min(max(0, $unused), $maxCarry);
                }

                $entitled = $this->resolveEntitlement($user, $balance->leaveType, $toYear);
                $newBalance->update([
                    'entitled' => $entitled,
                    'carried_forward' => $carry,
                    'used' => 0,
                    'pending' => 0,
                ]);
            }
        });
    }
}
