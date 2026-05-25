<x-app-layout>
    <x-slot name="header">Per-Employee Leave Overrides</x-slot>

    <!-- Add/Update Form -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white fw-bold text-dark py-3">Add / Update Override</div>
        <div class="card-body p-4">
            <form method="POST" action="{{ route('super-admin.leave-settings.overrides.store') }}" class="row g-3">
                @csrf
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Employee</label>
                    <select name="user_id" class="form-select border-0 shadow-sm bg-light" required>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Leave Type</label>
                    <select name="leave_type_id" class="form-select border-0 shadow-sm bg-light" required>
                        @foreach($leaveTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Year</label>
                    <input type="number" name="year" class="form-control border-0 shadow-sm bg-light" value="{{ $year }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Entitled Days</label>
                    <input type="number" step="0.5" name="entitled" class="form-control border-0 shadow-sm bg-light" required placeholder="e.g. 15">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100 shadow-sm fw-semibold"><i class="bi bi-check-circle me-1"></i> Save Override</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters Row -->
    <form class="row g-3 mb-4 align-items-center" method="GET" id="filterForm">
        <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Employee Filter</label>
            <select name="filter_user_id" class="form-select border-0 shadow-sm" onchange="document.getElementById('filterForm').submit()">
                <option value="">All employees</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" @selected(request('filter_user_id')==$u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Leave Type Filter</label>
            <select name="filter_leave_type_id" class="form-select border-0 shadow-sm" onchange="document.getElementById('filterForm').submit()">
                <option value="">All types</option>
                @foreach($leaveTypes as $type)
                    <option value="{{ $type->id }}" @selected(request('filter_leave_type_id')==$type->id)>{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Status Filter</label>
            <select name="filter_status" class="form-select border-0 shadow-sm" onchange="document.getElementById('filterForm').submit()">
                <option value="active" @selected(request('filter_status', 'active')=='active')>Active Only</option>
                <option value="deleted" @selected(request('filter_status')=='deleted')>Deleted Only</option>
                <option value="all" @selected(request('filter_status')=='all')>All</option>
            </select>
        </div>
        <div class="col-md-3 text-end mt-auto">
            <a href="{{ route('super-admin.leave-settings.overrides') }}" class="btn btn-outline-secondary shadow-sm w-100"><i class="bi bi-x-circle me-1"></i> Clear Filters</a>
        </div>
    </form>

    <!-- Existing Overrides Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white fw-bold text-dark py-3">Existing Overrides ({{ $year }})</div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Employee</th>
                        <th>Leave Type</th>
                        <th>Year</th>
                        <th>Entitled</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($overrides as $override)
                        <tr>
                            <td class="ps-4">
                                <span class="fw-semibold text-dark">{{ $override->user->name }}</span>
                                <span class="d-block small text-muted">{{ $override->user->email }}</span>
                            </td>
                            <td>{{ $override->leaveType->name }}</td>
                            <td>{{ $override->year }}</td>
                            <td><span class="badge bg-light text-dark fw-bold px-2 py-1">{{ $override->entitled }} days</span></td>
                            <td>
                                @if($override->trashed())
                                    <span class="badge bg-danger-subtle text-danger px-2.5 py-1.5"><i class="bi bi-dash-circle me-1"></i> Deleted</span>
                                @else
                                    <span class="badge bg-success-subtle text-success px-2.5 py-1.5"><i class="bi bi-check-circle me-1"></i> Active</span>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                @if(!$override->trashed())
                                    <button type="button" class="btn btn-sm btn-outline-danger shadow-sm px-2.5" onclick="confirmDeleteOverride('{{ route('super-admin.leave-settings.overrides.destroy', $override->id) }}')">
                                        <i class="bi bi-trash me-1"></i> Delete
                                    </button>
                                @else
                                    <span class="text-muted small">None</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No overrides match the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($overrides->hasPages())
            <div class="card-footer bg-white border-0 py-3">{{ $overrides->links() }}</div>
        @endif
    </div>

    <!-- Back Button -->
    <div class="mb-4">
        <a href="{{ route('super-admin.leave-settings.index') }}" class="btn btn-secondary shadow-sm px-4 fw-semibold"><i class="bi bi-arrow-left me-1"></i> Back to Leave Settings</a>
    </div>

    <!-- Delete Confirmation Modal Popup -->
    <div class="modal fade" id="deleteOverrideModal" tabindex="-1" aria-labelledby="deleteOverrideModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-danger text-white border-0 py-3">
                    <h5 class="modal-title d-flex align-items-center fw-bold" id="deleteOverrideModalLabel">
                        <i class="bi bi-trash-fill me-2 fs-4"></i> Delete Override
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <div class="mb-3 text-danger">
                        <i class="bi bi-exclamation-triangle-fill" style="font-size: 3rem;"></i>
                    </div>
                    <h4 class="fw-bold mb-2 text-dark">Confirm Deletion</h4>
                    <p class="text-muted mb-0">
                        Are you sure you want to delete this manual override? The employee's leave balance will immediately be restored to the global default settings.
                    </p>
                </div>
                <div class="modal-footer bg-light border-0 py-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary px-4 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteOverrideForm" method="POST" action="" class="m-0">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger px-4 fw-semibold shadow-sm">Yes, Delete Override</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDeleteOverride(actionUrl) {
            const form = document.getElementById('deleteOverrideForm');
            form.action = actionUrl;
            const modal = new bootstrap.Modal(document.getElementById('deleteOverrideModal'));
            modal.show();
        }
    </script>
</x-app-layout>
