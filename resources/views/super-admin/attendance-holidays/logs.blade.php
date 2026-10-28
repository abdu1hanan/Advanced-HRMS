<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <span>{{ $type === 'remote' ? 'Remote Work Logs' : ($type === 'leave' ? 'Marked Leave Logs' : 'Absence Logs') }}</span>
            <a href="{{ ($readOnly ?? false) ? route('admin.team.directory') : route('super-admin.attendance-holidays.absents') }}" class="row-btn text-decoration-none d-inline-flex align-items-center gap-2" style="font-size: 0.85rem; font-weight: normal; text-transform: none; line-height: 24px; padding: 6px 16px;">
                <i class="bi bi-arrow-left"></i> {{ ($readOnly ?? false) ? 'Back to Subordinate Directory' : 'Back to Attendance Flags' }}
            </a>
        </div>
    </x-slot>

    <!-- Custom CSS styles to make the header span 100% width and clean dropdown menus -->
    <style>
        .main-content-area h1.h3 {
            width: 100%;
        }
        .department-dropdown .dropdown-menu {
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

    <!-- Filter Card (same style as Leave History) -->
    <div class="spotify-card mb-4" style="position: relative; z-index: 1050 !important; overflow: visible !important;">
        <div class="p-4">
            <form class="row g-3 align-items-end" method="GET" id="filterForm" action="{{ ($readOnly ?? false) ? route('admin.team.attendance-logs') : route('super-admin.attendance-holidays.absents.logs') }}">
                <input type="hidden" name="type" value="{{ $type }}">
                @php
                    $currentEmp = $users->firstWhere('id', request('user_id'));
                    $currentEmpLabel = $currentEmp ? $currentEmp->name : 'All employees';

                    $currentTypeObj = $leaveTypes->firstWhere('id', request('leave_type_id'));
                    $currentTypeLabel = $currentTypeObj ? $currentTypeObj->name : 'All leave types';
                @endphp

                @if($type === 'leave')
                    <div class="col-lg-3 col-md-4">
                        <label class="form-label text-white opacity-50 small mb-1">Employee Filter</label>
                        <div class="dropdown department-dropdown w-100">
                            <button class="form-select border-0 shadow-sm text-start text-white w-100" 
                                    type="button" 
                                    id="empDropdown" 
                                    data-bs-toggle="dropdown" 
                                    aria-expanded="false"
                                    style="border: 1px solid rgba(255, 255, 255, 0.15) !important; background-color: rgba(255, 255, 255, 0.03); border-radius: 4px; height: 37px; font-size: 0.85rem; color: #ffffff !important;">
                                <span class="text-truncate d-inline-block w-100" style="max-width: calc(100% - 15px);">{{ $currentEmpLabel }}</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-dark p-0 w-100" aria-labelledby="empDropdown" style="max-height: 220px; overflow-y: auto;">
                                <li>
                                    <a class="dropdown-item py-2 text-white @if(!request('user_id')) active @endif" 
                                       href="#" 
                                       onclick="selectLogsFilter(event, 'filter_user_id', '')">All employees</a>
                                </li>
                                @foreach($users as $u)
                                    <li>
                                        <a class="dropdown-item py-2 text-white @if(request('user_id') == $u->id) active @endif" 
                                           href="#" 
                                           onclick="selectLogsFilter(event, 'filter_user_id', '{{ $u->id }}')">{{ $u->name }}</a>
                                    </li>
                                @endforeach
                            </ul>
                            <input type="hidden" name="user_id" id="filter_user_id" value="{{ request('user_id') }}">
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-4">
                        <label class="form-label text-white opacity-50 small mb-1">Leave Type</label>
                        <div class="dropdown department-dropdown w-100">
                            <button class="form-select border-0 shadow-sm text-start text-white w-100" 
                                    type="button" 
                                    id="ltDropdown" 
                                    data-bs-toggle="dropdown" 
                                    aria-expanded="false"
                                    style="border: 1px solid rgba(255, 255, 255, 0.15) !important; background-color: rgba(255, 255, 255, 0.03); border-radius: 4px; height: 37px; font-size: 0.85rem; color: #ffffff !important;">
                                <span class="text-truncate d-inline-block w-100" style="max-width: calc(100% - 15px);">{{ $currentTypeLabel }}</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-dark p-0 w-100" aria-labelledby="ltDropdown" style="max-height: 220px; overflow-y: auto;">
                                <li>
                                    <a class="dropdown-item py-2 text-white @if(!request('leave_type_id')) active @endif" 
                                       href="#" 
                                       onclick="selectLogsFilter(event, 'filter_leave_type_id', '')">All leave types</a>
                                </li>
                                @foreach($leaveTypes as $lt)
                                    <li>
                                        <a class="dropdown-item py-2 text-white @if(request('leave_type_id') == $lt->id) active @endif" 
                                           href="#" 
                                           onclick="selectLogsFilter(event, 'filter_leave_type_id', '{{ $lt->id }}')">{{ $lt->name }}</a>
                                    </li>
                                @endforeach
                            </ul>
                            <input type="hidden" name="leave_type_id" id="filter_leave_type_id" value="{{ request('leave_type_id') }}">
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label text-white opacity-50 small mb-1">Month</label>
                        <select name="month" class="form-select border-0 text-white" onchange="document.getElementById('filterForm').submit()"
                                style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; height: 37px; font-size: 0.85rem;">
                            @for($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" {{ ($selectedMonth ?? date('n')) == $m ? 'selected' : '' }} class="bg-dark text-white">
                                    {{ DateTime::createFromFormat('!m', $m)->format('F') }}
                                </option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label text-white opacity-50 small mb-1">Year</label>
                        <input type="number" name="year" class="form-control border-0 text-white" value="{{ $selectedYear ?? date('Y') }}" min="2020" max="2035" onchange="document.getElementById('filterForm').submit()"
                               style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; height: 37px; font-size: 0.85rem;">
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <a href="{{ ($readOnly ?? false) ? route('admin.team.attendance-logs', ['type' => 'leave']) : route('super-admin.attendance-holidays.absents.logs', ['type' => 'leave']) }}" class="row-btn w-100 text-decoration-none justify-content-center" style="height: 37px !important;">
                            <i class="bi bi-x-circle me-1"></i> Clear
                        </a>
                    </div>
                @else
                    <div class="col-lg-4 col-md-4">
                        <label class="form-label text-white opacity-50 small mb-1">Employee Filter</label>
                        <div class="dropdown department-dropdown w-100">
                            <button class="form-select border-0 shadow-sm text-start text-white w-100" 
                                    type="button" 
                                    id="empDropdown" 
                                    data-bs-toggle="dropdown" 
                                    aria-expanded="false"
                                    style="border: 1px solid rgba(255, 255, 255, 0.15) !important; background-color: rgba(255, 255, 255, 0.03); border-radius: 4px; height: 37px; font-size: 0.85rem; color: #ffffff !important;">
                                <span class="text-truncate d-inline-block w-100" style="max-width: calc(100% - 15px);">{{ $currentEmpLabel }}</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-dark p-0 w-100" aria-labelledby="empDropdown" style="max-height: 220px; overflow-y: auto;">
                                <li>
                                    <a class="dropdown-item py-2 text-white @if(!request('user_id')) active @endif" 
                                       href="#" 
                                       onclick="selectLogsFilter(event, 'filter_user_id', '')">All employees</a>
                                </li>
                                @foreach($users as $u)
                                    <li>
                                        <a class="dropdown-item py-2 text-white @if(request('user_id') == $u->id) active @endif" 
                                           href="#" 
                                           onclick="selectLogsFilter(event, 'filter_user_id', '{{ $u->id }}')">{{ $u->name }}</a>
                                    </li>
                                @endforeach
                            </ul>
                            <input type="hidden" name="user_id" id="filter_user_id" value="{{ request('user_id') }}">
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-4">
                        <label class="form-label text-white opacity-50 small mb-1">Month</label>
                        <select name="month" class="form-select border-0 text-white" onchange="document.getElementById('filterForm').submit()"
                                style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; height: 37px; font-size: 0.85rem;">
                            @for($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" {{ ($selectedMonth ?? date('n')) == $m ? 'selected' : '' }} class="bg-dark text-white">
                                    {{ DateTime::createFromFormat('!m', $m)->format('F') }}
                                </option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label text-white opacity-50 small mb-1">Year</label>
                        <input type="number" name="year" class="form-control border-0 text-white" value="{{ $selectedYear ?? date('Y') }}" min="2020" max="2035" onchange="document.getElementById('filterForm').submit()"
                               style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; height: 37px; font-size: 0.85rem;">
                    </div>
                    <div class="col-lg-3 col-md-4">
                        <a href="{{ ($readOnly ?? false) ? route('admin.team.attendance-logs', ['type' => $type]) : route('super-admin.attendance-holidays.absents.logs', ['type' => $type]) }}" class="row-btn w-100 text-decoration-none justify-content-center" style="height: 37px !important;">
                            <i class="bi bi-x-circle me-1"></i> Clear Filters
                        </a>
                    </div>
                @endif
            </form>
        </div>
    </div>

    <!-- Logs Table Card (No icon in header) -->
    <div class="spotify-card mb-4 p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h6 class="text-uppercase text-white fw-bold mb-0 opacity-75 small">
                    {{ $type === 'remote' ? 'Remote Work Log List' : ($type === 'leave' ? 'Marked Leave Log List' : 'Absence Log List') }}
                </h6>
            </div>
            <!-- Mode Switch Pill Toggles -->
            <div class="d-flex gap-2 p-1" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 50px;">
                <a href="{{ ($readOnly ?? false) ? route('admin.team.attendance-logs', array_merge(request()->query(), ['type' => 'absent'])) : route('super-admin.attendance-holidays.absents.logs', array_merge(request()->query(), ['type' => 'absent'])) }}" 
                   class="btn btn-sm px-3 text-white fw-semibold" 
                   style="border-radius: 50px; background-color: {{ $type === 'absent' ? '#3b82f6' : 'transparent' }}; font-size: 0.8rem; transition: all 0.2s;">
                    Absent Logs
                </a>
                <a href="{{ ($readOnly ?? false) ? route('admin.team.attendance-logs', array_merge(request()->query(), ['type' => 'remote'])) : route('super-admin.attendance-holidays.absents.logs', array_merge(request()->query(), ['type' => 'remote'])) }}" 
                   class="btn btn-sm px-3 text-white fw-semibold" 
                   style="border-radius: 50px; background-color: {{ $type === 'remote' ? '#f59e0b' : 'transparent' }}; color: {{ $type === 'remote' ? '#000000 !important' : '#ffffff' }}; font-size: 0.8rem; transition: all 0.2s;">
                    Remote Logs
                </a>
                <a href="{{ ($readOnly ?? false) ? route('admin.team.attendance-logs', array_merge(request()->query(), ['type' => 'leave'])) : route('super-admin.attendance-holidays.absents.logs', array_merge(request()->query(), ['type' => 'leave'])) }}" 
                   class="btn btn-sm px-3 text-white fw-semibold" 
                   style="border-radius: 50px; background-color: {{ $type === 'leave' ? '#10b981' : 'transparent' }}; font-size: 0.8rem; transition: all 0.2s;">
                    Leave Logs
                </a>
            </div>
        </div>
        <hr class="border-secondary opacity-25 mb-3">

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr class="border-bottom border-secondary border-opacity-25 text-white opacity-50 small text-uppercase">
                        <th class="ps-3">Employee</th>
                        <th>Date</th>
                        @if($type === 'leave')
                            <th>Leave Type</th>
                        @endif
                        <th>Total Days</th>
                        <th>Reason/Notes</th>
                        <th>Marked By</th>
                        @if(!($readOnly ?? false))
                        <th class="text-end pe-3">Action</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($absents as $abs)
                        <tr class="text-white" style="border-bottom: 1px solid rgba(255, 255, 255, 0.03) !important;">
                            <td class="ps-3 fw-medium">{{ $abs->user->name }}</td>
                            <td class="font-monospace text-warning">{{ $abs->date_range_display }}</td>
                            @if($type === 'leave')
                                <td>
                                    <span class="text-white opacity-75 small">{{ $abs->leaveType?->name ?? 'Casual Leave' }}</span>
                                </td>
                            @endif
                            <td>
                                <span class="row-badge" style="background: rgba(255, 255, 255, 0.1); color: rgba(255, 255, 255, 0.75); border: transparent;">
                                    {{ (float)$abs->days_count == (int)$abs->days_count ? (int)$abs->days_count : number_format($abs->days_count, 1) }} {{ Str::plural('day', $abs->days_count) }}
                                </span>
                            </td>
                            <td><span class="text-white opacity-75 small">{{ $abs->notes ?? '—' }}</span></td>
                            <td>
                                <span class="row-badge-green">{{ $abs->marker->name ?? 'System' }}</span>
                            </td>
                            @if(!($readOnly ?? false))
                            <td class="text-end pe-3">
                                <div class="d-inline-flex align-items-center gap-2">
                                    @if($type === 'absent')
                                        <!-- Blue outline circular icon convert button (hover filled) -->
                                        <button type="button" class="row-btn-outline-blue btn-sm px-2 btn-convert-absent" 
                                                data-id="{{ $abs->id }}" 
                                                data-ids="{{ implode(',', $abs->record_ids) }}"
                                                data-name="{{ $abs->user->name }}" 
                                                data-date="{{ $abs->date_range_display }}"
                                                data-custom-tooltip="Convert Absent to Leave"
                                                data-tooltip-placement="top"
                                                style="height: 28px !important; width: 28px !important; display: inline-flex; align-items: center; justify-content: center; padding: 0 !important; border-radius: 50px !important;">
                                            <i class="bi bi-arrow-left-right" style="font-size: 0.85rem;"></i>
                                        </button>
                                    @endif
                                    @if($type === 'leave')
                                        @if($abs->hasOutstandingChangeRequest())
                                            <!-- Purple circular icon button for pending change request (redirects to changes_requested leave requests) -->
                                            <a href="{{ ($readOnly ?? false) ? route('admin.team.leaves', ['status' => 'changes_requested']) : route('super-admin.leave-requests.index', ['status' => 'changes_requested']) }}" 
                                               class="row-btn-outline-purple text-decoration-none" 
                                               data-custom-tooltip="Changes Requested already"
                                               data-tooltip-placement="top"
                                               style="height: 28px !important; width: 28px !important; display: inline-flex; align-items: center; justify-content: center; padding: 0 !important; border-radius: 50px !important; background-color: rgba(168, 85, 247, 0.15) !important; color: #c084fc !important; border: 1px solid rgba(168, 85, 247, 0.4) !important;">
                                                <i class="bi bi-clock-fill" style="font-size: 0.8rem;"></i>
                                            </a>
                                        @else
                                            <!-- Blue outline circular icon edit leave button -->
                                            <button type="button" class="row-btn-outline-blue btn-sm px-2 btn-change-leave-type" 
                                                    data-id="{{ $abs->id }}" 
                                                    data-name="{{ $abs->user->name }}" 
                                                    data-date="{{ $abs->date_range_display }}"
                                                    data-start-date="{{ \Carbon\Carbon::parse($abs->start_date)->format('Y-m-d') }}"
                                                    data-end-date="{{ \Carbon\Carbon::parse($abs->end_date)->format('Y-m-d') }}"
                                                    data-reason="{{ $abs->reason }}"
                                                    data-current-type-id="{{ $abs->leave_type_id }}"
                                                    data-current-type-name="{{ $abs->leaveType->name ?? '' }}"
                                                    data-custom-tooltip="Edit Marked Leave"
                                                    data-tooltip-placement="top"
                                                    style="height: 28px !important; width: 28px !important; display: inline-flex; align-items: center; justify-content: center; padding: 0 !important; border-radius: 50px !important;">
                                                <i class="bi bi-pencil" style="font-size: 0.85rem;"></i>
                                            </button>
                                        @endif
                                    @endif
                                    
                                    <!-- Solid filled red delete button with custom modal confirmation -->
                                    @if($type === 'remote')
                                        <form action="{{ route('super-admin.attendance-holidays.remote.destroy', $abs->id) }}" method="POST" class="d-inline absent-delete-form" id="deleteForm_{{ $abs->id }}">
                                    @elseif($type === 'leave')
                                        <form action="{{ route('super-admin.attendance-holidays.leave.destroy', $abs->id) }}" method="POST" class="d-inline absent-delete-form" id="deleteForm_{{ $abs->id }}">
                                    @else
                                        <form action="{{ route('super-admin.attendance-holidays.absents.destroy', $abs->id) }}" method="POST" class="d-inline absent-delete-form" id="deleteForm_{{ $abs->id }}">
                                    @endif
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="record_ids" value="{{ implode(',', $abs->record_ids ?? [$abs->id]) }}">
                                        <button type="button" class="border-0 text-white btn-trigger-delete-absent" 
                                                data-form-id="deleteForm_{{ $abs->id }}"
                                                data-employee-name="{{ $abs->user->name }}"
                                                data-date-range="{{ $abs->date_range_display }}"
                                                style="background-color: #ef4444 !important; color: #ffffff !important; border-radius: 50px !important; height: 28px; line-height: 28px; padding: 0 16px !important; font-size: 0.8rem; font-weight: 500;">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $type === 'leave' ? (($readOnly ?? false) ? 6 : 7) : (($readOnly ?? false) ? 5 : 6) }}" class="text-center text-white opacity-50 py-5">
                                <div class="mb-2"><i class="bi bi-calendar-x fs-3 text-warning"></i></div>
                                No {{ $type === 'remote' ? 'remote logs' : ($type === 'leave' ? 'marked leaves' : 'absences') }} found matching filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot id="logs-table-footer">
                    <tr style="border-top: 2px solid rgba(255, 255, 255, 0.15); background-color: rgba(255, 255, 255, 0.02);">
                        <td class="ps-3 py-3 text-uppercase text-white fw-bold small" style="letter-spacing: 0.05em;">
                            Total {{ $type === 'remote' ? 'Remote Days' : ($type === 'leave' ? 'Leave Days' : 'Absent Days') }}
                        </td>
                        <td class="py-3 text-white opacity-40 small font-monospace">
                            {{ DateTime::createFromFormat('!m', $selectedMonth ?? (int)date('n'))->format('F') }} {{ $selectedYear ?? date('Y') }}
                        </td>
                        @if($type === 'leave')
                            <td class="py-3 text-white opacity-40 small">—</td>
                        @endif
                        <td class="py-3">
                            <span class="row-badge" style="background: rgba(255, 255, 255, 0.12); color: #ffffff; font-weight: 700; border: 1px solid rgba(255, 255, 255, 0.2); font-size: 0.85rem; padding: 3px 10px;">
                                {{ (float)($totalDaysCount ?? 0) == (int)($totalDaysCount ?? 0) ? (int)($totalDaysCount ?? 0) : number_format($totalDaysCount ?? 0, 1) }} {{ Str::plural('day', $totalDaysCount ?? 0) }}
                            </span>
                        </td>
                        <td class="py-3 text-white opacity-40 small" colspan="{{ ($readOnly ?? false) ? 2 : 3 }}">
                            <span class="opacity-50 small">({{ $totalRecordsCount ?? 0 }} total log {{ Str::plural('entry', $totalRecordsCount ?? 0) }} in {{ DateTime::createFromFormat('!m', $selectedMonth ?? (int)date('n'))->format('F') }} {{ $selectedYear ?? date('Y') }})</span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4 px-2">
            {{ $absents->appends(request()->input())->links() }}
        </div>
    </div>

    @push('modals')
    <!-- Edit Admin-Marked Leave Modal -->
    <div class="modal fade" id="changeLeaveTypeModal" tabindex="-1" aria-labelledby="changeLeaveTypeModalLabel" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark border-0 shadow" style="overflow: hidden; background-color: #212529 !important;">
                <div class="modal-header bg-dark border-bottom border-secondary border-opacity-25">
                    <h5 class="modal-title fw-bold text-white fs-6" id="changeLeaveTypeModalLabel">Edit Admin-Marked Leave</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="changeLeaveTypeForm" method="POST">
                    @csrf
                    
                    <div class="modal-body py-4">
                        <div id="pendingChangeNotice" class="mb-3 p-3 rounded d-none" style="background: rgba(168, 85, 247, 0.1); border: 1px solid rgba(168, 85, 247, 0.3);">
                            <div class="d-flex align-items-center gap-2 text-white fw-semibold small">
                                <i class="bi bi-exclamation-triangle-fill" style="color: #a855f7;"></i>
                                <span>Employee Has a Pending Change Request</span>
                            </div>
                            <small class="d-block mt-1 text-white opacity-75" style="font-size: 0.73rem;">
                                Saving changes here will directly update the leave and clear the employee's pending change request cleanly.
                            </small>
                        </div>

                        <!-- Employee display card -->
                        <div class="mb-3 d-flex align-items-center justify-content-between p-3" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.06); border-radius: 50px !important; padding: 12px 28px !important;">
                            <div>
                                <span class="text-white opacity-50 small d-block mb-0.5" style="font-size: 0.72rem;">Employee Name</span>
                                <span id="changeLeaveEmployeeName" class="text-white fw-bold small"></span>
                            </div>
                            <div class="text-end">
                                <span class="text-white opacity-50 small d-block mb-0.5" style="font-size: 0.72rem;">Current Leave Type</span>
                                <span id="changeLeaveCurrentType" class="text-info fw-bold small"></span>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label small text-white opacity-70 mb-1">Start Date</label>
                                <input type="date" name="start_date" id="changeLeaveStartDate" class="form-control border-0 text-white" required style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;">
                            </div>
                            <div class="col-6">
                                <label class="form-label small text-white opacity-70 mb-1">End Date</label>
                                <input type="date" name="end_date" id="changeLeaveEndDate" class="form-control border-0 text-white" required style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-white opacity-70 mb-1">Leave Type</label>
                            <select name="leave_type_id" id="changeLeaveTypeSelect" class="form-select border-0 text-white" required style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; height: 38px;">
                                <option value="" class="bg-dark text-white">-- Select Leave Type --</option>
                                @foreach($leaveTypes as $lt)
                                    <option value="{{ $lt->id }}" class="bg-dark text-white">{{ $lt->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-0">
                            <label class="form-label small text-white opacity-70 mb-1">Reason</label>
                            <textarea name="reason" id="changeLeaveReason" class="form-control border-0 text-white" rows="2" placeholder="Reason for leave" style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer d-flex justify-content-end gap-2 border-top border-secondary border-opacity-25">
                        <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 120px;">Cancel</button>
                        <button type="submit" class="row-btn-blue" style="width: 180px;">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Convert Absent to Leave Modal (Styled to match system modal popups exactly) -->
    <div class="modal fade" id="convertLeaveModal" tabindex="-1" aria-labelledby="convertLeaveModalLabel" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark border-0 shadow" style="overflow: hidden; background-color: #212529 !important;">
                <div class="modal-header bg-dark border-bottom border-secondary border-opacity-25">
                    <h5 class="modal-title fw-bold text-white fs-6" id="convertLeaveModalLabel">Convert Absent to Leave</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="convertLeaveForm" method="POST">
                    @csrf
                    <!-- Hidden input to pass multiple record IDs when converting a range -->
                    <input type="hidden" name="record_ids" id="convertRecordIds" value="">
                    
                    <div class="modal-body py-4">
                        <!-- Employee / date range display card styled completely oval -->
                        <div class="mb-3 d-flex align-items-center justify-content-between p-3" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.06); border-radius: 50px !important; padding: 12px 28px !important;">
                            <div>
                                <span class="text-white opacity-50 small d-block mb-0.5" style="font-size: 0.72rem;">Employee Name</span>
                                <span id="convertEmployeeName" class="text-white fw-bold small"></span>
                            </div>
                            <div class="text-end">
                                <span class="text-white opacity-50 small d-block mb-0.5" style="font-size: 0.72rem;">Absent Date / Range</span>
                                <span id="convertAbsentDate" class="text-warning font-monospace fw-semibold small"></span>
                            </div>
                        </div>
                        
                        <div class="mb-0">
                            <label class="form-label small text-white opacity-70 mb-2">Select Leave Type</label>
                            <select name="leave_type_id" class="form-select border-0 text-white" required style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; height: 38px;">
                                <option value="" class="bg-dark text-white">-- Select Leave Type --</option>
                                @foreach($leaveTypes as $lt)
                                    <option value="{{ $lt->id }}" class="bg-dark text-white">{{ $lt->name }}</option>
                                @endforeach
                            </select>
                            <small class="d-block mt-2" style="font-size: 0.75rem; color: rgba(255, 255, 255, 0.6) !important; line-height: 1.4;">
                                <i class="bi bi-info-circle me-1"></i>Converting to paid leave will verify and subtract from their leave balance. Converting to unpaid leave will deduct salary.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer d-flex justify-content-end gap-2 border-top border-secondary border-opacity-25">
                        <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 120px;">Cancel</button>
                        <button type="submit" class="row-btn-blue" style="width: 180px;">Convert to Leave</button>
                    </div>
                </form>
            </div>
        </div>
    </div>    <!-- Custom Delete Confirmation Modal (Matches system style reconfirmations instead of native alert) -->
    <div class="modal fade" id="confirmDeleteAbsentModal" tabindex="-1" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="background-color: #0c0c0c !important; border: 1px solid rgba(255, 255, 255, 0.08) !important; border-radius: 12px !important;">
                <div class="modal-body text-center p-4">
                    <div class="text-danger mb-3">
                        <i class="bi bi-exclamation-triangle-fill" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-white" id="deleteModalTitle">Remove Absent Record?</h5>
                    <p class="text-white opacity-70 small mb-4">
                        Are you sure you want to remove the <span id="deleteModalType">absent record(s)</span> for <strong id="confirmDeleteEmployeeName" class="text-white"></strong> on <strong id="confirmDeleteDateRange" class="text-warning"></strong>?
                    </p>
                    
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 120px; border-radius: 50px !important;">Cancel</button>
                        <button type="button" class="row-btn text-white border-0" id="btnConfirmDeleteAbsent" style="width: 140px; border-radius: 50px !important; background-color: #ef4444 !important; font-weight: 500;">Yes, Remove</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endpush

    @push('scripts')
    <script>
        window.selectLogsFilter = function(event, elementId, value) {
            event.preventDefault();
            $('#' + elementId).val(value);
            $('#filterForm').submit();
        };

        $(function() {
            // Convert Absent to Leave action click
            $('.btn-convert-absent').on('click', function() {
                const id = $(this).data('id');
                const ids = $(this).data('ids');
                const name = $(this).data('name');
                const date = $(this).data('date');

                $('#convertEmployeeName').text(name);
                $('#convertAbsentDate').text(date);
                $('#convertRecordIds').val(ids);
                $('#convertLeaveForm').attr('action', `/super-admin/attendance-holidays/absents/${id}/convert`);

                const convertModal = new bootstrap.Modal(document.getElementById('convertLeaveModal'));
                convertModal.show();
            });

            // Change Leave Type / Edit Marked Leave action click
            $('.btn-change-leave-type').on('click', function() {
                const id = $(this).data('id');
                const name = $(this).data('name');
                const startDate = $(this).data('start-date');
                const endDate = $(this).data('end-date');
                const reason = $(this).data('reason');
                const currentTypeId = $(this).data('current-type-id');
                const currentTypeName = $(this).data('current-type-name');
                const hasPendingChange = $(this).data('has-pending-change') === 1 || $(this).data('has-pending-change') === '1';

                $('#changeLeaveEmployeeName').text(name);
                $('#changeLeaveCurrentType').text(currentTypeName);
                $('#changeLeaveStartDate').val(startDate);
                $('#changeLeaveEndDate').val(endDate);
                $('#changeLeaveTypeSelect').val(currentTypeId);
                $('#changeLeaveReason').val(reason);
                $('#changeLeaveTypeForm').attr('action', `/super-admin/attendance-holidays/leave/${id}/update`);

                if (hasPendingChange) {
                    $('#pendingChangeNotice').removeClass('d-none');
                } else {
                    $('#pendingChangeNotice').addClass('d-none');
                }

                const changeModal = new bootstrap.Modal(document.getElementById('changeLeaveTypeModal'));
                changeModal.show();
            });

            // Delete absent record custom modal confirm trigger
            let activeFormId = null;
            $('.btn-trigger-delete-absent').on('click', function() {
                activeFormId = $(this).data('form-id');
                const empName = $(this).data('employee-name');
                const dateRange = $(this).data('date-range');
                
                const logType = "{{ $type }}";
                if (logType === 'remote') {
                    $('#deleteModalTitle').text('Remove Remote Work Log?');
                    $('#deleteModalType').text('remote work record(s)');
                } else if (logType === 'leave') {
                    $('#deleteModalTitle').text('Remove Marked Leave?');
                    $('#deleteModalType').text('marked leave record(s)');
                } else {
                    $('#deleteModalTitle').text('Remove Absent Record?');
                    $('#deleteModalType').text('absent record(s)');
                }

                $('#confirmDeleteEmployeeName').text(empName);
                $('#confirmDeleteDateRange').text(dateRange);
                
                const confirmModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmDeleteAbsentModal'));
                confirmModal.show();
            });

            // Confirm delete click
            $('#btnConfirmDeleteAbsent').on('click', function() {
                if (activeFormId) {
                    document.getElementById(activeFormId).submit();
                }
            });
        });
    </script>
    @endpush
</x-app-layout>
