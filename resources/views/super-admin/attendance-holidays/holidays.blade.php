<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <span>Company Holidays Management</span>
        </div>
    </x-slot>

    <!-- Top Section: Side-by-Side Equal Height Cards in One Form -->
    <form id="markHolidayForm" action="{{ route('super-admin.attendance-holidays.holidays.store') }}" method="POST">
        @csrf
        <input type="hidden" name="editing_record_ids" id="editingRecordIds" value="">
        
        <div class="row g-4 mb-4 align-items-stretch">
            <!-- Left Card: Mark / Edit Company Holiday -->
            <div class="col-lg-6 d-flex">
                <div class="spotify-card p-4 w-100 d-flex flex-column justify-content-between">
                    <div>
                        <h6 class="text-uppercase text-white fw-bold mb-3 opacity-75 small" id="holidayFormCardTitle">
                            Mark Company Holiday
                        </h6>
                        <hr class="border-secondary opacity-25 mb-4">

                        <!-- Row 1: Holiday Label / Name (Full Width) -->
                        <div class="mb-3">
                            <label class="form-label small text-white opacity-70">Holiday Label / Name</label>
                            <input type="text" name="name" id="holidayName" class="form-control text-white" placeholder="e.g. Eid ul Fitr, Independence Day" required
                                   style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; border-radius: 50px !important; height: 38px;">
                        </div>

                        <!-- Row 2: Date Fields (Two Columns in One Row) -->
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label small text-white opacity-70">Start Date</label>
                                <input type="date" name="start_date" id="holidayStart" class="form-control border-0 text-white" required
                                       style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; border-radius: 50px !important; height: 38px;">
                            </div>
                            <div class="col-6">
                                <label class="form-label small text-white opacity-70">End Date</label>
                                <input type="date" name="end_date" id="holidayEnd" class="form-control border-0 text-white" required
                                       style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; border-radius: 50px !important; height: 38px;">
                            </div>
                        </div>
                    </div>

                    <!-- Row 3: Save / Update Holiday Button (Full Width) -->
                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" id="saveHolidayBtn" class="row-btn-blue flex-grow-1" style="height: 38px !important; border-radius: 50px !important;">
                            <i class="bi bi-check-circle me-1" id="saveHolidayBtnIcon"></i><span id="saveHolidayBtnText">Save Holiday</span>
                        </button>
                        <button type="button" id="btnCancelEditHoliday" class="row-btn d-none" style="height: 38px !important; border-radius: 50px !important; width: 110px;">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Card: Select Target Audience -->
            <div class="col-lg-6 d-flex">
                <div class="spotify-card p-4 w-100 d-flex flex-column justify-content-between" style="overflow: visible !important; position: relative; z-index: 30;">
                    <div>
                        <h6 class="text-uppercase text-white fw-bold mb-3 opacity-75 small">
                            Select Target Audience
                        </h6>
                        <hr class="border-secondary opacity-25 mb-4">

                        <!-- Field 1: Target Scope Selector (Full Width) -->
                        <div class="mb-3">
                            <label class="form-label small text-white opacity-70">Target Audience / Scope</label>
                            <select name="target_type" id="targetTypeSelect" class="form-select text-white" style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; border-radius: 50px !important; height: 38px;">
                                <option value="all" class="bg-dark text-white">All Employees (Company-wide)</option>
                                <option value="department" class="bg-dark text-white">By Department</option>
                                <option value="employee" class="bg-dark text-white">Custom Employees</option>
                            </select>
                        </div>

                        <!-- Field 2: Selection Rule (Full Width) -->
                        <div class="mb-3" id="targetActionGroup">
                            <label class="form-label small text-white opacity-70" id="targetActionLabel">Selection Rule</label>
                            
                            <!-- Active Select (when By Department or Custom Employees) -->
                            <select name="target_action" id="targetActionSelect" class="form-select text-white d-none" style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; border-radius: 50px !important; height: 38px; cursor: pointer;">
                                <option value="include" class="bg-dark text-white">Include Selected</option>
                                <option value="exclude" class="bg-dark text-white">Exclude Selected (All others receive holiday)</option>
                            </select>

                            <!-- Disabled Placeholder (when All Employees is chosen) -->
                            <div id="targetActionDisabledPlaceholder" style="cursor: not-allowed !important;">
                                <input type="text" class="form-control text-white-50 border-0 opacity-50" value="Include All (Default)" readonly
                                       style="background-color: rgba(255, 255, 255, 0.02); border: 1px dashed rgba(255, 255, 255, 0.15) !important; border-radius: 50px !important; height: 38px; cursor: not-allowed !important; pointer-events: none;">
                            </div>
                        </div>

                        <!-- Field 3: Scope Selection Dropdown (Full Width) -->
                        <div class="mb-0" id="targetSelectionGroup" style="position: relative; z-index: 1050;">
                            <label class="form-label small text-white opacity-70" id="targetSelectionLabel">Scope Selection</label>
                            
                            <!-- Disabled Placeholder when All Employees is chosen -->
                            <div id="targetDisabledPlaceholder" style="cursor: not-allowed !important;">
                                <input type="text" class="form-control text-white-50 border-0 opacity-50" value="Applies to all company employees" readonly
                                       style="background-color: rgba(255, 255, 255, 0.02); border: 1px dashed rgba(255, 255, 255, 0.15) !important; border-radius: 50px !important; height: 38px; cursor: not-allowed !important; pointer-events: none;">
                            </div>

                            <!-- Department Selector Dropdown (Active when By Department) -->
                            <div class="dropdown d-none" id="departmentSelectorDropdown">
                                <input type="text" id="deptSearchInput" class="form-select text-start text-white border-0 w-100 py-2 px-3"
                                       placeholder="-- Select Departments --" readonly data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
                                       style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; border-radius: 50px !important; font-size: 0.9rem; height: 38px; cursor: pointer;">
                                <div id="hiddenDeptInputs"></div>
                                <div class="dropdown-menu dropdown-menu-dark p-2 w-100 shadow border-0" id="deptDropdownMenu"
                                     style="background-color: #121212; border: 1px solid rgba(255, 255, 255, 0.08) !important; max-height: 220px; overflow-y: auto; border-radius: 12px !important; z-index: 1060;">
                                    <div class="dropdown-item py-1 px-2 mb-2 border-bottom border-secondary border-opacity-25 pb-2 d-flex align-items-center" style="cursor: pointer;" id="selectAllDeptsRow">
                                        <input type="checkbox" id="selectAllDepts" class="me-2" style="width: 15px; height: 15px; accent-color: #3b82f6; cursor: pointer;">
                                        <span class="text-white small fw-bold">Select All Departments</span>
                                    </div>
                                    @foreach($departments as $dept)
                                        <div class="dropdown-item py-1 px-2 rounded dept-option-row d-flex align-items-center" data-id="{{ $dept->id }}" data-name="{{ strtolower($dept->name) }}" style="cursor: pointer;">
                                            <input type="checkbox" class="me-2 dept-checkbox" value="{{ $dept->id }}" id="deptCheck_{{ $dept->id }}" style="width: 15px; height: 15px; accent-color: #3b82f6; cursor: pointer;">
                                            <span class="text-white small">{{ $dept->name }} ({{ $dept->code ?? 'N/A' }})</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Employee Selector Dropdown (Active when Custom Employees) -->
                            <div class="dropdown d-none" id="employeeSelectorDropdown">
                                <input type="text" id="empSearchInput" class="form-select text-start text-white border-0 w-100 py-2 px-3"
                                       placeholder="-- Select Employees --" autocomplete="off" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
                                       style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; border-radius: 50px !important; font-size: 0.9rem; height: 38px; cursor: pointer;">
                                <div id="hiddenEmpInputs"></div>
                                <div class="dropdown-menu dropdown-menu-dark p-2 w-100 shadow border-0" id="empDropdownMenu"
                                     style="background-color: #121212; border: 1px solid rgba(255, 255, 255, 0.08) !important; max-height: 220px; overflow-y: auto; border-radius: 12px !important; z-index: 1060;">
                                    <div class="dropdown-item py-1 px-2 mb-2 border-bottom border-secondary border-opacity-25 pb-2 d-flex align-items-center" style="cursor: pointer;" id="selectAllEmpsRow">
                                        <input type="checkbox" id="selectAllEmps" class="me-2" style="width: 15px; height: 15px; accent-color: #3b82f6; cursor: pointer;">
                                        <span class="text-white small fw-bold">Select All Employees</span>
                                    </div>
                                    <div id="empOptionsList">
                                        @foreach($users as $user)
                                            <div class="dropdown-item py-1 px-2 rounded emp-option-row d-flex align-items-center" data-id="{{ $user->id }}" data-name="{{ strtolower($user->name) }}" style="cursor: pointer;">
                                                <input type="checkbox" class="me-2 emp-checkbox" value="{{ $user->id }}" id="empCheck_{{ $user->id }}" style="width: 15px; height: 15px; accent-color: #3b82f6; cursor: pointer;">
                                                <span class="text-white small">{{ $user->name }} ({{ $user->employee_code ?? 'No Code' }}) — <span class="opacity-50">{{ $user->department->name ?? 'Unassigned' }}</span></span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Middle Card: Filter Card -->
    <div class="spotify-card mb-4">
        <div class="p-4">
            <form class="row g-3 align-items-end" method="GET" id="filterForm" action="{{ route('super-admin.attendance-holidays.holidays') }}">
                <div class="col-md-3">
                    <label class="form-label text-white opacity-50 small mb-1">Search Holiday Title</label>
                    <div class="position-relative">
                        <!-- Search Icon inside field -->
                        <i class="bi bi-search position-absolute text-white-50" style="left: 14px; top: 50%; transform: translateY(-50%); font-size: 0.9rem;"></i>
                        <input type="text" name="search" class="form-control text-white" placeholder="Search by name..." value="{{ request('search') }}" oninput="document.getElementById('filterForm').submit()"
                               style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; font-size: 0.9rem; padding-left: 42px !important; height: 38px; border-radius: 50px !important;">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-white opacity-50 small mb-1">Start Date</label>
                    <input type="date" name="start_date" class="form-control border-0 text-white" value="{{ request('start_date') }}" onchange="document.getElementById('filterForm').submit()"
                           style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; border-radius: 50px !important; height: 38px;">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-white opacity-50 small mb-1">End Date</label>
                    <input type="date" name="end_date" class="form-control border-0 text-white" value="{{ request('end_date') }}" onchange="document.getElementById('filterForm').submit()"
                           style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; border-radius: 50px !important; height: 38px;">
                </div>
                <div class="col-md-3">
                    <a href="{{ route('super-admin.attendance-holidays.holidays') }}" class="row-btn w-100 text-decoration-none justify-content-center" style="height: 38px !important; border-radius: 50px !important; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="bi bi-x-circle me-1"></i> Clear Filters
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Bottom Card: Company Holidays List -->
    <div class="spotify-card mb-4 p-4">
        <h6 class="text-uppercase text-white fw-bold mb-3 opacity-75 small">
            Company Holidays Log
        </h6>
        <hr class="border-secondary opacity-25 mb-3">

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="border-bottom border-secondary border-opacity-25 text-white opacity-50 small text-uppercase">
                        <th class="ps-3">Holiday Label</th>
                        <th>Date</th>
                        <th>Day of Week</th>
                        <th style="padding-left: 20px !important;">Target Scope</th>
                        <th>Created By</th>
                        <th class="text-end pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($holidays as $hol)
                        @php
                            $badge = $hol->target_badge ?? $hol->getTargetBadgeData();
                            $applicableUsers = $hol->getApplicableUsers();
                        @endphp
                        <tr class="text-white" style="border-bottom: 1px solid rgba(255, 255, 255, 0.03) !important;">
                            <td class="ps-3 fw-medium">{{ $hol->name }}</td>
                            <td class="font-monospace text-info">{{ $hol->date_range_display }}</td>
                            <td>
                                <span class="small opacity-75">
                                    {{ $hol->days_count === 1 ? $hol->date->format('l') : Carbon\Carbon::parse($hol->start_date)->format('D') . ' – ' . Carbon\Carbon::parse($hol->end_date)->format('D') }}
                                </span>
                            </td>
                            <td style="padding-left: 20px !important;">
                                <span class="d-inline-block btn-trigger-view-eligible"
                                      style="cursor: pointer;"
                                      data-holiday-name="{{ $hol->name }}"
                                      data-holiday-date="{{ $hol->date_range_display }}"
                                      data-target-label="{{ $badge['label'] }}"
                                      data-target-details="{{ $badge['details'] }}"
                                      data-users="{{ json_encode($applicableUsers->map(fn($u) => ['name' => $u->name, 'code' => $u->employee_code ?? '—', 'dept' => $u->department->name ?? 'Unassigned', 'role' => $u->designation->name ?? $u->role->name ?? 'Staff'])) }}"
                                      data-custom-tooltip="Click to view eligible employees"
                                      data-tooltip-placement="top">
                                    <span class="{{ $badge['badge_class'] }} d-inline-flex align-items-center gap-1 cursor-pointer" style="font-size: 0.72rem; padding: 0.25rem 0.75rem; border-radius: 50px;">
                                        {{ $badge['label'] }}
                                        <i class="bi bi-info-circle ms-1 opacity-75"></i>
                                    </span>
                                </span>
                            </td>
                            <td>
                                <span class="row-badge-green">{{ $hol->creator->name ?? 'System' }}</span>
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-inline-flex align-items-center gap-2">
                                    <!-- Blue outline edit button -->
                                    <button type="button" class="row-btn-outline-blue btn-sm px-3 btn-trigger-edit-holiday"
                                            data-holiday-name="{{ $hol->name }}"
                                            data-start-date="{{ $hol->start_date }}"
                                            data-end-date="{{ $hol->end_date }}"
                                            data-target-type="{{ $hol->target_type }}"
                                            data-target-action="{{ $hol->target_action }}"
                                            data-dept-ids="{{ json_encode($hol->departments->pluck('id')->toArray()) }}"
                                            data-user-ids="{{ json_encode($hol->users->pluck('id')->toArray()) }}"
                                            data-record-ids="{{ implode(',', $hol->record_ids) }}"
                                            style="height: 28px !important; line-height: 26px !important; font-size: 0.8rem; padding: 0 14px !important; border-radius: 50px !important;">
                                        <i class="bi bi-pencil me-1"></i> Edit
                                    </button>

                                    <!-- Red outline delete button -->
                                    <form action="{{ route('super-admin.attendance-holidays.holidays.destroy', $hol->id) }}" method="POST" class="d-inline holiday-delete-form" id="deleteForm_{{ $hol->id }}">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="record_ids" value="{{ implode(',', $hol->record_ids) }}">
                                        <button type="button" class="row-btn-outline-red btn-sm px-3 btn-trigger-delete-holiday" 
                                                data-form-id="deleteForm_{{ $hol->id }}" 
                                                data-holiday-name="{{ $hol->name }}"
                                                style="height: 28px !important; line-height: 26px !important; font-size: 0.8rem; padding: 0 14px !important; border-radius: 50px !important;">
                                            <i class="bi bi-trash me-1"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-white opacity-50 py-5">
                                <div class="mb-2"><i class="bi bi-calendar-x fs-3 text-warning"></i></div>
                                No company holidays found matching filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4 px-2">
            {{ $holidays->appends(request()->input())->links() }}
        </div>
    </div>

    @push('modals')
    <!-- Modal: View Eligible Employees for Holiday -->
    <div class="modal fade" id="viewEligibleModal" tabindex="-1" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow" style="background-color: #0c0c0c !important; border: 1px solid rgba(255, 255, 255, 0.08) !important; border-radius: 12px !important;">
                <div class="modal-header border-bottom border-secondary border-opacity-25 pb-3">
                    <div>
                        <h5 class="modal-title text-white fw-bold mb-1" id="eligibleModalHolidayTitle">Holiday Eligible Employees</h5>
                        <div class="small text-white-50" id="eligibleModalSubtitle"></div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Oval Target Scope Container -->
                    <div class="p-3 mb-3 d-flex justify-content-between align-items-center" style="border-radius: 50px !important; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.1) !important;">
                        <div>
                            <span class="small text-white fw-semibold ms-2">Target Scope</span>
                        </div>
                        <span class="row-badge-blue me-1" id="eligibleModalCountBadge" style="font-size: 0.75rem; padding: 0.25rem 0.85rem; border-radius: 50px;"></span>
                    </div>

                    <div class="mb-3 position-relative">
                        <i class="bi bi-search position-absolute text-white-50" style="left: 14px; top: 50%; transform: translateY(-50%); font-size: 0.85rem;"></i>
                        <input type="text" id="eligibleSearchInput" class="form-control text-white" placeholder="Search eligible employees..." style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; font-size: 0.85rem; padding-left: 38px !important; height: 34px; border-radius: 50px !important;">
                    </div>

                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr class="border-bottom border-secondary border-opacity-25 text-white opacity-50 small text-uppercase" style="font-size: 0.75rem;">
                                    <th class="ps-3">Sr. No.</th>
                                    <th>Employee</th>
                                    <th>Code</th>
                                    <th>Department</th>
                                    <th>Designation / Role</th>
                                </tr>
                            </thead>
                            <tbody id="eligibleUsersTableBody">
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-25 py-2">
                    <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 100px; border-radius: 50px !important;">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Delete Confirmation Modal -->
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="background-color: #0c0c0c !important; border: 1px solid rgba(255, 255, 255, 0.08) !important; border-radius: 12px !important;">
                <div class="modal-body text-center p-4">
                    <div class="text-danger mb-3">
                        <i class="bi bi-exclamation-triangle-fill" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-white">Delete Company Holiday?</h5>
                    <p class="text-white opacity-70 small mb-4">
                        Are you sure you want to delete the holiday '<strong id="confirmDeleteHolidayName" class="text-white"></strong>'?
                    </p>
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 120px; border-radius: 50px !important;">Cancel</button>
                        <button type="button" class="row-btn text-white border-0" id="btnConfirmDeleteHoliday" style="width: 140px; border-radius: 50px !important; background-color: #ef4444 !important; font-weight: 500;">Yes, Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hard Block: Duplicate Holiday Modal -->
    <div class="modal fade" id="holidayBlockModal" tabindex="-1" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="background-color: #0c0c0c !important; border: 1px solid rgba(255, 255, 255, 0.08) !important; border-radius: 12px !important;">
                <div class="modal-body text-center p-4">
                    <div class="text-danger mb-3">
                        <i class="bi bi-exclamation-triangle-fill" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-white">Holiday Already Exists</h5>
                    <p class="text-white opacity-70 small mb-4" id="holidayBlockMessage"></p>
                    
                    <div class="d-flex justify-content-center">
                        <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 120px; border-radius: 50px !important;">OK</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Smart Confirmation: Holiday Conflicts With Existing Leaves/Absents -->
    <div class="modal fade" id="holidayConfirmModal" tabindex="-1" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow" style="background-color: #0c0c0c !important; border: 1px solid rgba(255, 255, 255, 0.08) !important; border-radius: 12px !important;">
                <div class="modal-body text-center p-4">
                    <div class="text-warning mb-3">
                        <i class="bi bi-exclamation-circle-fill" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-white">Conflicts Detected</h5>
                    <p class="text-white opacity-70 small mb-4">
                        Declaring this holiday will <span class="text-warning">automatically cancel</span> the following leave requests for affected employees and restore their balances. Existing absent records will also be removed.
                    </p>
                    
                    <div id="holidayConflictLeaveList" class="mb-4 text-start" style="max-height: 250px; overflow-y: auto; padding-right: 5px;"></div>
                    
                    <div id="holidayConflictAbsentNote" class="small text-warning mb-4 d-none text-center">
                        <i class="bi-info-circle me-1"></i><span id="holidayConflictAbsentText"></span>
                    </div>

                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 120px; border-radius: 50px !important;">Cancel</button>
                        <button type="button" id="btnConfirmHolidayForce" class="row-btn text-black border-0" style="width: 220px; border-radius: 50px !important; background-color: #f59e0b !important; font-weight: 500;">Yes, Cancel Leaves & Save</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endpush

    @push('scripts')
    <script>
        $(function() {
            let activeFormId = null;

            // ---- Scope Selection UI Handling ----
            const $targetTypeSelect = $('#targetTypeSelect');
            const $targetActionSelect = $('#targetActionSelect');
            const $targetActionDisabledPlaceholder = $('#targetActionDisabledPlaceholder');
            const $targetDisabledPlaceholder = $('#targetDisabledPlaceholder');
            const $deptDropdown = $('#departmentSelectorDropdown');
            const $empDropdown = $('#employeeSelectorDropdown');

            function updateScopeVisibility() {
                const val = $targetTypeSelect.val();
                if (val === 'all') {
                    // Show identical dashed placeholder for Field 2 and Field 3
                    $targetActionSelect.addClass('d-none').prop('disabled', true);
                    $targetActionDisabledPlaceholder.removeClass('d-none');

                    $targetDisabledPlaceholder.removeClass('d-none');
                    $deptDropdown.addClass('d-none');
                    $empDropdown.addClass('d-none');
                } else if (val === 'department') {
                    $targetActionSelect.removeClass('d-none').prop('disabled', false);
                    $targetActionDisabledPlaceholder.addClass('d-none');

                    $targetDisabledPlaceholder.addClass('d-none');
                    $deptDropdown.removeClass('d-none');
                    $empDropdown.addClass('d-none');
                } else if (val === 'employee') {
                    $targetActionSelect.removeClass('d-none').prop('disabled', false);
                    $targetActionDisabledPlaceholder.addClass('d-none');

                    $targetDisabledPlaceholder.addClass('d-none');
                    $deptDropdown.addClass('d-none');
                    $empDropdown.removeClass('d-none');
                }
            }

            $targetTypeSelect.on('change', updateScopeVisibility);
            updateScopeVisibility(); // initial load

            // ---- Department Checklist Sync ----
            function syncDeptSelection() {
                const selected = [];
                $('#hiddenDeptInputs').empty();
                $('.dept-checkbox:checked').each(function() {
                    const id = $(this).val();
                    const name = $(this).closest('.dept-option-row').find('span').text().trim();
                    selected.push({ id: id, name: name });
                    $('#hiddenDeptInputs').append(`<input type="hidden" name="department_ids[]" value="${id}">`);
                });

                if (selected.length === 0) {
                    $('#deptSearchInput').val('');
                    $('#selectAllDepts').prop('checked', false);
                } else if (selected.length === 1) {
                    $('#deptSearchInput').val(selected[0].name);
                    $('#selectAllDepts').prop('checked', selected.length === $('.dept-checkbox').length);
                } else {
                    $('#deptSearchInput').val(selected.length + ' Departments Selected');
                    $('#selectAllDepts').prop('checked', selected.length === $('.dept-checkbox').length);
                }
            }

            $('.dept-checkbox').on('change', syncDeptSelection);

            $('#selectAllDepts').on('change', function() {
                const checked = $(this).is(':checked');
                $('.dept-checkbox').prop('checked', checked);
                syncDeptSelection();
            });

            // ---- Employee Checklist Sync & Search ----
            function syncEmpSelection() {
                const selected = [];
                $('#hiddenEmpInputs').empty();
                $('.emp-checkbox:checked').each(function() {
                    const id = $(this).val();
                    const name = $(this).closest('.emp-option-row').find('span').first().text().trim();
                    selected.push({ id: id, name: name });
                    $('#hiddenEmpInputs').append(`<input type="hidden" name="user_ids[]" value="${id}">`);
                });

                if (selected.length === 0) {
                    if (!$('#empSearchInput').is(':focus')) $('#empSearchInput').val('');
                    $('#selectAllEmps').prop('checked', false);
                } else if (selected.length === 1) {
                    if (!$('#empSearchInput').is(':focus')) $('#empSearchInput').val(selected[0].name);
                    $('#selectAllEmps').prop('checked', selected.length === $('.emp-checkbox').length);
                } else {
                    if (!$('#empSearchInput').is(':focus')) $('#empSearchInput').val(selected.length + ' Employees Selected');
                    $('#selectAllEmps').prop('checked', selected.length === $('.emp-checkbox').length);
                }
            }

            $('.emp-checkbox').on('change', syncEmpSelection);

            $('#selectAllEmps').on('change', function() {
                const checked = $(this).is(':checked');
                $('.emp-checkbox').prop('checked', checked);
                syncEmpSelection();
            });

            $('#empSearchInput').on('input', function() {
                const q = $(this).val().toLowerCase();
                $('#empOptionsList .emp-option-row').each(function() {
                    const text = $(this).text().toLowerCase();
                    $(this).toggle(text.includes(q));
                });
            });

            // ---- Edit Holiday ----
            $('.btn-trigger-edit-holiday').on('click', function() {
                const holidayName = $(this).data('holiday-name');
                const startDate = $(this).data('start-date');
                const endDate = $(this).data('end-date');
                const targetType = $(this).data('target-type') || 'all';
                const targetAction = $(this).data('target-action') || 'include';
                const deptIds = $(this).data('dept-ids') || [];
                const userIds = $(this).data('user-ids') || [];
                const recordIds = $(this).data('record-ids') || '';

                // Set hidden editing ID
                $('#editingRecordIds').val(recordIds);

                // Fill inputs
                $('#holidayName').val(holidayName);
                $('#holidayStart').val(startDate);
                $('#holidayEnd').val(endDate);
                $('#targetTypeSelect').val(targetType);
                $('#targetActionSelect').val(targetAction);

                // Reset checklists
                $('.dept-checkbox').prop('checked', false);
                deptIds.forEach(id => $(`#deptCheck_${id}`).prop('checked', true));
                syncDeptSelection();

                $('.emp-checkbox').prop('checked', false);
                userIds.forEach(id => $(`#empCheck_${id}`).prop('checked', true));
                syncEmpSelection();

                updateScopeVisibility();

                // Switch UI to Edit mode (Green Update button)
                $('#holidayFormCardTitle').text('Edit Company Holiday');
                $('#saveHolidayBtn').removeClass('row-btn-blue').addClass('row-btn-green');
                $('#saveHolidayBtnIcon').removeClass('bi-check-circle').addClass('bi-check2-circle');
                $('#saveHolidayBtnText').text('Update Holiday');
                $('#btnCancelEditHoliday').removeClass('d-none');

                // Smooth scroll to form
                $('html, body').animate({ scrollTop: $('#markHolidayForm').offset().top - 80 }, 300);
            });

            $('#btnCancelEditHoliday').on('click', function() {
                resetHolidayForm();
            });

            function resetHolidayForm() {
                $('#editingRecordIds').val('');
                $('#holidayName').val('');
                $('#holidayStart').val('');
                $('#holidayEnd').val('');
                $('#targetTypeSelect').val('all');
                $('#targetActionSelect').val('include');
                $('.dept-checkbox').prop('checked', false);
                syncDeptSelection();
                $('.emp-checkbox').prop('checked', false);
                syncEmpSelection();
                updateScopeVisibility();

                $('#holidayFormCardTitle').text('Mark Company Holiday');
                $('#saveHolidayBtn').removeClass('row-btn-green').addClass('row-btn-blue');
                $('#saveHolidayBtnIcon').removeClass('bi-check2-circle').addClass('bi-check-circle');
                $('#saveHolidayBtnText').text('Save Holiday');
                $('#btnCancelEditHoliday').addClass('d-none');
            }

            // ---- Delete holiday ----
            $('.btn-trigger-delete-holiday').on('click', function() {
                activeFormId = $(this).data('form-id');
                const holidayName = $(this).data('holiday-name');
                $('#confirmDeleteHolidayName').text(holidayName);
                bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmDeleteModal')).show();
            });

            $('#btnConfirmDeleteHoliday').on('click', function() {
                if (activeFormId) document.getElementById(activeFormId).submit();
            });

            // ---- View Eligible Employees Modal ----
            let currentModalUsers = [];
            $('.btn-trigger-view-eligible').on('click', function() {
                const holidayName = $(this).data('holiday-name');
                const holidayDate = $(this).data('holiday-date');
                const targetDetails = $(this).data('target-details');
                const users = $(this).data('users') || [];
                currentModalUsers = users;

                $('#eligibleModalHolidayTitle').text(holidayName);
                $('#eligibleModalSubtitle').text(holidayDate);
                $('#eligibleModalScopeText').text(targetDetails);
                $('#eligibleModalCountBadge').text(users.length + ' Eligible Employee' + (users.length !== 1 ? 's' : ''));
                $('#eligibleSearchInput').val('');

                renderEligibleUsers(users);
                bootstrap.Modal.getOrCreateInstance(document.getElementById('viewEligibleModal')).show();
            });

            function renderEligibleUsers(usersList) {
                let html = '';
                if (usersList.length === 0) {
                    html = `<tr><td colspan="5" class="text-center text-white-50 py-4">No eligible employees found.</td></tr>`;
                } else {
                    usersList.forEach((u, i) => {
                        html += `
                            <tr class="text-white" style="border-bottom: 1px solid rgba(255, 255, 255, 0.03) !important;">
                                <td class="ps-3 opacity-50 small">${i + 1}</td>
                                <td class="fw-medium">${u.name}</td>
                                <td class="font-monospace opacity-75 small">${u.code}</td>
                                <td><span class="badge bg-secondary bg-opacity-25 text-white-50 border border-secondary border-opacity-25 px-2 py-1 rounded-pill">${u.dept}</span></td>
                                <td class="small opacity-75">${u.role}</td>
                            </tr>
                        `;
                    });
                }
                $('#eligibleUsersTableBody').html(html);
            }

            $('#eligibleSearchInput').on('input', function() {
                const q = $(this).val().toLowerCase().trim();
                if (!q) {
                    renderEligibleUsers(currentModalUsers);
                    return;
                }
                const filtered = currentModalUsers.filter(u => 
                    u.name.toLowerCase().includes(q) || 
                    u.code.toLowerCase().includes(q) || 
                    u.dept.toLowerCase().includes(q) || 
                    u.role.toLowerCase().includes(q)
                );
                renderEligibleUsers(filtered);
            });

            // ---- Save / Update holiday via AJAX with conflict detection ----
            let pendingForce = false;

            function submitHolidayForm(force) {
                const targetType = $('#targetTypeSelect').val();
                const targetAction = $('#targetActionSelect').val();
                const totalDepts = {{ count($departments) }};
                const totalEmps = {{ count($users) }};
                const selectedDeptCount = $('.dept-checkbox:checked').length;
                const selectedEmpCount = $('.emp-checkbox:checked').length;

                // Validation: Exclude All
                if (targetType === 'department' && targetAction === 'exclude' && selectedDeptCount >= totalDepts) {
                    if (window.showToast) window.showToast('A holiday cannot be marked. Someone has to be selected.', 'danger');
                    return;
                }

                if (targetType === 'employee' && targetAction === 'exclude' && selectedEmpCount >= totalEmps) {
                    if (window.showToast) window.showToast('A holiday cannot be marked. Someone has to be selected.', 'danger');
                    return;
                }

                const btn = $('#saveHolidayBtn');
                const isEditing = Boolean($('#editingRecordIds').val());
                btn.prop('disabled', true).html(`<span class="spinner-border spinner-border-sm me-2"></span>${isEditing ? 'Updating...' : 'Saving...'}`);

                // Ensure target_action is enabled so formData collects it
                $('#targetActionSelect').prop('disabled', false);

                const formData = new FormData(document.getElementById('markHolidayForm'));
                if (force) formData.set('force', '1');

                fetch($('#markHolidayForm').attr('action'), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: formData,
                })
                .then(async res => {
                    const data = await res.json();
                    btn.prop('disabled', false).html(`<i class="bi ${isEditing ? 'bi-check2-circle' : 'bi-check-circle'} me-1"></i>${isEditing ? 'Update Holiday' : 'Save Holiday'}`);

                    if (data.success) {
                        if (window.showToast) window.showToast(data.message, 'success');
                        setTimeout(() => location.reload(), 1200);
                        return;
                    }

                    if (data.conflict_type === 'duplicate_holiday') {
                        $('#holidayBlockMessage').text(data.message);
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('holidayBlockModal')).show();
                        return;
                    }

                    if (data.conflict_type === 'needs_confirmation') {
                        // Build leave list HTML
                        let html = '';
                        if (data.affected_leaves && data.affected_leaves.length > 0) {
                            html += '<div class="small mb-2 text-white-50 text-uppercase fw-semibold" style="letter-spacing:.05em;">Affected Leave Requests</div>';
                            html += '<div class="d-flex flex-column gap-2">';
                            data.affected_leaves.forEach(l => {
                                const dates = l.from === l.to ? l.from : l.from + ' → ' + l.to;
                                const statusColor = l.status === 'Approved' ? '#10b981' : '#f59e0b';
                                html += `<div style="background:rgba(255,255,255,0.04);border-radius:10px;padding:10px 14px;border:1px solid rgba(255,255,255,0.07);">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-white fw-medium">${l.employee}</span>
                                        <span style="font-size:0.75rem;color:${statusColor};background:rgba(255,255,255,0.05);padding:2px 10px;border-radius:50px;">${l.status}</span>
                                    </div>
                                    <div class="text-white-50 small mt-1">${l.leave_type} &middot; ${dates} &middot; ${l.days} day${l.days != 1 ? 's' : ''}</div>
                                </div>`;
                            });
                            html += '</div>';
                        }
                        $('#holidayConflictLeaveList').html(html);

                        if (data.absent_count > 0) {
                            $('#holidayConflictAbsentText').text(`${data.absent_count} absent record${data.absent_count > 1 ? 's' : ''} will also be removed for affected employees.`);
                            $('#holidayConflictAbsentNote').removeClass('d-none');
                        } else {
                            $('#holidayConflictAbsentNote').addClass('d-none');
                        }

                        bootstrap.Modal.getOrCreateInstance(document.getElementById('holidayConfirmModal')).show();
                        pendingForce = true;
                        return;
                    }

                    // Generic error
                    if (window.showToast) window.showToast(data.message || 'An error occurred.', 'danger');
                })
                .catch(() => {
                    btn.prop('disabled', false).html(`<i class="bi ${isEditing ? 'bi-check2-circle' : 'bi-check-circle'} me-1"></i>${isEditing ? 'Update Holiday' : 'Save Holiday'}`);
                    if (window.showToast) window.showToast('An unexpected error occurred.', 'danger');
                });
            }

            $('#markHolidayForm').on('submit', function(e) {
                e.preventDefault();
                submitHolidayForm(false);
            });

            $('#btnConfirmHolidayForce').on('click', function() {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('holidayConfirmModal')).hide();
                if (pendingForce) {
                    pendingForce = false;
                    submitHolidayForm(true);
                }
            });
        });
    </script>
    @endpush
</x-app-layout>
