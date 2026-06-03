<x-app-layout>
    <x-slot name="header">Line Manager Dashboard</x-slot>

    <!-- Top Area: Compact Split Layout -->
    <div class="row g-3 mb-3">
        
        <!-- Left Side Panel (Profile and Who's Out Today) -->
        <div class="col-lg-8 d-flex flex-column gap-3">
            
            <!-- 1. Profile Summary Card (Compact & Sleek) -->
            <div class="spotify-card p-3">
                <div class="d-flex align-items-center" style="margin-top: 4px;">
                    <!-- Compact Initials Avatar with Green Presence Indicator -->
                    <div class="position-relative">
                        <div class="rounded-circle border d-flex align-items-center justify-content-center text-white" 
                             style="width: 44px; height: 44px; border-color: rgba(255, 255, 255, 0.15) !important; background-color: rgba(255, 255, 255, 0.03);">
                            <span class="fw-semibold fs-5 font-monospace" style="letter-spacing: -0.05em;">{{ strtoupper(substr($admin->name, 0, 1)) }}</span>
                        </div>
                        <span class="position-absolute bottom-0 end-0 bg-success border border-dark rounded-circle" style="width: 10px; height: 10px; border-width: 2px !important;"></span>
                    </div>
                    
                    <!-- Profile Header Details -->
                    <div class="flex-grow-1 ms-3">
                        <h4 class="fw-semibold text-white mb-0" style="font-size: 1.15rem; letter-spacing: -0.02em; line-height: 1.1;">{{ $admin->name }}</h4>
                        <span class="text-white opacity-50 small" style="font-size: 0.72rem;">Line Manager Account</span>
                    </div>
                </div>
                
                <!-- Grid separator with margin space above and below -->
                <div class="grid-separator" style="margin-top: 20px; margin-bottom: 20px;"></div>
                
                <!-- Compact horizontal details -->
                <div class="row g-2 text-start pt-1">
                    <div class="col-sm-4">
                        <span class="text-white small opacity-50 d-block mb-1" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.03em;">Email Address</span>
                        <span class="text-white fw-medium" style="font-size: 0.78rem;">{{ $admin->email }}</span>
                    </div>
                    <div class="col-sm-4">
                        <span class="text-white small opacity-50 d-block mb-1" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.03em;">Department Group</span>
                        <div class="mt-1 d-flex flex-wrap gap-1">
                            @forelse($admin->headedDepartments as $headed)
                                <span class="row-badge-green py-0.5 px-2" style="font-size: 0.62rem; letter-spacing: 0.01em; white-space: nowrap; height: 22px !important; display: inline-flex !important; align-items: center; justify-content: center; border-radius: 50px !important;">
                                    Head of {{ $headed->name }}
                                </span>
                            @empty
                                <span class="row-badge-green py-0.5 px-2" style="font-size: 0.62rem; letter-spacing: 0.01em; white-space: nowrap; height: 22px !important; display: inline-flex !important; align-items: center; justify-content: center; border-radius: 50px !important;">
                                    {{ $admin->department?->name ?? 'Team Line Manager' }}
                                </span>
                            @endforelse
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <span class="text-white small opacity-50 d-block mb-1" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.03em;">Access Level</span>
                        <span class="text-white fw-medium" style="font-size: 0.78rem;">Subordinate Reviewer</span>
                    </div>
                </div>
            </div>

            <!-- Today's Team Leaves Card (Compact & Flex-Aligned to Match Height) -->
            <div class="spotify-card p-3 flex-grow-1 d-flex flex-column">
                <h6 class="text-uppercase text-white fw-semibold small mb-2 opacity-75" style="letter-spacing: 0.05em;">
                    <i class="bi bi-calendar2-x-fill me-2 text-danger"></i> Who's Out Today (Team Leaves)
                </h6>
                <div class="grid-separator mb-2"></div>
                
                <div class="row g-2 flex-grow-1 align-items-center">
                    @forelse($outToday as $req)
                        <div class="col-md-6">
                            <div class="d-flex align-items-center justify-content-between p-2 rounded" style="background-color: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.06);">
                                <div>
                                    <div class="fw-semibold text-white" style="font-size: 0.82rem; letter-spacing: -0.01em; line-height: 1.1;">{{ $req->user->name }}</div>
                                    <div class="text-white opacity-50 small mt-0.5" style="font-size: 0.7rem;">{{ $req->user->department?->name ?? 'N/A' }}</div>
                                </div>
                                <span class="row-badge-green" style="font-size: 0.65rem; padding: 0.15rem 0.5rem;">{{ $req->leaveType->name }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="col-12 text-center py-3 text-white opacity-50 my-auto">
                            <div class="mb-1"><i class="bi bi-check-all text-success fs-4"></i></div>
                            <div class="fw-semibold text-white small" style="font-size: 0.8rem;">Full Team Present</div>
                            <div class="text-white opacity-50 small" style="font-size: 0.7rem;">Excellent! No active team leaves recorded for today.</div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Right Side Panel (Direct Reports, Pending and Actions - Compact stack) -->
        <div class="col-lg-4 d-flex flex-column gap-3">
            
            <!-- 1. Direct Reports Stats (Compact) -->
            <div class="spotify-card p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-uppercase fw-bold opacity-50" style="font-size: 0.65rem; letter-spacing: 0.08em; color: rgba(255, 255, 255, 0.7);">Direct Reports</span>
                    <h2 class="fw-bold mt-1 mb-0" style="font-size: 1.8rem; letter-spacing: -0.04em; color: #38bdf8 !important;">{{ $teamCount }}</h2>
                    <span class="fw-medium text-white opacity-50 mt-0.5 d-block" style="font-size: 0.7rem;">Active team accounts</span>
                </div>
                <div class="rounded-circle d-flex align-items-center justify-content-center" 
                     style="width: 42px; height: 42px; background-color: rgba(56, 189, 248, 0.08); border: 1px solid rgba(56, 189, 248, 0.2);">
                    <i class="bi bi-people-fill fs-5" style="color: #38bdf8 !important;"></i>
                </div>
            </div>

            <!-- 2. Pending Reviews Stats (Compact) -->
            <div class="spotify-card p-3 d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-uppercase fw-bold opacity-50" style="font-size: 0.65rem; letter-spacing: 0.08em; color: rgba(255, 255, 255, 0.7);">Pending Reviews</span>
                    <h2 class="fw-bold mt-1 mb-0 {{ $pendingLeaves > 0 ? 'text-warning' : 'text-white' }}" style="font-size: 1.8rem; letter-spacing: -0.04em;">{{ $pendingLeaves }}</h2>
                    <span class="fw-medium text-white opacity-50 mt-0.5 d-block" style="font-size: 0.7rem;">Action required</span>
                </div>
                <div class="rounded-circle d-flex align-items-center justify-content-center" 
                     style="width: 42px; height: 42px; background-color: {{ $pendingLeaves > 0 ? 'rgba(255, 193, 7, 0.08)' : 'rgba(255, 255, 255, 0.04)' }}; border: 1px solid {{ $pendingLeaves > 0 ? 'rgba(255, 193, 7, 0.2)' : 'rgba(255, 255, 255, 0.1)' }};">
                    <i class="bi bi-clock-history fs-5 {{ $pendingLeaves > 0 ? 'text-warning' : 'text-white opacity-75' }}"></i>
                </div>
            </div>

            <!-- 3. Review Action Shortcuts (Compact & Underline-Free) -->
            <div class="spotify-card p-3">
                <div>
                    <span class="text-uppercase fw-bold opacity-50" style="font-size: 0.65rem; letter-spacing: 0.08em; color: rgba(255, 255, 255, 0.7);">Review Shortcuts</span>
                    <div class="grid-separator mt-1 mb-2"></div>
                </div>
                <div class="d-flex flex-column gap-2 mt-2">
                    <!-- Royal Blue Filled button without any underlines -->
                    <a href="{{ route('admin.team.leaves') }}" class="row-btn-blue w-100 d-flex align-items-center justify-content-center gap-2 text-decoration-none" style="height: 34px;">
                        <i class="bi bi-journal-check fs-6"></i> Review Team Requests
                    </a>
                    <!-- Stark glass secondary button without any underlines -->
                    <a href="{{ route('admin.team.leaves.history') }}" class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center gap-2 text-decoration-none" style="height: 34px;">
                        <i class="bi bi-clock-history fs-6"></i> View Review History
                    </a>
                </div>
            </div>

        </div>
    </div>

    <!-- Bottom Area: Personal Leave Ledger (Takes 100% full width of screen) -->
    <div class="row mt-2">
        <div class="col-12">
            <!-- Spotify-Inspired Leave Tracklist -->
            <div class="spotify-card flush-list-card">
                <div class="card-header-area">
                    <!-- Heading and subtext corrected to be strictly professional corporate HRMS language -->
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
                                    <!-- Pending indicator segment (amber highlight overlay) -->
                                    @if($percentPending > 0)
                                        <div class="progress-bar bg-warning opacity-75" style="width: {{ $percentPending }}%; border-radius: 50px;"></div>
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
</x-app-layout>
