<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <span>Per-Employee Leave Overrides</span>
            <a href="{{ route('super-admin.leave-settings.index') }}" class="row-btn text-decoration-none d-inline-flex align-items-center gap-2" style="font-size: 0.85rem; font-weight: normal; text-transform: none; line-height: 24px; padding: 6px 16px;">
                <i class="bi bi-arrow-left"></i> Back to Leave Settings
            </a>
        </div>
    </x-slot>

    <!-- Custom CSS styles -->
    <style>
        .main-content-area h1.h3 {
            width: 100%;
        }
        .card-add-override {
            position: relative !important;
            z-index: 1100 !important;
            overflow: visible !important;
        }
        .card-filter-override {
            position: relative !important;
            z-index: 1050 !important;
            overflow: visible !important;
        }
        .leave-table-card {
            position: relative !important;
            z-index: 1 !important;
        }
        .department-dropdown {
            position: relative !important;
        }
        .department-dropdown .dropdown-menu {
            max-height: 200px !important;
            overflow-y: auto !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            background-color: #121212 !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.8) !important;
            z-index: 99999 !important;
        }
        .department-dropdown .dropdown-item {
            white-space: normal !important;
            word-wrap: break-word !important;
            color: #ffffff !important;
            font-size: 0.8rem !important;
        }
        .department-dropdown .dropdown-item:hover,
        .department-dropdown .dropdown-item.active {
            background-color: rgba(255, 255, 255, 0.1) !important;
            color: #ffffff !important;
        }
    </style>

    <!-- Add/Update Form -->
    <div class="spotify-card card-add-override mb-4" style="position: relative; z-index: 1100 !important; overflow: visible !important;">
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
                        <option value="" disabled selected>Select employee</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-white opacity-75 small">Leave Type</label>
                    @php
                        $firstType = $leaveTypes->first();
                        $firstTypeId = $firstType ? $firstType->id : '';
                        $firstTypeName = $firstType ? $firstType->name : 'Select Leave Type';
                        $selectedAddType = old('leave_type_id', $firstTypeId);
                        $selectedAddTypeObj = $leaveTypes->firstWhere('id', $selectedAddType);
                        $selectedAddTypeLabel = $selectedAddTypeObj ? $selectedAddTypeObj->name : $firstTypeName;
                    @endphp
                    <input type="hidden" name="leave_type_id" id="form_leave_type_id" value="{{ $selectedAddType }}">
                    <div class="dropdown department-dropdown w-100">
                        <button class="form-select border-0 shadow-sm text-start text-white w-100" 
                                type="button" 
                                id="addLeaveTypeDropdown" 
                                data-bs-toggle="dropdown" 
                                aria-expanded="false"
                                style="border: 1px solid rgba(255, 255, 255, 0.15) !important; background-color: rgba(255, 255, 255, 0.03); border-radius: 4px; height: 37px; font-size: 0.85rem; color: #ffffff !important;">
                            <span class="text-truncate d-inline-block w-100" id="addLeaveTypeBtnLabel" style="max-width: calc(100% - 15px);">{{ $selectedAddTypeLabel }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark p-0 w-100" aria-labelledby="addLeaveTypeDropdown">
                            @foreach($leaveTypes as $type)
                                <li>
                                    <a class="dropdown-item py-2 text-white @if($selectedAddType == $type->id) active @endif" 
                                       href="#" 
                                       onclick="selectAddLeaveType(event, '{{ $type->id }}', '{{ addslashes($type->name) }}')">{{ $type->name }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
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
    <div class="spotify-card card-filter-override mb-4" style="position: relative; z-index: 1050 !important; overflow: visible !important;">
        <div class="card-header-area px-4 pt-4 d-none">
            <h6 class="text-uppercase text-white fw-semibold small mb-2 opacity-75" style="letter-spacing: 0.05em;">Filters</h6>
            <div class="grid-separator"></div>
        </div>
        <div class="p-4 pt-4">
            <form class="row g-3 align-items-center" method="GET" id="filterForm">
                @php
                    $currentFilterType = $leaveTypes->firstWhere('id', request('filter_leave_type_id'));
                    $currentFilterTypeLabel = $currentFilterType ? $currentFilterType->name : 'All types';

                    $statusLabels = [
                        'active' => 'Active Only',
                        'deleted' => 'Deleted Only',
                        'all' => 'All',
                    ];
                    $currentStatus = request('filter_status', 'active');
                    $currentStatusLabel = $statusLabels[$currentStatus] ?? 'Active Only';
                @endphp

                <input type="hidden" name="filter_leave_type_id" id="filter_leave_type_id" value="{{ request('filter_leave_type_id') }}">
                <input type="hidden" name="filter_status" id="filter_status" value="{{ $currentStatus }}">

                <div class="col-md-3">
                    <label class="form-label text-white opacity-75 small">Employee Filter</label>
                    <select name="filter_user_id" class="form-select searchable-select border-0 shadow-sm" onchange="document.getElementById('filterForm').submit()" style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important; height: 37px;">
                        <option value="" class="bg-dark text-white">All employees</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected(request('filter_user_id')==$u->id) class="bg-dark text-white">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-white opacity-75 small">Leave Type Filter</label>
                    <div class="dropdown department-dropdown w-100">
                        <button class="form-select border-0 shadow-sm text-start text-white w-100" 
                                type="button" 
                                id="filterTypeDropdown" 
                                data-bs-toggle="dropdown" 
                                aria-expanded="false"
                                style="border: 1px solid rgba(255, 255, 255, 0.15) !important; background-color: rgba(255, 255, 255, 0.03); border-radius: 4px; height: 37px; font-size: 0.85rem; color: #ffffff !important;">
                            <span class="text-truncate d-inline-block w-100" style="max-width: calc(100% - 15px);">{{ $currentFilterTypeLabel }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark p-0 w-100" aria-labelledby="filterTypeDropdown">
                            <li>
                                <a class="dropdown-item py-2 text-white @if(!request('filter_leave_type_id')) active @endif" 
                                   href="#" 
                                   onclick="selectFilterOption(event, 'filter_leave_type_id', '')">All types</a>
                            </li>
                            @foreach($leaveTypes as $type)
                                <li>
                                    <a class="dropdown-item py-2 text-white @if(request('filter_leave_type_id') == $type->id) active @endif" 
                                       href="#" 
                                       onclick="selectFilterOption(event, 'filter_leave_type_id', '{{ $type->id }}')">{{ $type->name }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-white opacity-75 small">Status Filter</label>
                    <div class="dropdown department-dropdown w-100">
                        <button class="form-select border-0 shadow-sm text-start text-white w-100" 
                                type="button" 
                                id="filterStatusDropdown" 
                                data-bs-toggle="dropdown" 
                                aria-expanded="false"
                                style="border: 1px solid rgba(255, 255, 255, 0.15) !important; background-color: rgba(255, 255, 255, 0.03); border-radius: 4px; height: 37px; font-size: 0.85rem; color: #ffffff !important;">
                            <span class="text-truncate d-inline-block w-100" style="max-width: calc(100% - 15px);">{{ $currentStatusLabel }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark p-0 w-100" aria-labelledby="filterStatusDropdown">
                            @foreach($statusLabels as $val => $lbl)
                                <li>
                                    <a class="dropdown-item py-2 text-white @if($currentStatus === $val) active @endif" 
                                       href="#" 
                                       onclick="selectFilterOption(event, 'filter_status', '{{ $val }}')">{{ $lbl }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
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
    <div class="spotify-card leave-table-card mb-4" style="position: relative; z-index: 1 !important;">
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
            <div class="modal-content border-0 shadow" style="background-color: #0c0c0c !important; border: 1px solid rgba(255, 255, 255, 0.08) !important; border-radius: 12px !important;">
                <div class="modal-body text-center p-4">
                    <div class="text-danger mb-3">
                        <i class="bi bi-exclamation-triangle-fill" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-white">Delete Leave Override?</h5>
                    <p class="text-white opacity-70 small mb-4">
                        Are you sure you want to delete this manual override? The employee's leave balance will immediately be restored to the global default settings.
                    </p>
                    
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 120px; border-radius: 50px !important;">Cancel</button>
                        <form id="deleteOverrideForm" method="POST" action="" class="m-0">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="row-btn text-white border-0" style="width: 180px; border-radius: 50px !important; background-color: #ef4444 !important; font-weight: 500;">Yes, Delete Override</button>
                        </form>
                    </div>
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

        function selectAddLeaveType(e, val, label) {
            e.preventDefault();
            document.getElementById('form_leave_type_id').value = val;
            document.getElementById('addLeaveTypeBtnLabel').textContent = label;
        }

        function selectFilterOption(e, inputId, value) {
            e.preventDefault();
            const input = document.getElementById(inputId);
            if (input) {
                input.value = value;
            }
            const form = document.getElementById('filterForm');
            if (form) {
                form.submit();
            }
        }
    </script>
</x-app-layout>
