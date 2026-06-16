<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <span>Per-Employee Leave Overrides</span>
            <a href="{{ route('super-admin.leave-settings.index') }}" class="row-btn text-decoration-none d-inline-flex align-items-center gap-2" style="font-size: 0.85rem; font-weight: normal; text-transform: none; line-height: 24px; padding: 6px 16px;">
                <i class="bi bi-arrow-left"></i> Back to Leave Settings
            </a>
        </div>
    </x-slot>

    <!-- Custom CSS styles to make the header span 100% width -->
    <style>
        .main-content-area h1.h3 {
            width: 100%;
        }
    </style>

    <!-- Add/Update Form -->
    <div class="spotify-card mb-4">
        <div class="card-header-area px-4 pt-4">
            <h6 class="text-uppercase text-white fw-semibold small mb-2 opacity-75" style="letter-spacing: 0.05em;">Add / Update Override</h6>
            <div class="grid-separator"></div>
        </div>
        <div class="px-4 pb-4 pt-2">
            <form method="POST" action="{{ route('super-admin.leave-settings.overrides.store') }}" class="row g-3">
                @csrf
                <div class="col-md-3">
                    <label class="form-label text-white opacity-75 small">Employee</label>
                    <select name="user_id" class="form-select bg-dark border-secondary text-white" required>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-white opacity-75 small">Leave Type</label>
                    <select name="leave_type_id" class="form-select bg-dark border-secondary text-white" required>
                        @foreach($leaveTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-white opacity-75 small">Year</label>
                    <input type="number" name="year" class="form-control bg-dark border-secondary text-white" value="{{ $year }}" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-white opacity-75 small">Entitled Days</label>
                    <input type="number" step="0.5" name="entitled" class="form-control bg-dark border-secondary text-white" required placeholder="e.g. 15">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="row-btn-blue w-100" style="height: 37px !important;"><i class="bi bi-check-circle me-1"></i> Save Override</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters Row -->
    <div class="spotify-card mb-4">
        <div class="card-header-area px-4 pt-4 d-none">
            <h6 class="text-uppercase text-white fw-semibold small mb-2 opacity-75" style="letter-spacing: 0.05em;">Filters</h6>
            <div class="grid-separator"></div>
        </div>
        <div class="p-4 pt-4">
            <form class="row g-3 align-items-center" method="GET" id="filterForm">
                <div class="col-md-3">
                    <label class="form-label text-white opacity-75 small">Employee Filter</label>
                    <select name="filter_user_id" class="form-select bg-dark border-secondary text-white" onchange="document.getElementById('filterForm').submit()">
                        <option value="">All employees</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected(request('filter_user_id')==$u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-white opacity-75 small">Leave Type Filter</label>
                    <select name="filter_leave_type_id" class="form-select bg-dark border-secondary text-white" onchange="document.getElementById('filterForm').submit()">
                        <option value="">All types</option>
                        @foreach($leaveTypes as $type)
                            <option value="{{ $type->id }}" @selected(request('filter_leave_type_id')==$type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-white opacity-75 small">Status Filter</label>
                    <select name="filter_status" class="form-select bg-dark border-secondary text-white" onchange="document.getElementById('filterForm').submit()">
                        <option value="active" @selected(request('filter_status', 'active')=='active')>Active Only</option>
                        <option value="deleted" @selected(request('filter_status')=='deleted')>Deleted Only</option>
                        <option value="all" @selected(request('filter_status')=='all')>All</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="w-100">
                        <label class="form-label d-block small">&nbsp;</label>
                        <a href="{{ route('super-admin.leave-settings.overrides') }}" class="row-btn w-100 text-decoration-none justify-content-center" style="height: 37px !important;"><i class="bi bi-x-circle me-1"></i> Clear Filters</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Existing Overrides Card -->
    <div class="spotify-card mb-4">
        <div class="card-header-area px-4 pt-4">
            <h6 class="text-uppercase text-white fw-semibold small mb-2 opacity-75" style="letter-spacing: 0.05em;">Existing Overrides ({{ $year }})</h6>
            <div class="grid-separator"></div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
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
                                <span class="fw-semibold text-white">{{ $override->user->name }}</span>
                                <span class="d-block small text-white opacity-50">{{ $override->user->email }}</span>
                            </td>
                            <td><span class="text-white opacity-75">{{ $override->leaveType->name }}</span></td>
                            <td><span class="text-white opacity-75">{{ $override->year }}</span></td>
                            <td><span class="row-badge" style="background: rgba(255, 255, 255, 0.1); color: rgba(255, 255, 255, 0.75); border: transparent;">{{ $override->entitled }} days</span></td>
                            <td>
                                @if($override->trashed())
                                    <span class="row-badge-red"><i class="bi bi-dash-circle me-1"></i> Deleted</span>
                                @else
                                    <span class="row-badge-green"><i class="bi bi-check-circle me-1"></i> Active</span>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                @if(!$override->trashed())
                                    <button type="button" class="row-btn-outline-red" onclick="confirmDeleteOverride('{{ route('super-admin.leave-settings.overrides.destroy', $override->id) }}')">
                                        <i class="bi bi-trash me-1"></i> Delete
                                    </button>
                                @else
                                    <span class="text-white opacity-50 small">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-white opacity-50 py-5">No overrides match the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($overrides->hasPages())
            <div class="px-4 py-3 border-top border-secondary border-opacity-25">{{ $overrides->links() }}</div>
        @endif
    </div>



    @push('modals')
    <!-- Delete Confirmation Modal Popup -->
    <div class="modal fade" id="deleteOverrideModal" tabindex="-1" aria-labelledby="deleteOverrideModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-white fs-6" id="deleteOverrideModalLabel">
                        <i class="bi bi-trash-fill me-2 text-danger"></i> Delete Override
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4 text-center">
                    <div class="mb-3 text-danger">
                        <i class="bi bi-exclamation-triangle-fill" style="font-size: 3rem;"></i>
                    </div>
                    <h4 class="fw-bold mb-2 text-white">Confirm Deletion</h4>
                    <p class="text-white opacity-50 mb-0 small">
                        Are you sure you want to delete this manual override? The employee's leave balance will immediately be restored to the global default settings.
                    </p>
                </div>
                <div class="modal-footer d-flex justify-content-end gap-2">
                    <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 145px;">Cancel</button>
                    <form id="deleteOverrideForm" method="POST" action="" class="m-0">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="row-btn-red" style="width: 165px;">Yes, Delete Override</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endpush

    <script>
        function confirmDeleteOverride(actionUrl) {
            const form = document.getElementById('deleteOverrideForm');
            form.action = actionUrl;
            const modal = new bootstrap.Modal(document.getElementById('deleteOverrideModal'));
            modal.show();
        }
    </script>
</x-app-layout>
