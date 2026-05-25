<x-app-layout>
    <x-slot name="header">Employee Dashboard</x-slot>

    @if(!$user->manager_id)
        <div class="alert alert-warning border-0 shadow-sm mb-4">
            <i class="bi bi-exclamation-triangle me-2"></i> You do not have a line manager assigned yet. Leave requests will be sent directly to the CEO/Super Admin for review.
        </div>
    @endif

    <!-- Profile & Apply Row (Equal Heights) -->
    <div class="row g-4 mb-4">
        <!-- Profile details on left -->
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header bg-primary text-white border-0 py-3">
                    <h6 class="text-uppercase fw-bold mb-0 small"><i class="bi bi-person-circle me-1"></i> My Profile</h6>
                </div>
                <div class="card-body d-flex flex-column justify-content-center">
                    <div class="mb-3">
                        <label class="text-muted small mb-0">Name</label>
                        <div class="fw-semibold text-dark">{{ $user->name }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small mb-0">Email</label>
                        <div class="fw-semibold text-dark">{{ $user->email }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small mb-0">Department</label>
                        <div class="fw-semibold text-dark">{{ $user->department?->name ?? '—' }}</div>
                    </div>
                    <div class="mb-0">
                        <label class="text-muted small mb-0">Line Manager</label>
                        <div class="fw-semibold text-dark">{{ $user->manager?->name ?? 'CEO / Super Admin' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Apply card on right -->
        <div class="col-md-8">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h6 class="text-uppercase text-muted fw-bold small mb-0"><i class="bi bi-pencil-square me-1"></i> Apply for Leave</h6>
                </div>
                <div class="card-body d-flex flex-column justify-content-between">
                    <div id="leave-form-alert" class="alert d-none" role="alert"></div>
                    <form id="leave-request-form" action="{{ route('employee.leave-requests.store') }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-1">Leave Type</label>
                            <select name="leave_type_id" class="form-select border shadow-sm" required>
                                @foreach($leaveTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-1">Start Date</label>
                            <input type="date" name="start_date" class="form-control border shadow-sm" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-1">End Date</label>
                            <input type="date" name="end_date" class="form-control border shadow-sm" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small text-muted mb-1">Reason</label>
                            <textarea name="reason" class="form-control border shadow-sm" rows="3" placeholder="Explain the reason for leave request..." required></textarea>
                        </div>
                        <div class="col-12 d-flex justify-content-end align-items-center gap-2 mt-3">
                            <a href="{{ route('employee.leaves.record') }}" class="btn btn-outline-secondary px-4 shadow-sm"><i class="bi bi-journal-text me-1"></i> View Your Leave Record</a>
                            <button type="submit" class="btn btn-primary px-4 shadow-sm"><i class="bi bi-send me-1"></i> Submit Request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Leave Statistics Accordion (Intelligent Personal Stack) -->
    <div class="card border-0 shadow-sm mb-4 mt-4">
        <div class="card-header bg-white border-0 pt-4 pb-2">
            <h6 class="text-uppercase text-muted fw-bold small mb-0"><i class="bi bi-bar-chart-fill me-1"></i> My Leave Entitlements & Statistics</h6>
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
                    <div class="col-md-4 balance-card" data-type="{{ $balance->leaveType->name }}">
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
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold balance-remaining">{{ $remaining }} Left</span>
                                </div>
                                <div class="row text-center mb-2">
                                    <div class="col-4 border-end">
                                        <div class="text-muted small" style="font-size: 0.7rem;">Allowed</div>
                                        <strong class="fs-6 text-dark">{{ $allowed }}</strong>
                                    </div>
                                    <div class="col-4 border-end">
                                        <div class="text-muted small" style="font-size: 0.7rem;">Used</div>
                                        <strong class="fs-6 text-secondary balance-used">{{ $used }}</strong>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-muted small" style="font-size: 0.7rem;">Pending</div>
                                        <strong class="fs-6 text-warning balance-pending">{{ $pending }}</strong>
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

    @push('scripts')
        <script>
            window.leaveCancelUrl = "{{ url('/employee/leave-requests') }}";
        </script>
        <script src="{{ asset('js/leave-request.js') }}"></script>
    @endpush
</x-app-layout>
