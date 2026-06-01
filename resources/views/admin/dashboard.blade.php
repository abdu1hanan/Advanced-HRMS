<x-app-layout>
    <x-slot name="header">Line Manager Dashboard</x-slot>

    <!-- Top Row: Perfect Rectangle Layout -->
    <div class="row g-4 mb-4 align-items-stretch">
        <!-- Left Side: Profile Detail Card -->
        <div class="col-lg-7">
            <div class="spotify-card dashboard-top-card p-4 d-flex flex-column justify-content-center h-100">
                <div class="d-flex align-items-center grid-separator">
                    <div class="rounded-circle border d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px; border-color: rgba(255, 255, 255, 0.15) !important;">
                        <i class="bi bi-person-fill fs-5 text-white"></i>
                    </div>
                    <div>
                        <h4 class="fw-semibold mb-0 text-white" style="letter-spacing: -0.02em; line-height: 1.1; font-size: 1.2rem;">{{ $admin->name }}</h4>
                        <span class="row-badge mt-1" style="background-color: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.15); color: #ffffff !important;"><i class="bi bi-shield-lock me-1"></i> Line Manager</span>
                    </div>
                </div>
                <div class="row g-3 text-start mt-2">
                    <div class="col-sm-6">
                        <span class="text-white small opacity-50 d-block mb-0" style="font-size: 0.72rem;">Email Address</span>
                        <strong class="text-white fw-semibold" style="font-size: 0.85rem;">{{ $admin->email }}</strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-white small opacity-50 d-block mb-0" style="font-size: 0.72rem;">Department</span>
                        <strong class="text-white fw-semibold" style="font-size: 0.85rem;">{{ $admin->department?->name ?? '—' }}</strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-white small opacity-50 d-block mb-0" style="font-size: 0.72rem;">Employee Code</span>
                        <strong class="text-white fw-semibold" style="font-size: 0.85rem;">{{ $admin->employee_code ?? '—' }}</strong>
                    </div>
                    <div class="col-sm-6">
                        <span class="text-white small opacity-50 d-block mb-0" style="font-size: 0.72rem;">Hierarchy Role</span>
                        <strong class="text-white fw-semibold" style="font-size: 0.85rem;">Team Reviewer</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Subordinates & Pending Stacked (Super Admin Aesthetic Matches) -->
        <div class="col-lg-5">
            <div class="d-flex flex-column gap-3 h-100">
                <!-- Direct Subordinates Card -->
                <div class="spotify-card p-4 d-flex flex-column justify-content-between flex-fill" style="min-height: 110px;">
                    <div>
                        <span class="text-uppercase fw-bold opacity-50" style="font-size: 0.65rem; letter-spacing: 0.08em; color: rgba(255, 255, 255, 0.7);">My Subordinates</span>
                        <h2 class="fw-bold text-white mt-2 mb-0" style="font-size: 2.1rem; letter-spacing: -0.04em;">{{ $teamCount }} Employees</h2>
                    </div>
                    <div class="d-flex align-items-center text-white opacity-50 small mt-2">
                        <i class="bi bi-people-fill me-2 fs-6"></i>
                        <span class="fw-medium" style="font-size: 0.75rem;">Direct reporting team</span>
                    </div>
                </div>

                <!-- Pending Reviews Card -->
                <div class="spotify-card p-4 d-flex flex-column justify-content-between flex-fill" style="min-height: 110px;">
                    <div>
                        <span class="text-uppercase fw-bold opacity-50" style="font-size: 0.65rem; letter-spacing: 0.08em; color: rgba(255, 255, 255, 0.7);">Pending Reviews</span>
                        <h2 class="fw-bold text-white mt-2 mb-0" style="font-size: 2.1rem; letter-spacing: -0.04em;">{{ $pendingLeaves }} Requests</h2>
                    </div>
                    <div class="d-flex align-items-center text-white opacity-50 small mt-2">
                        <i class="bi bi-clock-history me-2 fs-6 text-warning"></i>
                        <span class="fw-medium text-warning" style="font-size: 0.75rem;">Action required</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Second Row: Today's Leaves -->
    <div class="spotify-card p-4 mb-4">
        <h6 class="text-uppercase text-white fw-semibold small mb-2 opacity-75" style="letter-spacing: 0.05em;"><i class="bi bi-calendar2-x-fill me-2"></i> Who's Out Today (Team Leaves)</h6>
        <div class="grid-separator"></div>
        <div class="row g-3 mt-1">
            @forelse($outToday as $req)
                <div class="col-md-4">
                    <div class="d-flex align-items-center justify-content-between p-3 rounded" style="background-color: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.08);">
                        <div>
                            <div class="fw-semibold text-white fs-6" style="letter-spacing: -0.02em; line-height: 1.1;">{{ $req->user->name }}</div>
                            <div class="text-white opacity-50 small mt-1" style="font-size: 0.75rem;">{{ $req->user->department?->name ?? 'N/A' }}</div>
                        </div>
                        <span class="row-badge-green">{{ $req->leaveType->name }}</span>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center py-4 text-white opacity-50 small">
                    <div class="mb-2"><i class="bi bi-emoji-smile fs-3"></i></div>
                    Everyone in your team is present today!
                </div>
            @endforelse
        </div>
    </div>

    <!-- Third Row: Intelligent Stack of Personal Leave Stats -->
    <div class="spotify-card p-4 mb-4">
        <h6 class="text-uppercase text-white fw-semibold small mb-0 opacity-75" style="letter-spacing: 0.05em;"><i class="bi bi-bar-chart-fill me-2"></i> My Personal Leave Statistics</h6>
        <p class="text-white opacity-50 small mt-1 mb-2">Dynamic personal leave breakdown. Click any card to expand dynamic details.</p>
        <div class="grid-separator"></div>
        
        <div class="row g-4 mt-1" id="statsAccordion">
            @foreach($balances as $index => $balance)
                @php
                    $allowed = $balance->entitled + $balance->carried_forward;
                    $used = $balance->used;
                    $remaining = $balance->remaining;
                    $pending = $balance->pending;
                    $percentUsed = $allowed > 0 ? min(100, round(($used / $allowed) * 100)) : 0;
                @endphp
                <div class="col-md-4">
                    <!-- Collapsible Trigger Card -->
                    <div class="spotify-card h-100 p-4 transition-all" 
                         style="background-color: rgba(255, 255, 255, 0.02) !important; cursor: pointer; border-color: rgba(255,255,255,0.08) !important;"
                         data-bs-toggle="collapse" 
                         data-bs-target="#collapseStats-{{ $index }}" 
                         aria-expanded="false" 
                         aria-controls="collapseStats-{{ $index }}">
                        
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-white mb-0 fs-6" style="letter-spacing: -0.02em;">{{ $balance->leaveType->name }}</h5>
                            <span class="row-badge-green" style="height: 24px;">{{ $remaining }} Left</span>
                        </div>
                        
                        <div class="row text-center mb-2 mt-3">
                            <div class="col-4 border-end" style="border-color: rgba(255, 255, 255, 0.08) !important;">
                                <div class="text-white opacity-50 small" style="font-size: 0.68rem; text-transform: uppercase;">Allowed</div>
                                <strong class="fs-6 text-white fw-bold mt-1 d-block">{{ $allowed }}</strong>
                            </div>
                            <div class="col-4 border-end" style="border-color: rgba(255, 255, 255, 0.08) !important;">
                                <div class="text-white opacity-50 small" style="font-size: 0.68rem; text-transform: uppercase;">Used</div>
                                <strong class="fs-6 text-white fw-bold mt-1 d-block">{{ $used }}</strong>
                            </div>
                            <div class="col-4">
                                <div class="text-white opacity-50 small" style="font-size: 0.68rem; text-transform: uppercase;">Pending</div>
                                <strong class="fs-6 text-warning fw-bold mt-1 d-block">{{ $pending }}</strong>
                            </div>
                        </div>
                        
                        <!-- Premium stark white progress bar -->
                        <div class="progress mt-3 bg-dark" style="height: 6px; border: 1px solid rgba(255,255,255,0.08);">
                            <div class="progress-bar bg-white" role="progressbar" style="width: {{ $percentUsed }}%" aria-valuenow="{{ $percentUsed }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        
                        <div class="d-flex justify-content-between text-white opacity-50 small mt-2" style="font-size: 0.68rem;">
                            <span>{{ $percentUsed }}% Used</span>
                            <span>More breakdown &bull; Click</span>
                        </div>

                        <!-- Collapsible Detailed Pane Inside the Card -->
                        <div class="collapse mt-4 pt-3 border-top" style="border-color: rgba(255, 255, 255, 0.08) !important;" id="collapseStats-{{ $index }}" data-bs-parent="#statsAccordion">
                            <h6 class="fw-bold text-white small mb-2 opacity-50" style="font-size: 0.72rem;">Extended Details ({{ $year }})</h6>
                            <ul class="list-group list-group-flush small" style="background-color: transparent !important;">
                                <li class="list-group-item d-flex justify-content-between px-0 py-1.5 bg-transparent border-0 text-white">
                                    <span class="opacity-50">Base Entitlement</span>
                                    <strong class="text-white">{{ $balance->entitled }} days</strong>
                                </li>
                                @if($balance->carried_forward > 0)
                                    <li class="list-group-item d-flex justify-content-between px-0 py-1.5 bg-transparent border-0 text-white">
                                        <span class="opacity-50">Carried Forward</span>
                                        <strong class="text-success">+{{ $balance->carried_forward }} days</strong>
                                    </li>
                                @endif
                                <li class="list-group-item d-flex justify-content-between px-0 py-1.5 bg-transparent border-0 text-white">
                                    <span class="opacity-50">Pending Review</span>
                                    <strong class="text-warning">{{ $pending }} days</strong>
                                </li>
                                <li class="list-group-item d-flex justify-content-between px-0 py-1.5 bg-transparent border-0 text-white">
                                    <span class="opacity-50">Available / Remaining</span>
                                    <strong class="text-success">{{ $remaining }} days</strong>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
