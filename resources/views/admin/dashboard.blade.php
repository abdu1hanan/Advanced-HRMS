<x-app-layout>
    <x-slot name="header">My Dashboard</x-slot>

    {{-- Status Banner for Pending / Rejected Accounts --}}
    @if($user->isPendingApproval())
        <div class="spotify-card p-4 mb-4" style="border: 1px solid rgba(245, 158, 11, 0.3) !important; background: linear-gradient(135deg, rgba(245, 158, 11, 0.08) 0%, rgba(18, 18, 18, 0.95) 100%);">
            <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
                <div class="d-flex align-items-start gap-3">
                    <div class="rounded-circle p-3 d-flex align-items-center justify-content-center flex-shrink-0" style="background-color: rgba(245, 158, 11, 0.15); width: 48px; height: 48px;">
                        <i class="bi bi-hourglass-split text-warning fs-4"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-white mb-1">Account Pending Approval</h5>
                        <p class="text-white opacity-75 small mb-0" style="font-size: 0.85rem; line-height: 1.5; max-width: 820px;">
                            Welcome to BeetleOps HRMS, <strong class="text-white">{{ $user->name }}</strong>! Your account has been registered and is currently awaiting approval from HR / Administrator. Your profile details are shown below. Once approved, all system tools including attendance logs, personal leaves, and organization hierarchy will be unlocked automatically. Kindly contact HR if you require immediate activation.
                        </p>
                    </div>
                </div>
                @php
                    $submittedAt = $user->activation_requested_at ?? $user->created_at;
                @endphp
                @if($submittedAt)
                    @php
                        $now = now();
                        $diffSeconds = (int) abs($submittedAt->diffInSeconds($now));
                        $diffMinutes = (int) abs($submittedAt->diffInMinutes($now));
                        $diffHours   = (int) abs($submittedAt->diffInHours($now));
                        $diffDays    = (int) abs($submittedAt->diffInDays($now));

                        if ($diffDays >= 1) {
                            $timeString = $diffDays . ' ' . \Illuminate\Support\Str::plural('day', $diffDays) . ' ago';
                        } elseif ($diffHours >= 1) {
                            $timeString = $diffHours . ' ' . \Illuminate\Support\Str::plural('hour', $diffHours) . ' ago';
                        } elseif ($diffMinutes >= 1) {
                            $timeString = $diffMinutes . ' ' . \Illuminate\Support\Str::plural('min', $diffMinutes) . ' ago';
                        } elseif ($diffSeconds >= 10) {
                            $timeString = $diffSeconds . ' sec ago';
                        } else {
                            $timeString = 'Just now';
                        }
                    @endphp
                    <div class="flex-shrink-0 ms-md-auto text-md-end">
                        <span class="text-warning opacity-90 small d-inline-flex align-items-center gap-2 px-3 py-1.5 rounded-pill" style="background-color: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.25); font-size: 0.78rem;">
                            <i class="bi bi-clock-history"></i> Request submitted {{ $timeString }}
                        </span>
                    </div>
                @endif
            </div>
        </div>
    @elseif($user->isRejected())
        <div class="spotify-card p-4 mb-4" style="border: 1px solid rgba(239, 68, 68, 0.35) !important; background: linear-gradient(135deg, rgba(239, 68, 68, 0.08) 0%, rgba(18, 18, 18, 0.95) 100%);">
            <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
                <div class="d-flex align-items-start gap-3">
                    <div class="rounded-circle p-3 d-flex align-items-center justify-content-center flex-shrink-0" style="background-color: rgba(239, 68, 68, 0.15); width: 48px; height: 48px;">
                        <i class="bi bi-shield-x text-danger fs-4"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-white mb-1">Account Registration Not Approved</h5>
                        <p class="text-white opacity-75 small mb-1" style="font-size: 0.85rem; line-height: 1.5; max-width: 820px;">
                            Your account registration was not approved by HR / Administration. If you believe this was an error or would like your account re-evaluated, please verify your details with HR or click the button to request account activation.
                        </p>
                        @if($user->rejected_at)
                            <small class="text-white opacity-40 d-block" style="font-size: 0.72rem;">
                                Decision recorded on {{ $user->rejected_at->timezone('Asia/Karachi')->format('M d, Y · h:i A') }}.
                            </small>
                        @endif
                    </div>
                </div>
                <div class="flex-shrink-0 ms-md-auto text-md-end">
                    <form action="{{ route('admin.account.request-activation') }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="row-btn-blue px-3 py-2 d-inline-flex align-items-center gap-2 text-nowrap" style="font-size: 0.82rem; border: none; height: 36px;">
                            <i class="bi bi-arrow-repeat"></i> Request Account Activation
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if(!$user->manager_id && !$user->isGuest() && $user->isApproved())
        <div id="no-boss-warning-alert" class="alert alert-warning alert-dismissible fade show border-0 shadow-sm mb-4 bg-warning bg-opacity-10 text-warning d-none" style="border: 1px solid rgba(255, 193, 7, 0.15) !important;">
            <i class="bi bi-exclamation-triangle me-2"></i> You do not have an immediate boss assigned yet. Leave requests will be sent directly to the Super Admin for review.
            <button type="button" class="btn-close btn-close-white opacity-75" id="dismiss-no-boss-alert" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const alertKey = 'dismissed_no_boss_alert_{{ $user->id }}';
                    const alertEl = document.getElementById('no-boss-warning-alert');
                    const dismissBtn = document.getElementById('dismiss-no-boss-alert');

                    if (alertEl) {
                        if (!localStorage.getItem(alertKey)) {
                            alertEl.classList.remove('d-none');
                        }
                        if (dismissBtn) {
                            dismissBtn.addEventListener('click', function() {
                                localStorage.setItem(alertKey, 'true');
                            });
                        }
                    }
                });
            </script>
        @endpush
    @endif

    {{-- =====================================================================
         BENTO GRID ROW 1: PROFILE & OVERVIEW TILES
         ===================================================================== --}}
    @if($user->isApproved() && !$user->isGuest())
    {{-- Break Balance Bento Card --}}
    @php
        $formatDuration = function(int $seconds): string {
            $minutes = (int) round($seconds / 60);
            if ($minutes < 60) {
                return "{$minutes} min";
            }
            $hours = intdiv($minutes, 60);
            $remMin = $minutes % 60;
            $hourText = ($hours === 1) ? '1 hour' : "{$hours} hours";
            return ($remMin === 0) ? $hourText : "{$hourText} {$remMin} min";
        };

        $totalSec    = $todayBreakBalance['balance_seconds'] ?? \App\Models\SlackMessage::BREAK_BALANCE_SECONDS;
        $consumedSec = $todayBreakBalance['consumed_seconds'] ?? 0;
        $remSec      = $todayBreakBalance['remaining_seconds'] ?? $totalSec;
        $exceededSec = $todayBreakBalance['exceeded_seconds'] ?? 0;

        $isExceeded   = ($remSec <= 0 && $exceededSec > 0);
        $displaySec   = $isExceeded ? $exceededSec : $remSec;
        $displayText  = $formatDuration($displaySec);
        $displayLabel = $isExceeded ? 'exceeded' : 'remaining';

        $usedText  = $formatDuration($consumedSec);
        $totalText = $formatDuration($totalSec);

        $pctUsed      = min(100, round(($consumedSec / $totalSec) * 100));
        $initialColor = $pctUsed >= 80 ? '#e22134' : ($pctUsed >= 50 ? '#ffa42b' : '#1fdf64');
    @endphp
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="spotify-card p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="background-color: rgba(255, 255, 255, 0.05); width: 42px; height: 42px;">
                            <i class="bi bi-stopwatch text-warning fs-5"></i>
                        </div>
                        <div>
                            <h6 class="text-uppercase text-white fw-bold small mb-1 opacity-75" style="letter-spacing: 0.05em;">
                                Today's Break Balance
                            </h6>
                            <p class="text-white opacity-50 small mb-0" style="font-size: 0.75rem;">
                                Breaks within your {{ $totalText }} daily allowance are counted automatically.
                            </p>
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="fw-bold fs-4" id="admin-break-balance-remaining-text" style="color: {{ $remSec <= 0 ? '#e22134' : '#ffffff' }};">{{ $displayText }}</span>
                        <div class="text-white opacity-50 small" id="admin-break-balance-label-text" style="text-transform: uppercase; font-size: 0.7rem;">{{ $displayLabel }}</div>
                    </div>
                </div>
                <div class="position-relative" style="height: 10px; border-radius: 50px; background: rgba(255,255,255,0.07); overflow: hidden; border: 1px solid rgba(255,255,255,0.06);">
                    <div id="admin-break-balance-bar" style="height: 100%; width: {{ $pctUsed }}%; border-radius: 50px; transition: width 0.6s ease, background-color 0.4s ease; background: {{ $initialColor }};"></div>
                </div>
                <div class="d-flex justify-content-between mt-2">
                    <small class="text-white opacity-40" style="font-size: 0.7rem;" id="admin-break-balance-used-label">{{ $usedText }} used</small>
                    <small class="text-white opacity-40" style="font-size: 0.7rem;">{{ $totalText }} total</small>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    (function() {
        const userId = {{ $user->id }};
        const bar    = document.getElementById('admin-break-balance-bar');
        const remTxt = document.getElementById('admin-break-balance-remaining-text');
        const lblTxt = document.getElementById('admin-break-balance-label-text');
        const usedLbl= document.getElementById('admin-break-balance-used-label');

        function formatDurationText(seconds) {
            const minutes = Math.round(seconds / 60);
            if (minutes < 60) {
                return minutes + ' min';
            }
            const hours = Math.floor(minutes / 60);
            const remMin = minutes % 60;
            const hourText = (hours === 1) ? '1 hour' : (hours + ' hours');
            return (remMin === 0) ? hourText : (hourText + ' ' + remMin + ' min');
        }

        function updateAdminBreakBalance() {
            fetch(`/session-management/track-record/api/report?user_id=${userId}&period=today`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(r => r.ok ? r.json() : null)
            .then(data => {
                if (!data || !data.summary) return;
                const total    = data.summary.break_balance_total_seconds    || 3600;
                const consumed = data.summary.break_balance_consumed_seconds  || 0;
                const remaining= data.summary.break_balance_remaining_seconds || 0;
                const exceeded = data.summary.break_balance_exceeded_seconds  || 0;

                const isExceeded   = (remaining <= 0 && exceeded > 0);
                const displaySec   = isExceeded ? exceeded : remaining;
                const displayText  = formatDurationText(displaySec);
                const displayLabel = isExceeded ? 'exceeded' : 'remaining';

                const usedPct = Math.min(100, Math.round((consumed / total) * 100));
                let color = '#1fdf64';
                if (usedPct >= 80) color = '#e22134';
                else if (usedPct >= 50) color = '#ffa42b';

                if (bar) {
                    bar.style.width = usedPct + '%';
                    bar.style.backgroundColor = color;
                }
                if (remTxt) {
                    remTxt.textContent = displayText;
                    remTxt.style.color = (remaining <= 0) ? '#e22134' : '';
                }
                if (lblTxt) {
                    lblTxt.textContent = displayLabel;
                }
                if (usedLbl) {
                    usedLbl.textContent = formatDurationText(consumed) + ' used';
                }
            })
            .catch(() => {});
        }

        updateAdminBreakBalance();
        setInterval(updateAdminBreakBalance, 15000);
    })();
    </script>
    @endpush
    @endif

    <div class="row g-3 mb-3">
        @if(!$hasSubordinates)
            {{-- -----------------------------------------------------------------
                 LEAF NODE MODE: Profile Summary Tile (7 cols) + Quick Stats Tile (5 cols)
                 ----------------------------------------------------------------- --}}
            <!-- 1. Profile Summary Card (7 cols) -->
            <div class="col-lg-7 d-flex">
                <div class="spotify-card p-4 h-100 w-100 d-flex flex-column">
                    <div class="d-flex align-items-center mb-3">
                        <div class="position-relative">
                            <div class="rounded-circle border d-flex align-items-center justify-content-center text-white"
                                 style="width: 48px; height: 48px; border-color: rgba(255, 255, 255, 0.15) !important; background-color: rgba(255, 255, 255, 0.03);">
                                <span class="fw-semibold fs-5 font-monospace" style="letter-spacing: -0.05em;">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                            </div>
                            <span class="position-absolute bottom-0 end-0 {{ $user->isApproved() ? 'bg-success' : 'bg-warning' }} border border-dark rounded-circle" style="width: 10px; height: 10px; border-width: 2px !important;"></span>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h4 class="fw-semibold text-white mb-0" style="font-size: 1.2rem; letter-spacing: -0.02em; line-height: 1.1;">{{ $user->name }}</h4>
                            <span class="text-white opacity-50 small d-block" style="font-size: 0.75rem; margin-top: 2px;">
                                {{ $user->designation?->name ?? ($user->isGuest() ? 'Guest Supervisor' : 'Employee') }}
                            </span>
                        </div>
                    </div>

                    <div class="grid-separator mb-3"></div>

                    <div class="row g-3 text-start flex-grow-1 align-content-around">
                        <div class="col-sm-6">
                            <span class="text-white small opacity-50 d-block mb-1" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.03em;">Email Address</span>
                            <span class="text-white fw-medium" style="font-size: 0.85rem; word-break: break-all;">{{ $user->email }}</span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-white small opacity-50 d-block mb-1" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.03em;">Department</span>
                            <div class="mt-1" style="max-width: 100%;">
                                <span class="row-badge-green py-0.5 px-2 d-inline-block text-wrap" style="font-size: 0.68rem; letter-spacing: 0.02em; border-radius: 50px !important; line-height: 1.3; height: auto !important; max-width: 100%; white-space: normal; word-break: break-word;">
                                    {{ $user->department?->name ?? ($user->isGuest() ? 'External' : 'No Department') }}
                                </span>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-white small opacity-50 d-block mb-1" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.03em;">Immediate Boss</span>
                            <span class="text-white fw-medium" style="font-size: 0.85rem;">{{ $user->manager?->name ?? 'Super Admin' }}</span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-white small opacity-50 d-block mb-1" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.03em;">Head of Department</span>
                            <span class="text-white fw-medium" style="font-size: 0.85rem;">{{ $user->department?->head?->name ?? '—' }}</span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-white small opacity-50 d-block mb-1" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.03em;">Phone Number</span>
                            <span class="text-white fw-medium" style="font-size: 0.85rem;">{{ $user->phone ?? '—' }}</span>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-white small opacity-50 d-block mb-1" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.03em;">Address</span>
                            <span class="text-white fw-medium" style="font-size: 0.85rem; word-break: break-word;">{{ $user->address ?? '—' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Personal Quick Stats / Guest Supervisor Overview (5 cols) -->
            @if($user->isGuest())
                <div class="col-lg-5 d-flex flex-column gap-3">
                    <div class="spotify-card p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-uppercase fw-bold opacity-50" style="font-size: 0.65rem; letter-spacing: 0.08em; color: rgba(255, 255, 255, 0.7);">Account Status</span>
                            <h4 class="fw-bold mt-1 mb-0 text-info" style="font-size: 1.3rem; letter-spacing: -0.02em;">
                                Guest Supervisor
                            </h4>
                            <span class="fw-medium text-white opacity-50 mt-0.5 d-block" style="font-size: 0.7rem;">External Team Management</span>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                             style="width: 42px; height: 42px; background-color: rgba(56, 189, 248, 0.08); border: 1px solid rgba(56, 189, 248, 0.2);">
                            <i class="bi bi-shield-check fs-5 text-info"></i>
                        </div>
                    </div>

                    <div class="spotify-card p-3 flex-grow-1 d-flex flex-column justify-content-between">
                        <div>
                            <span class="text-uppercase fw-bold opacity-50" style="font-size: 0.65rem; letter-spacing: 0.08em; color: rgba(255, 255, 255, 0.7);">Team Supervision</span>
                            <div class="grid-separator mt-1 mb-2"></div>
                            <p class="text-white opacity-50 small mb-2" style="font-size: 0.75rem;">No employees are currently assigned under your supervision. Once the Super Admin configures your team in the hierarchy, team management tools will automatically activate here.</p>
                        </div>
                        @if($user->isApproved())
                            <a href="{{ route('admin.hierarchy.index') }}" class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center gap-2 text-decoration-none mt-2" style="height: 36px;">
                                <i class="bi bi-diagram-3 fs-6"></i> View System Hierarchy
                            </a>
                        @else
                            <button class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center gap-2 mt-2 opacity-50" disabled style="height: 36px;">
                                <i class="bi bi-lock-fill fs-6"></i> Unlocks Upon HR Approval
                            </button>
                        @endif
                    </div>
                </div>
            @else
                <div class="col-lg-5 d-flex flex-column gap-3">
                    <div class="spotify-card p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-uppercase fw-bold opacity-50" style="font-size: 0.65rem; letter-spacing: 0.08em; color: rgba(255, 255, 255, 0.7);">Working Mode</span>
                            <h4 class="fw-bold mt-1 mb-0 {{ ($isRemoteToday ?? false) ? 'text-info' : 'text-success' }}" style="font-size: 1.3rem; letter-spacing: -0.02em;">
                                {{ ($isRemoteToday ?? false) ? 'Remote Work' : 'On Site' }}
                            </h4>
                            <span class="fw-medium text-white opacity-50 mt-0.5 d-block" style="font-size: 0.7rem;">Active for today</span>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                             style="width: 42px; height: 42px; background-color: {{ ($isRemoteToday ?? false) ? 'rgba(56, 189, 248, 0.08)' : 'rgba(34, 197, 94, 0.08)' }}; border: 1px solid {{ ($isRemoteToday ?? false) ? 'rgba(56, 189, 248, 0.2)' : 'rgba(34, 197, 94, 0.2)' }};">
                            <i class="bi {{ ($isRemoteToday ?? false) ? 'bi-laptop' : 'bi-building-check' }} fs-5 {{ ($isRemoteToday ?? false) ? 'text-info' : 'text-success' }}"></i>
                        </div>
                    </div>

                    <div class="spotify-card p-3 flex-grow-1 d-flex flex-column justify-content-between">
                        <div>
                            <span class="text-uppercase fw-bold opacity-50" style="font-size: 0.65rem; letter-spacing: 0.08em; color: rgba(255, 255, 255, 0.7);">Personal Records</span>
                            <div class="grid-separator mt-1 mb-2"></div>
                            <p class="text-white opacity-50 small mb-2" style="font-size: 0.75rem;">Access your historical attendance, remote work logs, and leave requests ledger anytime.</p>
                        </div>
                        @if($user->isApproved())
                            <a href="{{ route('admin.leaves.record') }}" class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center gap-2 text-decoration-none mt-2" style="height: 36px;">
                                <i class="bi bi-journal-text fs-6"></i> My Attendance &amp; Logs
                            </a>
                        @else
                            <button class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center gap-2 mt-2 opacity-50" disabled style="height: 36px;">
                                <i class="bi bi-lock-fill fs-6"></i> Unlocks Upon HR Approval
                            </button>
                        @endif
                    </div>
                </div>
            @endif

        @else
            {{-- -----------------------------------------------------------------
                 MANAGER MODE: Profile (5 cols) + Stacked Stats (3 cols) + Actions (4 cols)
                 ----------------------------------------------------------------- --}}
            <!-- 1. Profile Summary Card (5 cols) -->
            <div class="col-lg-5 col-md-12">
                <div class="spotify-card p-3 h-100 d-flex flex-column">
                    <div class="d-flex align-items-center" style="margin-top: 4px;">
                        <div class="position-relative">
                            <div class="rounded-circle border d-flex align-items-center justify-content-center text-white"
                                 style="width: 44px; height: 44px; border-color: rgba(255, 255, 255, 0.15) !important; background-color: rgba(255, 255, 255, 0.03);">
                                <span class="fw-semibold fs-5 font-monospace" style="letter-spacing: -0.05em;">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                            </div>
                            <span class="position-absolute bottom-0 end-0 bg-success border border-dark rounded-circle" style="width: 10px; height: 10px; border-width: 2px !important;"></span>
                        </div>

                        <div class="flex-grow-1 ms-3">
                            <h4 class="fw-semibold text-white mb-0" style="font-size: 1.15rem; letter-spacing: -0.02em; line-height: 1.1;">{{ $user->name }}</h4>
                            <span class="text-white opacity-50 small d-block" style="font-size: 0.72rem; margin-top: 2px;">{{ $user->designation?->name ?? ($user->isGuest() ? 'Guest Supervisor' : 'Employee') }}</span>
                        </div>
                    </div>

                    <div class="grid-separator" style="margin-top: 16px; margin-bottom: 16px;"></div>

                    <div class="row g-2 text-start flex-grow-1 align-content-around">
                        <div class="col-sm-6 col-12">
                            <span class="text-white small opacity-50 d-block mb-1" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.03em;">Email Address</span>
                            <span class="text-white fw-medium" style="font-size: 0.78rem; word-break: break-all;">{{ $user->email }}</span>
                        </div>
                        <div class="col-sm-6 col-12">
                            <span class="text-white small opacity-50 d-block mb-1" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.03em;">Department</span>
                            <div class="mt-1" style="max-width: 100%;">
                                <span class="row-badge-green py-0.5 px-2 d-inline-block text-wrap" style="font-size: 0.62rem; letter-spacing: 0.01em; border-radius: 50px !important; line-height: 1.3; height: auto !important; max-width: 100%; white-space: normal; word-break: break-word;">
                                    {{ $user->department?->name ?? ($user->isGuest() ? 'External' : 'No Department') }}
                                </span>
                            </div>
                        </div>
                        <div class="col-sm-6 col-12">
                            <span class="text-white small opacity-50 d-block mb-1" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.03em;">Head of Department</span>
                            <span class="text-white fw-medium" style="font-size: 0.78rem;">{{ $user->department?->head?->name ?? '—' }}</span>
                        </div>
                        <div class="col-sm-6 col-12">
                            <span class="text-white small opacity-50 d-block mb-1" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.03em;">Working</span>
                            @if($user->isGuest())
                                <span class="text-info fw-semibold" style="font-size: 0.78rem;">External</span>
                            @else
                                <span class="{{ ($isRemoteToday ?? false) ? 'text-info' : 'text-success' }} fw-semibold" style="font-size: 0.78rem;">{{ ($isRemoteToday ?? false) ? 'Remote' : 'On Site' }}</span>
                            @endif
                        </div>
                        <div class="col-sm-6 col-12">
                            <span class="text-white small opacity-50 d-block mb-1" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.03em;">Phone Number</span>
                            <span class="text-white fw-medium" style="font-size: 0.78rem;">{{ $user->phone ?? '—' }}</span>
                        </div>
                        <div class="col-sm-6 col-12">
                            <span class="text-white small opacity-50 d-block mb-1" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.03em;">Immediate Boss</span>
                            <span class="text-white fw-medium" style="font-size: 0.78rem;">{{ $user->manager?->name ?? 'Super Admin' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Stacked Team Stat Cards (3 cols) -->
            <div class="col-lg-3 col-md-6 d-flex flex-column gap-3">
                <!-- Direct Reports Stat Card -->
                <div class="spotify-card p-3 flex-fill d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-uppercase fw-bold opacity-50" style="font-size: 0.65rem; letter-spacing: 0.08em; color: rgba(255, 255, 255, 0.7);">Direct Reports</span>
                        <h2 class="fw-bold mt-1 mb-0" style="font-size: 1.8rem; letter-spacing: -0.04em; color: #38bdf8 !important;">{{ $teamCount }}</h2>
                        <span class="fw-medium text-white opacity-50 mt-0.5 d-block" style="font-size: 0.7rem;">Active team accounts</span>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center ms-2"
                         style="width: 44px; height: 44px; background-color: rgba(56, 189, 248, 0.08); border: 1px solid rgba(56, 189, 248, 0.2); flex-shrink: 0;">
                        <i class="bi bi-people-fill fs-5" style="color: #38bdf8 !important;"></i>
                    </div>
                </div>

                <!-- Pending Reviews Stat Card -->
                <div class="spotify-card p-3 flex-fill d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-uppercase fw-bold opacity-50" style="font-size: 0.65rem; letter-spacing: 0.08em; color: rgba(255, 255, 255, 0.7);">Pending Reviews</span>
                        <h2 class="fw-bold mt-1 mb-0 {{ $pendingLeaves > 0 ? 'text-warning' : 'text-white' }}" style="font-size: 1.8rem; letter-spacing: -0.04em;">{{ $pendingLeaves }}</h2>
                        <span class="fw-medium text-white opacity-50 mt-0.5 d-block" style="font-size: 0.7rem;">Action required</span>
                    </div>
                    <div class="rounded-circle d-flex align-items-center justify-content-center ms-2"
                         style="width: 44px; height: 44px; background-color: {{ $pendingLeaves > 0 ? 'rgba(255, 193, 7, 0.08)' : 'rgba(255, 193, 7, 0.04)' }}; border: 1px solid {{ $pendingLeaves > 0 ? 'rgba(255, 193, 7, 0.2)' : 'rgba(255, 193, 7, 0.1)' }}; flex-shrink: 0;">
                        <i class="bi bi-clock-history fs-5 {{ $pendingLeaves > 0 ? 'text-warning' : 'text-white opacity-75' }}"></i>
                    </div>
                </div>
            </div>

            <!-- 3. Team Management Actions Tile (4 cols) -->
            <div class="col-lg-4 col-md-6">
                <div class="spotify-card p-3 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <span class="text-uppercase fw-bold opacity-50" style="font-size: 0.65rem; letter-spacing: 0.08em; color: rgba(255, 255, 255, 0.7);">Team Management</span>
                        <div class="grid-separator mt-1 mb-2"></div>
                        <p class="text-white opacity-50 small mb-2" style="font-size: 0.73rem;">Quickly review pending leave requests, inspect full review history, or check team logs.</p>
                    </div>
                    <div class="d-flex flex-column gap-2 mt-auto">
                        <a href="{{ route('admin.team.leaves') }}" class="row-btn-blue w-100 d-flex align-items-center justify-content-center gap-2 text-decoration-none" style="height: 34px;">
                            <i class="bi bi-journal-check fs-6"></i> Review Team Requests ({{ $pendingLeaves }})
                        </a>
                        <a href="{{ route('admin.team.directory') }}" class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center gap-2 text-decoration-none" style="height: 34px;">
                            <i class="bi bi-people fs-6"></i> Team Directory &amp; Logs
                        </a>
                        <a href="{{ route('admin.team.leaves.history') }}" class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center gap-2 text-decoration-none" style="height: 34px;">
                            <i class="bi bi-clock-history fs-6"></i> View Review History
                        </a>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- =====================================================================
         BENTO GRID ROW 2: TODAY'S TEAM STATUS (MANAGERS ONLY)
         ===================================================================== --}}
    @if($hasSubordinates)
        <div class="row mb-3">
            <div class="col-12">
                <div class="spotify-card flush-list-card">
                    <div class="card-header-area px-3 pt-3">
                        <h6 class="text-uppercase text-white fw-semibold small mb-2 opacity-75" style="letter-spacing: 0.05em;">
                            <i class="bi bi-people-fill me-2 text-primary"></i> Today's Team Status
                        </h6>
                        <div class="grid-separator mb-2"></div>
                    </div>

                    <div class="list-group list-group-flush scrollable-stream" style="max-height: 9.5rem; height: 9.5rem; overflow-y: scroll !important;">
                        @forelse($teamStatusToday as $status)
                            @php
                                $targetUrl = '#';
                                if ($status['type'] === 'leave') {
                                    $targetUrl = route('admin.team.leaves.history', ['user_id' => $status['user']->id]);
                                } elseif ($status['type'] === 'remote') {
                                    $targetUrl = route('admin.team.attendance-logs', ['user_id' => $status['user']->id, 'type' => 'remote']);
                                } else {
                                    $targetUrl = route('admin.team.attendance-logs', ['user_id' => $status['user']->id, 'type' => 'absent']);
                                }
                            @endphp
                            <a href="{{ $targetUrl }}" class="list-group-item bg-transparent d-flex justify-content-between align-items-center py-1.5 px-3 hover-highlight text-decoration-none" style="border-bottom: 1px solid rgba(255,255,255,0.03) !important;">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-dark text-white rounded-circle p-2 d-flex align-items-center justify-content-center border border-secondary" style="width: 32px; height: 32px;">
                                        <span class="small fw-semibold text-white" style="font-size: 0.72rem;">{{ strtoupper(substr($status['user']->name, 0, 1)) }}</span>
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-white fs-6" style="letter-spacing: -0.01em; line-height: 1.1; font-size: 0.88rem !important;">{{ $status['user']->name }}</div>
                                        <small class="text-white opacity-50" style="font-size: 0.68rem; line-height: 1.1;">{{ $status['user']->department?->name ?? 'No Department' }}</small>
                                    </div>
                                </div>
                                <div>
                                    @if($status['type'] === 'leave')
                                        <span class="badge" style="font-size: 0.62rem; padding: 0.15rem 0.5rem; background-color: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.35); color: #f59e0b; border-radius: 50px !important; font-weight: normal;">On Leave ({{ $status['label'] }})</span>
                                    @elseif($status['type'] === 'remote')
                                        <span class="badge" style="font-size: 0.62rem; padding: 0.15rem 0.5rem; background-color: rgba(59, 130, 246, 0.12); border: 1px solid rgba(59, 130, 246, 0.3); color: #60a5fa; border-radius: 50px !important; font-weight: normal;">Working Remote</span>
                                    @else
                                        <span class="badge" style="font-size: 0.62rem; padding: 0.15rem 0.5rem; background-color: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; border-radius: 50px !important; font-weight: normal;">Absent</span>
                                    @endif
                                </div>
                            </a>
                        @empty
                            <div class="text-center py-4 text-white opacity-50 my-auto">
                                <div class="mb-1"><i class="bi bi-check-all text-success fs-4"></i></div>
                                <div class="fw-semibold text-white small" style="font-size: 0.8rem;">Full Team Present</div>
                                <div class="text-white opacity-50 small" style="font-size: 0.7rem;">Excellent! All team members are working on-site today.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- =====================================================================
         BENTO GRID ROW 3: APPLY FOR LEAVE CARD (FULL WIDTH - STAFF ONLY)
         ===================================================================== --}}
    @if(!$user->isGuest() && $user->isApproved())
        <div class="row my-3">
            <div class="col-12">
                <div class="spotify-card p-4">
                    <div class="card-header bg-transparent border-0 p-0 mb-3">
                        <h6 class="text-uppercase text-white fw-bold small mb-0 opacity-75" style="letter-spacing: 0.05em;">
                            <i class="bi bi-pencil-square me-2 text-primary"></i> Apply for Leave
                        </h6>
                        <div class="grid-separator mt-2"></div>
                    </div>
                    <div class="card-body p-0">
                        <div id="leave-form-alert" class="alert d-none" role="alert"></div>
                        <form id="leave-request-form" action="{{ route('admin.leave-requests.store') }}" method="POST" class="row g-3">
                            @csrf
                            <div class="col-md-4">
                                <label class="form-label small text-white opacity-50 mb-1">Leave Type</label>
                                <select name="leave_type_id" class="form-select border-0 shadow-sm text-white" required
                                        style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;">
                                    @foreach(\App\Models\LeaveType::where('is_active', true)->orderBy('name')->get() as $type)
                                        @if($type->slug !== 'absent')
                                            <option value="{{ $type->id }}" class="bg-dark text-white">{{ $type->name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-white opacity-50 mb-1">Start Date</label>
                                <input type="date" name="start_date" class="form-control border-0 shadow-sm text-white" required
                                       style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-white opacity-50 mb-1">End Date</label>
                                <input type="date" name="end_date" class="form-control border-0 shadow-sm text-white" required
                                       style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;">
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-white opacity-50 mb-1">Reason</label>
                                <textarea name="reason" class="form-control border-0 shadow-sm text-white" rows="3" placeholder="Explain the reason for leave request..." required
                                          style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;"></textarea>
                            </div>
                            <div class="col-12 d-flex justify-content-end align-items-center gap-2 mt-4">
                                <a href="{{ route('admin.leaves.record') }}" class="btn btn-outline-secondary px-4 text-decoration-none shadow-sm d-flex align-items-center justify-content-center gap-2" style="height: 38px; min-width: 215px;">
                                    <i class="bi bi-journal-text fs-6"></i> View Your Leave Record
                                </a>
                                <button type="submit" class="row-btn-blue px-4 shadow-sm d-flex align-items-center justify-content-center gap-2" style="height: 38px; border: none; min-width: 215px;">
                                    <i class="bi bi-send fs-6"></i> Submit Request
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        {{-- =====================================================================
             BENTO GRID ROW 4: PERSONAL LEAVE LEDGER (FULL WIDTH - STAFF ONLY)
             ===================================================================== --}}
        <div class="row mt-2">
            <div class="col-12">
                <div class="spotify-card flush-list-card">
                    <div class="card-header-area">
                        <h6 class="text-uppercase text-white fw-semibold small mb-0 opacity-75" style="letter-spacing: 0.05em;">
                            <i class="bi bi-pie-chart-fill me-2 text-primary"></i> My Personal Leave Ledger
                        </h6>
                        <p class="text-white opacity-50 small mt-1 mb-2">Dynamic personal leave balance tracking. Click individual leave types to expand detailed carried-forward allocations.</p>
                        <div class="grid-separator"></div>
                    </div>

                    <div class="list-group list-group-flush mt-2">
                        @php
                            $sortedBalances = $balances->filter(fn($b) => $b->leaveType && $b->leaveType->is_active)->sortBy(function($b) {
                                if ($b->leaveType->slug === 'unpaid') return 0;
                                if ($b->leaveType->slug === 'emergency') return 1;
                                if ($b->leaveType->slug === 'absent') return 2;
                                return 3;
                            });
                        @endphp
                        @foreach($sortedBalances as $index => $balance)
                            @php
                                $isUnpaid   = $balance->leaveType->slug === 'unpaid';
                                $isEmergency = $balance->leaveType->slug === 'emergency';
                                $isAbsent   = $balance->leaveType->slug === 'absent';
                                $allowed    = ($isUnpaid || $isEmergency || $isAbsent) ? $balance->used : ($balance->entitled + $balance->carried_forward);
                                $used       = $balance->used;
                                $remaining  = ($isUnpaid || $isEmergency || $isAbsent) ? $balance->used : $balance->remaining;
                                $pending    = $balance->pending;
                                $percentUsed    = $allowed > 0 ? min(100, round(($used / $allowed) * 100)) : 0;
                                $percentPending = ($allowed > 0 && $pending > 0) ? min(100, round(($pending / $allowed) * 100)) : 0;
                            @endphp

                            <div class="list-group-item bg-transparent d-flex align-items-center justify-content-between py-3 px-4 border-0 hover-highlight cursor-pointer"
                                 style="transition: background-color 0.2s ease-in-out; border-bottom: 1px solid rgba(255,255,255,0.03) !important;"
                                 data-bs-toggle="collapse"
                                 data-bs-target="#trackCollapseStats-{{ $index }}">

                                <div class="d-flex align-items-center" style="min-width: 180px;">
                                    <span class="text-white opacity-25 me-3 small font-monospace">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                                    <div>
                                        <div class="fw-semibold text-white fs-6" style="letter-spacing: -0.02em;">{{ $balance->leaveType->name }}</div>
                                        <small class="text-white opacity-50" style="font-size: 0.72rem;">{{ $year }} cycle</small>
                                    </div>
                                </div>

                                <div class="flex-grow-1 mx-4 d-none d-md-block">
                                    <div class="progress bg-dark position-relative" style="height: 6px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.06); overflow: visible;">
                                        <div class="progress-bar bg-white" style="width: {{ $percentUsed }}%; border-radius: 50px;"></div>
                                        @if($percentPending > 0)
                                            <div class="progress-bar bg-warning opacity-75" style="width: {{ $percentPending }}%; border-radius: 50px;"></div>
                                        @endif
                                    </div>
                                </div>

                                <div class="d-flex align-items-center gap-4">
                                    <div class="text-end" style="min-width: 130px;">
                                        <span class="fw-bold text-white fs-6">{{ $remaining + 0 }} Left</span>
                                        <div class="text-white opacity-50 small" style="font-size: 0.7rem; text-transform: uppercase;">of {{ $allowed + 0 }} Allowed</div>
                                    </div>
                                    <i class="bi bi-chevron-down text-white opacity-25 small"></i>
                                </div>
                            </div>

                            <div class="collapse" id="trackCollapseStats-{{ $index }}" style="background-color: rgba(255,255,255,0.015);">
                                <div class="py-3 px-5 border-bottom" style="border-color: rgba(255,255,255,0.04) !important;">
                                    <div class="row text-start py-2 g-3">
                                        <div class="col-sm-3">
                                            <label class="text-white small opacity-50 d-block mb-1" style="font-size: 0.68rem; text-transform: uppercase;">Base Entitled</label>
                                            <strong class="text-white">{{ ($isUnpaid || $isEmergency || $isAbsent) ? '∞' : ($balance->entitled + 0) . " days" }}</strong>
                                        </div>
                                        <div class="col-sm-3">
                                            <label class="text-white small opacity-50 d-block mb-1" style="font-size: 0.68rem; text-transform: uppercase;">Carried Over</label>
                                            @if($isUnpaid || $isEmergency || $isAbsent)
                                                <strong class="text-white">-</strong>
                                            @else
                                                <strong class="{{ $balance->carried_forward > 0 ? 'text-success' : 'text-white' }}">+{{ $balance->carried_forward + 0 }} days</strong>
                                            @endif
                                        </div>
                                        <div class="col-sm-3">
                                            <label class="text-white small opacity-50 d-block mb-1" style="font-size: 0.68rem; text-transform: uppercase;">Pending Approval</label>
                                            <strong class="{{ $isAbsent ? 'text-white' : 'text-warning' }}">{{ $isAbsent ? '-' : ($pending + 0) . " days" }}</strong>
                                        </div>
                                        <div class="col-sm-3">
                                            <label class="text-white small opacity-50 d-block mb-1" style="font-size: 0.68rem; text-transform: uppercase;">Utilized</label>
                                            <strong class="text-white">{{ $used + 0 }} days</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($user->isApproved())
        @push('scripts')
            <script src="{{ asset('js/leave-request.js') }}?v={{ time() }}"></script>
        @endpush
    @endif
</x-app-layout>
