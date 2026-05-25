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

    <!-- Leave Statistics Cards Row (Full Width Rectangle Style) -->
    <h5 class="text-uppercase text-muted fw-bold small mb-3 mt-4"><i class="bi bi-bar-chart-fill me-1"></i> Leave Entitlements & Statistics</h5>
    <div class="row g-4 mb-4">
        @foreach($balances as $balance)
            <div class="col-md-3 balance-card" data-type="{{ $balance->leaveType->name }}">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pt-3 pb-0">
                        <h6 class="text-uppercase text-muted fw-bold small mb-0">{{ $balance->leaveType->name }} ({{ $year }})</h6>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-between">
                        <div class="row text-center my-2">
                            <div class="col-3">
                                <div class="fw-bold fs-6 text-dark">{{ $balance->entitled + $balance->carried_forward }}</div>
                                <div class="text-muted small" style="font-size: 0.65rem;">Allowed</div>
                            </div>
                            <div class="col-3">
                                <div class="fw-bold fs-6 text-secondary balance-used">{{ $balance->used }}</div>
                                <div class="text-muted small" style="font-size: 0.65rem;">Used</div>
                            </div>
                            <div class="col-3">
                                <div class="fw-bold fs-6 text-warning balance-pending">{{ $balance->pending }}</div>
                                <div class="text-muted small" style="font-size: 0.65rem;">Pending</div>
                            </div>
                            <div class="col-3">
                                <div class="fw-bold fs-6 text-success balance-remaining">{{ $balance->remaining }}</div>
                                <div class="text-muted small" style="font-size: 0.65rem;">Left</div>
                            </div>
                        </div>
                        @if($balance->carried_forward > 0)
                            <div class="text-muted text-center small mt-2 pt-2 border-top" style="font-size: 0.7rem;">
                                Includes {{ $balance->carried_forward }} carried forward
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @push('scripts')
        <script>
            window.leaveCancelUrl = "{{ url('/employee/leave-requests') }}";
        </script>
        <script src="{{ asset('js/leave-request.js') }}"></script>
    @endpush
</x-app-layout>
