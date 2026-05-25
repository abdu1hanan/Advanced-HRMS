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
                                    <form method="POST" action="{{ route('super-admin.leave-settings.overrides.destroy', $override->id) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this manual override? The employee\'s leave balance will immediately be restored to the global default settings.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm px-2.5"><i class="bi bi-trash me-1"></i> Delete</button>
                                    </form>
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
</x-app-layout>
