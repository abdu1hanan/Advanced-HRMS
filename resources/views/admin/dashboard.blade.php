<x-app-layout>
    <x-slot name="header">Line Manager Dashboard</x-slot>

    <!-- Top Row: Perfect Rectangle Layout -->
    <div class="row g-4 mb-4 align-items-stretch">
        <!-- Left Side: Employee Detail Card -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100 bg-white">
                <div class="card-header bg-primary text-white border-0 py-3 d-flex align-items-center">
                    <i class="bi bi-person-badge fs-4 me-2"></i>
                    <h6 class="text-uppercase fw-bold mb-0 small">My Profile Details</h6>
                </div>
                <div class="card-body d-flex flex-column justify-content-center p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="bg-primary-subtle text-primary rounded-circle p-3 me-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                            <i class="bi bi-person-fill fs-2"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-1 text-dark">{{ $admin->name }}</h4>
                            <span class="badge bg-secondary px-2.5 py-1.5"><i class="bi bi-shield-lock me-1"></i> Line Manager</span>
                        </div>
                    </div>
                    <div class="row g-3 border-top pt-3">
                        <div class="col-sm-6">
                            <span class="text-muted d-block small mb-0.5">Email Address</span>
                            <strong class="text-dark">{{ $admin->email }}</strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block small mb-0.5">Department</span>
                            <strong class="text-dark">{{ $admin->department?->name ?? '—' }}</strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block small mb-0.5">Employee Code</span>
                            <strong class="text-dark"><span class="badge bg-light text-dark border">{{ $admin->employee_code ?? '—' }}</span></strong>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted d-block small mb-0.5">Hierarchy Role</span>
                            <strong class="text-dark">Line Manager / Team Reviewer</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side: Subordinates & Pending Stacked -->
        <div class="col-lg-5 d-flex flex-column gap-4">
            <!-- Subordinates Card -->
            <div class="card border-0 shadow-sm flex-fill" style="background: linear-gradient(135deg, #4f46e5, #6366f1); min-height: 120px;">
                <div class="card-body d-flex align-items-center justify-content-between p-4 text-white">
                    <div>
                        <h6 class="text-uppercase fw-bold small mb-1 opacity-75">My Subordinates</h6>
                        <h2 class="fw-bold mb-0">{{ $teamCount }} Employees</h2>
                    </div>
                    <div class="bg-white-10 rounded p-3" style="background: rgba(255, 255, 255, 0.15);">
                        <i class="bi bi-people fs-2"></i>
                    </div>
                </div>
            </div>

            <!-- Pending Requests Card -->
            <div class="card border-0 shadow-sm flex-fill" style="background: linear-gradient(135deg, #e11d48, #f43f5e); min-height: 120px;">
                <div class="card-body d-flex align-items-center justify-content-between p-4 text-white">
                    <div>
                        <h6 class="text-uppercase fw-bold small mb-1 opacity-75">Pending Reviews</h6>
                        <h2 class="fw-bold mb-0">{{ $pendingLeaves }} Requests</h2>
                    </div>
                    <div class="bg-white-10 rounded p-3" style="background: rgba(255, 255, 255, 0.15);">
                        <i class="bi bi-inbox fs-2"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Second Row: Today's Leaves (Full Width Rectangle) -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 pt-4 pb-0">
            <h6 class="text-uppercase text-muted fw-bold small mb-0"><i class="bi bi-calendar2-x-fill me-1"></i> Who's Out Today (Team Leaves)</h6>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                @forelse($outToday as $req)
                    <div class="col-md-4">
                        <div class="d-flex align-items-center justify-content-between p-3 rounded bg-light border-start border-4 border-danger shadow-sm">
                            <div>
                                <div class="fw-bold text-dark">{{ $req->user->name }}</div>
                                <div class="text-muted small">{{ $req->user->department?->name ?? 'N/A' }}</div>
                            </div>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-semibold px-2.5 py-1.5">{{ $req->leaveType->name }}</span>
                        </div>
                    </div>
                @empty
                    <div class="col-12 text-center py-4 text-muted small">
                        <div class="mb-2 text-warning"><i class="bi bi-sun" style="font-size: 2.5rem;"></i></div>
                        Everyone in your team is present today!
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Third Row (End of Dashboard): Intelligent Stack of Stats -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 pt-4 pb-2">
            <h6 class="text-uppercase text-muted fw-bold small mb-0"><i class="bi bi-bar-chart-fill me-1"></i> My Personal Leave Statistics</h6>
            <p class="text-muted small mb-0">Dynamic personal leave breakdown. Click any card to expand dynamic details.</p>
        </div>
        <div class="card-body p-4">
            <div class="row g-4" id="statsAccordion">
                @foreach($balances as $index => $balance)
                    @php
                        $allowed = $balance->entitled + $balance->carried_forward;
                        $used = $balance->used;
                        $remaining = $balance->remaining;
                        $pending = $balance->pending;
                        $percentUsed = $allowed > 0 ? min(100, round(($used / $allowed) * 100)) : 0;
                    @endphp
                    <div class="col-md-4">
                        <!-- Dynamic Stat Card (Collapsible Trigger) -->
                        <div class="card border shadow-sm h-100 cursor-pointer transition-all hover-shadow-lg" 
                             style="cursor: pointer;"
                             data-bs-toggle="collapse" 
                             data-bs-target="#collapseStats-{{ $index }}" 
                             aria-expanded="false" 
                             aria-controls="collapseStats-{{ $index }}">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="fw-bold text-dark mb-0">{{ $balance->leaveType->name }}</h5>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold">{{ $remaining }} Left</span>
                                </div>
                                <div class="row text-center mb-2">
                                    <div class="col-4 border-end">
                                        <div class="text-muted small" style="font-size: 0.7rem;">Allowed</div>
                                        <strong class="fs-6 text-dark">{{ $allowed }}</strong>
                                    </div>
                                    <div class="col-4 border-end">
                                        <div class="text-muted small" style="font-size: 0.7rem;">Used</div>
                                        <strong class="fs-6 text-secondary">{{ $used }}</strong>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-muted small" style="font-size: 0.7rem;">Pending</div>
                                        <strong class="fs-6 text-warning">{{ $pending }}</strong>
                                    </div>
                                </div>
                                
                                <!-- Progress Bar indicator -->
                                <div class="progress mt-3" style="height: 6px;">
                                    <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $percentUsed }}%" aria-valuenow="{{ $percentUsed }}" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div class="d-flex justify-content-between text-muted small mt-1.5" style="font-size: 0.65rem;">
                                    <span>{{ $percentUsed }}% Used</span>
                                    <span>Click for more breakdown</span>
                                </div>

                                <!-- Collapsible Detailed Pane Inside the Stack -->
                                <div class="collapse mt-4 pt-3 border-top" id="collapseStats-{{ $index }}" data-bs-parent="#statsAccordion">
                                    <h6 class="fw-bold text-muted small mb-2">Extended Details ({{ $year }})</h6>
                                    <ul class="list-group list-group-flush small">
                                        <li class="list-group-item d-flex justify-content-between px-0 py-1.5 bg-transparent">
                                            <span>Base Entitlement</span>
                                            <strong>{{ $balance->entitled }} days</strong>
                                        </li>
                                        @if($balance->carried_forward > 0)
                                            <li class="list-group-item d-flex justify-content-between px-0 py-1.5 bg-transparent">
                                                <span>Carried Forward</span>
                                                <strong class="text-success">+{{ $balance->carried_forward }} days</strong>
                                            </li>
                                        @endif
                                        <li class="list-group-item d-flex justify-content-between px-0 py-1.5 bg-transparent">
                                            <span>Pending Review</span>
                                            <strong class="text-warning">{{ $pending }} days</strong>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between px-0 py-1.5 bg-transparent">
                                            <span>Available / Remaining</span>
                                            <strong class="text-success">{{ $remaining }} days</strong>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
