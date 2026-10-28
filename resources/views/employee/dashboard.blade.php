<x-app-layout>
    <x-slot name="header">My Dashboard</x-slot>

    @if(!$user->manager_id)
        <div id="no-boss-warning-alert-emp" class="alert alert-warning alert-dismissible fade show border-0 shadow-sm mb-4 bg-warning bg-opacity-10 text-warning d-none" style="border: 1px solid rgba(255, 193, 7, 0.15) !important;">
            <i class="bi bi-exclamation-triangle me-2"></i> You do not have an immediate boss assigned yet. Leave requests will be sent directly to the CEO/Super Admin for review.
            <button type="button" class="btn-close btn-close-white opacity-75" id="dismiss-no-boss-alert-emp" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const alertKey = 'dismissed_no_boss_alert_{{ $user->id }}';
                    const alertEl = document.getElementById('no-boss-warning-alert-emp');
                    const dismissBtn = document.getElementById('dismiss-no-boss-alert-emp');

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

    <!-- Profile & Apply Row (Equal Heights) -->
    <div class="row g-4 mb-4">
        <!-- Profile details on left (Spotify Profile Layout) -->
        <div class="col-md-4">
            <div class="spotify-card p-3 h-100 mb-0 d-flex flex-column">
                <div class="d-flex align-items-center" style="margin-top: 4px;">
                    <!-- Compact Initials Avatar with Green Presence Indicator -->
                    <div class="position-relative">
                        <div class="rounded-circle border d-flex align-items-center justify-content-center text-white" 
                             style="width: 44px; height: 44px; border-color: rgba(255, 255, 255, 0.15) !important; background-color: rgba(255, 255, 255, 0.03);">
                            <span class="fw-semibold fs-5 font-monospace" style="letter-spacing: -0.05em;">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                        </div>
                        <span class="position-absolute bottom-0 end-0 bg-success border border-dark rounded-circle" style="width: 10px; height: 10px; border-width: 2px !important;"></span>
                    </div>
                    
                    <!-- Profile Header Details -->
                    <div class="flex-grow-1 ms-3">
                        <h4 class="fw-semibold text-white mb-0" style="font-size: 1.15rem; letter-spacing: -0.02em; line-height: 1.1;">{{ $user->name }}</h4>
                        <span class="text-white opacity-50 small d-block" style="font-size: 0.72rem; margin-top: 2px;">Active Employee Account</span>
                    </div>
                </div>

                <!-- Grid separator with margin space above and below -->
                <div class="grid-separator" style="margin-top: 20px; margin-bottom: 20px;"></div>
                
                <!-- Vertically stacked details to fill space and align beautifully -->
                <div class="d-flex flex-column justify-content-around flex-grow-1 text-start pt-1 gap-3">
                    <div class="row g-2">
                        <div class="col-7">
                            <span class="text-white small opacity-50 d-block mb-1" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.03em;">Email Address</span>
                            <span class="text-white fw-medium" style="font-size: 0.85rem; word-break: break-all;">{{ $user->email }}</span>
                        </div>
                        <div class="col-5 ps-3">
                            <span class="text-white small opacity-50 d-block mb-1" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.03em;">Working</span>
                            <span class="{{ ($isRemoteToday ?? false) ? 'text-info' : 'text-success' }} fw-semibold" style="font-size: 0.85rem;">{{ ($isRemoteToday ?? false) ? 'Remote' : 'On Site' }}</span>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-7">
                            <span class="text-white small opacity-50 d-block mb-1" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.03em;">Phone Number</span>
                            <span class="text-white fw-medium" style="font-size: 0.85rem; word-break: break-all;">{{ $user->phone ?? '-' }}</span>
                        </div>
                        <div class="col-5 ps-3">
                            <span class="text-white small opacity-50 d-block mb-1" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.03em;">Emergency No.</span>
                            <span class="text-white fw-medium" style="font-size: 0.85rem; word-break: break-all;">{{ $user->emergency_number ?? '-' }}</span>
                        </div>
                    </div>
                    <div>
                        <span class="text-white small opacity-50 d-block mb-1" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.03em;">Address</span>
                        <span class="text-white fw-medium" style="font-size: 0.85rem; word-break: break-word;">{{ $user->address ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-white small opacity-50 d-block mb-1" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.03em;">Department</span>
                        <div class="mt-1">
                            <span class="row-badge-green py-0.5 px-2" style="font-size: 0.68rem; letter-spacing: 0.02em; min-height: 22px; height: auto !important; display: inline-flex !important; align-items: center; justify-content: center; border-radius: 50px !important; padding: 4px 12px; white-space: normal; text-align: center; word-break: break-word; max-width: 100%;">
                                {{ $user->department?->name ?? 'No Department' }}
                            </span>
                        </div>
                    </div>
                    <div>
                        <span class="text-white small opacity-50 d-block mb-1" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.03em;">Line Manager</span>
                        <span class="text-white fw-medium" style="font-size: 0.85rem;">{{ $user->manager?->name ?? 'CEO / Super Admin' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Apply card on right -->
        <div class="col-md-8">
            <div class="spotify-card h-100 mb-0">
                <div class="card-header-area px-4 pt-4">
                    <h6 class="text-uppercase text-white fw-bold small mb-2 opacity-75" style="letter-spacing: 0.05em;"><i class="bi bi-pencil-square me-1"></i> Apply for Leave</h6>
                    <div class="grid-separator"></div>
                </div>
                <div class="p-4 pt-2">
                    <div id="leave-form-alert" class="alert d-none" role="alert"></div>
                    <form id="leave-request-form" action="{{ route('employee.leave-requests.store') }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label small text-white opacity-50 mb-1">Leave Type</label>
                            <select name="leave_type_id" class="form-select border-0 text-white" required
                                    style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;">
                                @foreach($leaveTypes as $type)
                                    @if($type->slug !== 'absent')
                                        <option value="{{ $type->id }}" class="bg-dark text-white">{{ $type->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-white opacity-50 mb-1">Start Date</label>
                            <input type="date" name="start_date" class="form-control border-0 text-white" required
                                   style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-white opacity-50 mb-1">End Date</label>
                            <input type="date" name="end_date" class="form-control border-0 text-white" required
                                   style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;">
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-white opacity-50 mb-1">Reason</label>
                            <textarea name="reason" class="form-control border-0 text-white" rows="3" placeholder="Explain the reason for leave request..." required
                                      style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;"></textarea>
                        </div>
                        <div class="col-12 d-flex justify-content-end align-items-center gap-2 mt-4">
                            <!-- Stark Glass secondary button -->
                            <a href="{{ route('admin.leaves.record') }}" class="btn btn-outline-secondary px-4 text-decoration-none shadow-sm d-flex align-items-center justify-content-center gap-2" style="height: 38px; min-width: 215px;">
                                <i class="bi bi-journal-text fs-6"></i> View Your Leave Record
                            </a>
                            <!-- Royal Blue Filled Primary button -->
                            <button type="submit" class="row-btn-blue px-4 shadow-sm d-flex align-items-center justify-content-center gap-2" style="height: 38px; border: none; min-width: 215px;">
                                <i class="bi bi-send fs-6"></i> Submit Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Area: Personal Leave Ledger (Takes 100% full width of screen) -->
    <div class="row mt-2">
        <div class="col-12">
            <!-- Spotify-Inspired Leave Tracklist -->
            <div class="spotify-card flush-list-card">
                <div class="card-header-area px-4 pt-4">
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
                            $isUnpaid = $balance->leaveType->slug === 'unpaid';
                            $isEmergency = $balance->leaveType->slug === 'emergency';
                            $isAbsent = $balance->leaveType->slug === 'absent';
                            $allowed = ($isUnpaid || $isEmergency || $isAbsent) ? $balance->used : ($balance->entitled + $balance->carried_forward);
                            $used = $balance->used;
                            $remaining = ($isUnpaid || $isEmergency || $isAbsent) ? $balance->used : $balance->remaining;
                            $pending = $balance->pending;
                            $percentUsed = $allowed > 0 ? min(100, round(($used / $allowed) * 100)) : 0;
                            $percentPending = ($allowed > 0 && $pending > 0) ? min(100, round(($pending / $allowed) * 100)) : 0;
                        @endphp
                        
                        <!-- Leave Track Item -->
                        <div class="list-group-item bg-transparent d-flex align-items-center justify-content-between py-3 px-4 border-0 hover-highlight cursor-pointer" 
                             style="transition: background-color 0.2s ease-in-out; border-bottom: 1px solid rgba(255,255,255,0.03) !important;"
                             data-bs-toggle="collapse" 
                             data-bs-target="#trackCollapseStats-{{ $index }}">
                            
                            <!-- Index & Name -->
                            <div class="d-flex align-items-center" style="min-width: 180px;">
                                <span class="text-white opacity-25 me-3 small font-monospace">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                                <div>
                                    <div class="fw-semibold text-white fs-6" style="letter-spacing: -0.02em;">{{ $balance->leaveType->name }}</div>
                                    <small class="text-white opacity-50" style="font-size: 0.72rem;">{{ $year }} cycle</small>
                                </div>
                            </div>
                            
                            <!-- Spotify Visual Duration Progress Bar -->
                            <div class="flex-grow-1 mx-4 d-none d-md-block">
                                <div class="progress bg-dark position-relative" style="height: 6px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.06); overflow: visible;">
                                    <!-- Used indicator segment (stark white) -->
                                    <div class="progress-bar bg-white" style="width: {{ $percentUsed }}%; border-radius: 50px;"></div>
                                    <!-- Pending indicator segment (solid stark white to ensure bright output) -->
                                    @if($percentPending > 0)
                                        <div class="progress-bar bg-white" style="width: {{ $percentPending }}%; border-radius: 50px;"></div>
                                    @endif
                                </div>
                            </div>
 
                            <!-- Numeric Track Balance -->
                            <div class="d-flex align-items-center gap-4">
                                <div class="text-end" style="min-width: 130px;">
                                    <span class="fw-bold text-white fs-6">{{ $remaining + 0 }} Left</span>
                                    <div class="text-white opacity-50 small" style="font-size: 0.7rem; text-transform: uppercase;">of {{ $allowed + 0 }} Allowed</div>
                                </div>
                                <i class="bi bi-chevron-down text-white opacity-25 small"></i>
                            </div>
                        </div>
 
                        <!-- Carried Forward and Extra Detail collapse list inside the ledger -->
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
    <div class="row g-4 mb-4" id="break-balance-widget-row">
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
                    <div class="text-end" id="break-balance-remaining-display">
                        <span class="fw-bold fs-4" id="break-balance-remaining-text" style="color: {{ $remSec <= 0 ? '#e22134' : '#ffffff' }};">{{ $displayText }}</span>
                        <div class="text-white opacity-50 small" id="break-balance-label-text" style="text-transform: uppercase; font-size: 0.7rem;">{{ $displayLabel }}</div>
                    </div>
                </div>
                <div class="position-relative" style="height: 10px; border-radius: 50px; background: rgba(255,255,255,0.07); overflow: hidden; border: 1px solid rgba(255,255,255,0.06);">
                    <div id="break-balance-bar" style="height: 100%; width: {{ $pctUsed }}%; border-radius: 50px; transition: width 0.6s ease, background-color 0.4s ease; background: {{ $initialColor }};"></div>
                </div>
                <div class="d-flex justify-content-between mt-2">
                    <small class="text-white opacity-40" style="font-size: 0.7rem;" id="break-balance-used-label">{{ $usedText }} used</small>
                    <small class="text-white opacity-40" style="font-size: 0.7rem;">{{ $totalText }} total</small>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    (function() {
        const userId = {{ $user->id }};
        const bar    = document.getElementById('break-balance-bar');
        const remTxt = document.getElementById('break-balance-remaining-text');
        const lblTxt = document.getElementById('break-balance-label-text');
        const usedLbl= document.getElementById('break-balance-used-label');

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

        function updateBreakBalance() {
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

        updateBreakBalance();
        setInterval(updateBreakBalance, 15000); // refresh every 15 seconds
    })();
    </script>
    @endpush

    @push('scripts')
        <script>
            window.leaveCancelUrl = "{{ url('/admin/leave-requests') }}";
        </script>
        <script src="{{ asset('js/leave-request.js') }}?v={{ time() }}"></script>
    @endpush
</x-app-layout>

