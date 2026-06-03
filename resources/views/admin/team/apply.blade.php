<x-app-layout>
    <x-slot name="header">Apply for Leave</x-slot>

    <!-- Balances Cards Row (Same style as leave history page cards in super admin side) -->
    <div class="row g-4 mb-4">
        @foreach($balances as $balance)
            <div class="col-md-3 balance-card" data-type="{{ $balance->leaveType->name }}">
                <div class="spotify-card h-100">
                    <div class="card-header-area px-4 pt-4">
                        <h6 class="text-uppercase text-white fw-bold small mb-2 opacity-75" style="letter-spacing: 0.05em;">{{ $balance->leaveType->name }} ({{ $year }})</h6>
                        <div class="grid-separator"></div>
                    </div>
                    <div class="p-4 pt-2">
                        <div class="d-flex align-items-baseline mb-3">
                            <span class="fs-3 fw-bold text-white">{{ $balance->entitled + $balance->carried_forward }}</span>
                            <span class="text-white opacity-50 ms-1">entitled</span>
                        </div>
                        <div class="row g-0 pt-3 border-top border-secondary border-opacity-25 text-center small text-white opacity-75">
                            <div class="col-4 border-end border-secondary border-opacity-25">
                                <strong class="d-block text-white">{{ $balance->used }}</strong> Used
                            </div>
                            <div class="col-4 border-end border-secondary border-opacity-25">
                                <strong class="d-block text-warning">{{ $balance->pending }}</strong> Pending
                            </div>
                            <div class="col-4">
                                <strong class="d-block text-success">{{ $balance->remaining }}</strong> Left
                            </div>
                        </div>
                        @if($balance->carried_forward > 0)
                            <div class="text-white opacity-50 text-center small mt-3 pt-2" style="font-size: 0.7rem; border-top: 1px solid rgba(255,255,255,0.06);">
                                Includes <span class="text-success fw-semibold">{{ $balance->carried_forward }}</span> carried forward
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Apply for Leave Card (Full Width) -->
    <div class="spotify-card p-4 mb-4">
        <div class="card-header bg-transparent border-0 p-0 mb-3">
            <h6 class="text-uppercase text-white fw-bold small mb-0 opacity-75" style="letter-spacing: 0.05em;">
                <i class="bi bi-pencil-square me-2 text-primary"></i> Apply for Leave
            </h6>
            <div class="grid-separator mt-2"></div>
        </div>
        <div class="card-body p-0">
            <form id="leave-request-form" action="{{ route('employee.leave-requests.store') }}" method="POST" class="row g-3">
                @csrf
                <div class="col-md-4">
                    <label class="form-label small text-white opacity-50 mb-1">Leave Type</label>
                    <select name="leave_type_id" class="form-select border-0 shadow-sm" 
                            style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important;" required>
                        @foreach($leaveTypes as $type)
                            <option value="{{ $type->id }}" class="bg-dark text-white">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-white opacity-50 mb-1">Start Date</label>
                    <input type="date" name="start_date" class="form-control border-0 shadow-sm" 
                           style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important;" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-white opacity-50 mb-1">End Date</label>
                    <input type="date" name="end_date" class="form-control border-0 shadow-sm" 
                           style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important;" required>
                </div>
                <div class="col-12">
                    <label class="form-label small text-white opacity-50 mb-1">Reason</label>
                    <textarea name="reason" class="form-control border-0 shadow-sm" rows="3" placeholder="Explain the reason for leave request..." 
                              style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important;" required></textarea>
                </div>
                <div class="col-12 d-flex justify-content-end align-items-center gap-2 mt-4">
                    <!-- Stark Glass secondary button -->
                    <a href="{{ route('admin.leaves.record') }}" class="btn btn-outline-secondary px-4 text-decoration-none shadow-sm d-flex align-items-center gap-2" style="height: 38px;">
                        <i class="bi bi-journal-text fs-6"></i> View Your Leave Record
                    </a>
                    <!-- Royal Blue Filled Primary button -->
                    <button type="submit" class="row-btn-blue px-4 shadow-sm d-flex align-items-center gap-2" style="height: 38px; border: none;">
                        <i class="bi bi-send fs-6"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            window.leaveCancelUrl = "{{ url('/employee/leave-requests') }}";
        </script>
        <script src="{{ asset('js/leave-request.js') }}"></script>
    @endpush
</x-app-layout>
