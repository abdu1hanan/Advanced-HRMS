<x-app-layout>
    <x-slot name="header">Employee Dashboard</x-slot>

    @if(!$user->manager_id)
        <div class="alert alert-warning border-0 shadow-sm mb-4 bg-warning bg-opacity-10 text-warning" style="border: 1px solid rgba(255, 193, 7, 0.15) !important;">
            <i class="bi bi-exclamation-triangle me-2"></i> You do not have a line manager assigned yet. Leave requests will be sent directly to the CEO/Super Admin for review.
        </div>
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
                        <span class="text-white opacity-50 small" style="font-size: 0.72rem;">Active Employee Account</span>
                    </div>
                </div>

                <!-- Grid separator with margin space above and below -->
                <div class="grid-separator" style="margin-top: 20px; margin-bottom: 20px;"></div>
                
                <!-- Vertically stacked details to fill space and align beautifully -->
                <div class="d-flex flex-column justify-content-around flex-grow-1 text-start pt-1 gap-3">
                    <div>
                        <span class="text-white small opacity-50 d-block mb-1" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.03em;">Email Address</span>
                        <span class="text-white fw-medium" style="font-size: 0.85rem;">{{ $user->email }}</span>
                    </div>
                    <div>
                        <span class="text-white small opacity-50 d-block mb-1" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.03em;">Department</span>
                        <div class="mt-1">
                            <span class="row-badge-green py-0.5 px-2" style="font-size: 0.68rem; letter-spacing: 0.02em; white-space: nowrap;">
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
                                    <option value="{{ $type->id }}" class="bg-dark text-white">{{ $type->name }}</option>
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
                            <a href="{{ route('employee.leaves.record') }}" class="btn btn-outline-secondary px-4 text-decoration-none shadow-sm d-flex align-items-center justify-content-center gap-2" style="height: 38px; min-width: 215px;">
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
                    @foreach($balances as $index => $balance)
                        @php
                            $allowed = $balance->entitled + $balance->carried_forward;
                            $used = $balance->used;
                            $remaining = $balance->remaining;
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
                                    <span class="fw-bold text-white fs-6">{{ $remaining }} Left</span>
                                    <div class="text-white opacity-50 small" style="font-size: 0.7rem; text-transform: uppercase;">of {{ $allowed }} Allowed</div>
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
                                        <strong class="text-white">{{ $balance->entitled }} days</strong>
                                    </div>
                                    <div class="col-sm-3">
                                        <label class="text-white small opacity-50 d-block mb-1" style="font-size: 0.68rem; text-transform: uppercase;">Carried Over</label>
                                        <strong class="{{ $balance->carried_forward > 0 ? 'text-success' : 'text-white' }}">+{{ $balance->carried_forward }} days</strong>
                                    </div>
                                    <div class="col-sm-3">
                                        <label class="text-white small opacity-50 d-block mb-1" style="font-size: 0.68rem; text-transform: uppercase;">Pending Approval</label>
                                        <strong class="text-warning">{{ $pending }} days</strong>
                                    </div>
                                    <div class="col-sm-3">
                                        <label class="text-white small opacity-50 d-block mb-1" style="font-size: 0.68rem; text-transform: uppercase;">Utilized</label>
                                        <strong class="text-white">{{ $used }} days</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            window.leaveCancelUrl = "{{ url('/employee/leave-requests') }}";
        </script>
        <script src="{{ asset('js/leave-request.js') }}"></script>
    @endpush
</x-app-layout>
