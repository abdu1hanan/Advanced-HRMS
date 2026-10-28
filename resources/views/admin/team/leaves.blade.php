<x-app-layout>
    <x-slot name="header">Team Leave Requests</x-slot>
    
    <style>
        .leave-filters-card {
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
        .leave-filters-card .collapse.show {
            overflow: visible !important;
        }
        .leave-filters-card .collapsing {
            overflow: hidden !important;
        }
    </style>

    <div class="spotify-card leave-filters-card mb-4" style="position: relative; z-index: 1050 !important; overflow: visible !important;">
        <div class="card-header-area px-4 pt-4 d-none">
            <h6 class="text-uppercase text-white fw-semibold small mb-2 opacity-75" style="letter-spacing: 0.05em;">Filters</h6>
            <div class="grid-separator"></div>
        </div>
        <div class="p-4 pt-4">
            <form method="GET" id="filterForm">
                @php
                    $hasAdvancedFilters = request()->filled('start_date') || request()->filled('end_date') || request()->filled('leave_type_id');
                    
                    $statusLabels = [
                        'all' => 'All Active Requests',
                        'pending' => 'Pending Manager Approval',
                        'approved_by_manager' => 'Pending CEO Approval',
                        'rejected_by_manager' => 'Rejected by Line Manager',
                        'changes_requested' => 'Changes Requested',
                        'pending_cancellation' => 'Pending Cancellation',
                    ];
                    $currentStatus = request('status', 'all');
                    $currentStatusLabel = $statusLabels[$currentStatus] ?? 'All Active Requests';

                    $currentDept = $departments->firstWhere('id', request('department_id'));
                    $currentDeptLabel = $currentDept ? $currentDept->name : 'All departments';

                    $currentType = $leaveTypes->firstWhere('id', request('leave_type_id'));
                    $currentTypeLabel = $currentType ? $currentType->name : 'All types';
                @endphp

                <input type="hidden" name="status" id="filter_status" value="{{ $currentStatus }}">
                <input type="hidden" name="department_id" id="filter_department_id" value="{{ request('department_id') }}">
                <input type="hidden" name="leave_type_id" id="filter_leave_type_id" value="{{ request('leave_type_id') }}">
                
                <!-- Primary Filter Bar (Clean Single Line on Desktop) -->
                <div class="row g-3 align-items-end">
                    <!-- Status Filter -->
                    <div class="col-xl-3 col-md-3 col-sm-6">
                        <label class="form-label text-white opacity-50 small mb-1">Status Filter</label>
                        <div class="dropdown department-dropdown w-100">
                            <button class="form-select border-0 shadow-sm text-start text-white w-100" 
                                    type="button" 
                                    id="statusDropdown" 
                                    data-bs-toggle="dropdown" 
                                    aria-expanded="false"
                                    style="border: 1px solid rgba(255, 255, 255, 0.15) !important; background-color: rgba(255, 255, 255, 0.03); border-radius: 4px; height: 37px; font-size: 0.85rem; color: #ffffff !important;">
                                <span class="text-truncate d-inline-block w-100" style="max-width: calc(100% - 15px);">{{ $currentStatusLabel }}</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-dark p-0 w-100" aria-labelledby="statusDropdown">
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

                    <!-- Department Filter -->
                    <div class="col-xl-2 col-md-3 col-sm-6">
                        <label class="form-label text-white opacity-50 small mb-1">Department Filter</label>
                        <div class="dropdown department-dropdown w-100">
                            <button class="form-select border-0 shadow-sm text-start text-white w-100" 
                                    type="button" 
                                    id="deptDropdown" 
                                    data-bs-toggle="dropdown" 
                                    aria-expanded="false"
                                    style="border: 1px solid rgba(255, 255, 255, 0.15) !important; background-color: rgba(255, 255, 255, 0.03); border-radius: 4px; height: 37px; font-size: 0.85rem; color: #ffffff !important;">
                                <span class="text-truncate d-inline-block w-100" style="max-width: calc(100% - 15px);">{{ $currentDeptLabel }}</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-dark p-0 w-100" aria-labelledby="deptDropdown">
                                <li>
                                    <a class="dropdown-item py-2 text-white @if(!request('department_id')) active @endif" 
                                       href="#" 
                                       onclick="selectFilterOption(event, 'filter_department_id', '')">All departments</a>
                                </li>
                                @foreach($departments as $dept)
                                    <li>
                                        <a class="dropdown-item py-2 text-white @if(request('department_id') == $dept->id) active @endif" 
                                           href="#" 
                                           onclick="selectFilterOption(event, 'filter_department_id', '{{ $dept->id }}')">{{ $dept->name }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    <!-- Employee Filter -->
                    <div class="col-xl-3 col-md-3 col-sm-6">
                        <label class="form-label text-white opacity-50 small mb-1">Employee Filter</label>
                        <select name="user_id" class="form-select border-0 shadow-sm" onchange="document.getElementById('filterForm').submit()"
                                style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important; height: 37px;">
                            <option value="" class="bg-dark text-white">All employees</option>
                            @foreach($allUsers as $u)
                                <option value="{{ $u->id }}" @selected(request('user_id')==$u->id) class="bg-dark text-white">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- More Filters Toggle Button -->
                    <div class="col-xl-2 col-md-3 col-sm-6 d-flex align-items-end">
                        <div class="w-100">
                            <label class="form-label d-block small">&nbsp;</label>
                            <button type="button" class="row-btn w-100 text-decoration-none d-inline-flex align-items-center justify-content-center gap-2" 
                                    data-bs-toggle="collapse" data-bs-target="#advancedFilters" aria-expanded="{{ $hasAdvancedFilters ? 'true' : 'false' }}" aria-controls="advancedFilters"
                                    style="height: 37px !important;">
                                <i class="bi bi-calendar-range"></i>
                                <span>More Filters</span>
                            </button>
                        </div>
                    </div>

                    <!-- Clear Filters Button -->
                    <div class="col-xl-2 col-md-12 col-sm-12 d-flex align-items-end">
                        <div class="w-100">
                            <label class="form-label d-block small">&nbsp;</label>
                            <a href="{{ route('admin.team.leaves') }}" 
                               class="row-btn w-100 text-decoration-none d-inline-flex align-items-center justify-content-center gap-2" 
                               style="height: 37px !important;">
                                <i class="bi bi-x-circle"></i>
                                <span>Clear Filters</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Collapsible Advanced Filters (Leave Type & Date Range) -->
                <div class="collapse @if($hasAdvancedFilters) show @endif" id="advancedFilters">
                    <div class="row g-3 align-items-end pt-3">
                        <!-- Leave Type Filter -->
                        <div class="col-md-4 col-sm-12">
                            <label class="form-label text-white opacity-50 small mb-1">Leave Type Filter</label>
                            <div class="dropdown department-dropdown w-100">
                                <button class="form-select border-0 shadow-sm text-start text-white w-100" 
                                        type="button" 
                                        id="typeDropdown" 
                                        data-bs-toggle="dropdown" 
                                        aria-expanded="false"
                                        style="border: 1px solid rgba(255, 255, 255, 0.15) !important; background-color: rgba(255, 255, 255, 0.03); border-radius: 4px; height: 37px; font-size: 0.85rem; color: #ffffff !important;">
                                    <span class="text-truncate d-inline-block w-100" style="max-width: calc(100% - 15px);">{{ $currentTypeLabel }}</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-dark p-0 w-100" aria-labelledby="typeDropdown">
                                    <li>
                                        <a class="dropdown-item py-2 text-white @if(!request('leave_type_id')) active @endif" 
                                           href="#" 
                                           onclick="selectFilterOption(event, 'filter_leave_type_id', '')">All types</a>
                                    </li>
                                    @foreach($leaveTypes as $type)
                                        <li>
                                            <a class="dropdown-item py-2 text-white @if(request('leave_type_id') == $type->id) active @endif" 
                                               href="#" 
                                               onclick="selectFilterOption(event, 'filter_leave_type_id', '{{ $type->id }}')">{{ $type->name }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>

                        <!-- Start Date Filter -->
                        <div class="col-md-4 col-sm-6">
                            <label class="form-label text-white opacity-50 small mb-1">Start Date</label>
                            <input type="date" name="start_date" value="{{ request('start_date') }}" class="form-control" onchange="document.getElementById('filterForm').submit()" 
                                   style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important; height: 37px; color-scheme: dark;">
                        </div>

                        <!-- End Date Filter -->
                        <div class="col-md-4 col-sm-6">
                            <label class="form-label text-white opacity-50 small mb-1">End Date</label>
                            <input type="date" name="end_date" value="{{ request('end_date') }}" class="form-control" onchange="document.getElementById('filterForm').submit()" 
                                   style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important; height: 37px; color-scheme: dark;">
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <div class="spotify-card leave-table-card mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4 text-white opacity-50">Employee</th>
                        <th class="text-white opacity-50">Department</th>
                        <th class="text-white opacity-50">Type</th>
                        <th class="text-white opacity-50">Dates</th>
                        <th class="text-white opacity-50">Entry On</th>
                        <th class="text-white opacity-50">Days</th>
                        <th class="text-white opacity-50">Status</th>
                        <th class="text-end pe-4 text-white opacity-50">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $request)
                        <tr class="cursor-pointer hover-highlight">
                            <td class="ps-4 fw-semibold text-white">{{ $request->user->name }}</td>
                            <td><span class="text-white opacity-75">{{ $request->user->department?->name ?? 'N/A' }}</span></td>
                            <td><span class="text-white opacity-75">{{ $request->leaveType->name }}</span></td>
                            <td><span class="text-white opacity-75">{{ $request->start_date->format('M d, Y') }} - {{ $request->end_date->format('M d, Y') }}</span></td>
                            <td><span class="text-white opacity-75">{{ $request->created_at->format('M d, Y') }}</span></td>
                            <td class="text-white">{{ $request->days_count }}</td>
                            <td>
                                @php
                                    $cp = (int)$request->approval_chain_progress;
                                    $ct = (int)$request->approval_chain_target;
                                    $rejLevel = $request->rejectionLevel();
                                @endphp
                                @if($request->hasPendingCancellation())
                                    <span class="row-badge-gray"><i class="bi bi-clock-history me-1"></i> Pending Cancellation</span>
                                @elseif($request->status === 'approved_by_manager')
                                    <span class="row-badge-blue">
                                        <i class="bi bi-clock-history me-1"></i>
                                        @if($cp > 0 && $ct > 0)
                                            Level {{ $cp }}/{{ $ct }} Approved &mdash; Pending CEO
                                        @else
                                            Approved &mdash; Pending CEO
                                        @endif
                                    </span>
                                @elseif($request->status === 'approved' && $request->is_admin_marked && $request->hasOutstandingChangeRequest() && !$request->hasPendingCancellation())
                                    <span class="row-badge-purple">
                                        <i class="bi bi-clock-history me-1"></i> Pending Changes
                                    </span>
                                @elseif($request->status === 'approved')
                                    <span class="row-badge-green">
                                        <i class="bi bi-check-circle-fill me-1"></i> Fully Approved
                                    </span>
                                @elseif($request->status === 'rejected')
                                    <span class="row-badge-red">
                                        <i class="bi bi-x-circle-fill me-1"></i> Rejected by CEO
                                    </span>
                                @elseif($request->status === 'rejected_by_manager')
                                    <span class="row-badge-red">
                                        <i class="bi bi-x-circle-fill me-1"></i>
                                        @if($rejLevel > 0 && $ct > 0)
                                            Rejected (Level {{ $rejLevel }}/{{ $ct }})
                                        @else
                                            Rejected by Manager
                                        @endif
                                    </span>
                                @elseif($request->status === 'cancelled')
                                    <span class="row-badge-gray"><i class="bi bi-x-circle me-1"></i> Cancelled</span>
                                @else
                                    @if($cp > 0 && $ct > 0)
                                        <span class="row-badge-blue">
                                            <i class="bi bi-clock-history me-1"></i> Level {{ $cp }}/{{ $ct }} Approved &mdash; Pending Manager
                                        </span>
                                    @else
                                        <span class="row-badge-warning">
                                            <i class="bi bi-clock-fill me-1"></i>
                                            @if($ct > 0)
                                                Pending Level 1/{{ $ct }} Approval
                                            @else
                                                Pending Approval
                                            @endif
                                        </span>
                                    @endif
                                @endif

                                @if($request->hasOutstandingChangeRequest() && !$request->hasPendingCancellation() && !($request->status === 'approved' && $request->is_admin_marked))
                                    <div class="mt-1">
                                        <span class="row-badge-purple">
                                            <i class="bi bi-clock-history me-1"></i> {{ $request->changeRequestBadgeText() }}
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex align-items-center gap-2">
                                    @if($request->comments->count() > 0)
                                        <span class="row-badge-blue" title="Has conversation">
                                            <i class="bi bi-chat-text-fill"></i> {{ $request->comments->count() }}
                                        </span>
                                    @endif
                                    <button type="button" class="row-btn-outline-blue btn-sm px-3" onclick="openRequestModal({{ $request->id }})">View / Action</button>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Hidden Data for Modal -->
                        <div id="request-data-{{ $request->id }}" class="d-none">
                            <span class="d-name">{{ $request->user->name }}</span>
                            <span class="d-dept">{{ $request->user->department?->name ?? 'N/A' }}</span>
                            <span class="d-type">{{ $request->leaveType->name }}</span>
                            <span class="d-dates">{{ $request->start_date->format('M d, Y') }} – {{ $request->end_date->format('M d, Y') }} ({{ $request->formatted_days_count }})</span>
                            <span class="d-cur-start d-none">{{ $request->start_date->format('Y-m-d') }}</span>
                            <span class="d-cur-end d-none">{{ $request->end_date->format('Y-m-d') }}</span>
                            <span class="d-reason">{{ $request->reason }}</span>
                            <span class="d-status">{{ $request->status }}</span>
                            <span class="d-super-admin-resolved">{{ ($request->reviewer && $request->reviewer->isSuperAdmin()) ? 'true' : 'false' }}</span>
                            @php
                                $canApproveUser  = app(\App\Services\LeaveApprovalChainService::class)->canApprove($request, auth()->user());
                                $canRejectUser   = app(\App\Services\LeaveApprovalChainService::class)->canReject($request, auth()->user());
                                $hasApprovedUser = $request->hasManagerApproved(auth()->id());
                            @endphp
                            <span class="d-can-approve">{{ $canApproveUser ? '1' : '0' }}</span>
                            <span class="d-can-reject">{{ $canRejectUser ? '1' : '0' }}</span>
                            <span class="d-has-approved">{{ $hasApprovedUser ? '1' : '0' }}</span>
                            <span class="d-chain-target">{{ (int)$request->approval_chain_target }}</span>
                            <span class="d-chain-progress">{{ (int)$request->approval_chain_progress }}</span>
                            <span class="d-rejected-by-name">{{ $request->reviewer?->name ?? 'Line Manager' }}</span>
                            <span class="d-rejected-by-level">{{ $request->rejectionLevel() }}</span>
                            @php
                                $orig = $request->change_request['original'] ?? null;
                                $cancelReason = $request->change_request['cancel_reason'] ?? null;
                                $isCancelReq = !empty($request->change_request['request_cancellation']);
                            @endphp
                            @if($orig)
                                @php
                                    $origDays = (float)($orig['days_count'] ?? 0);
                                    $origDaysFormatted = ($origDays == (int)$origDays) ? (int)$origDays : $origDays;
                                    $origUnit = ($origDaysFormatted === 1) ? 'day' : 'days';
                                @endphp
                                <span class="d-has-orig-snapshot">1</span>
                                <span class="d-orig-type">{{ $orig['leave_type_name'] ?? 'N/A' }}</span>
                                <span class="d-orig-dates">{{ \Carbon\Carbon::parse($orig['start_date'])->format('M d, Y') }} – {{ \Carbon\Carbon::parse($orig['end_date'])->format('M d, Y') }} ({{ $origDaysFormatted }} {{ $origUnit }})</span>
                                <span class="d-orig-reason">{{ $orig['reason'] ?? 'N/A' }}</span>
                                <span class="d-orig-start d-none">{{ $orig['start_date'] ?? '' }}</span>
                                <span class="d-orig-end d-none">{{ $orig['end_date'] ?? '' }}</span>
                            @else
                                <span class="d-has-orig-snapshot">0</span>
                            @endif
                            <span class="d-cancel-reason">{{ $cancelReason ?? '' }}</span>
                            <span class="d-is-cancel-req">{{ $isCancelReq ? '1' : '0' }}</span>
                            <span class="d-change-status">{{ $request->change_request_status }}</span>
                            <span class="d-cancel-banner-text">{{ $request->cancellationBannerText() }}</span>
                            <span class="d-change-badge-text">{{ $request->changeRequestBadgeText() }}</span>
                            <span class="d-is-admin-marked">{{ $request->is_admin_marked ? '1' : '0' }}</span>
                            {{-- Approval Chain Timeline Data --}}
                            @php
                                $chainEntries  = $request->approvalChain;
                                $chainTarget   = (int)$request->approval_chain_target;
                                $chainProgress = (int)$request->approval_chain_progress;
                                $maxLevel      = max($chainTarget, $chainEntries->max('level') ?? 0);
                                $saReviewer    = ($request->reviewer && $request->reviewer->isSuperAdmin()) ? $request->reviewer : null;
                                $saApprovedBy  = !empty($request->change_request['approved_by']) ? \App\Models\User::find($request->change_request['approved_by']) : null;
                                $hasSAActed    = ($saReviewer !== null || ($saApprovedBy && $saApprovedBy->isSuperAdmin()));
                                $hasPendingCancel = $request->hasPendingCancellation();
                                $saAction      = match(true) {
                                    $hasPendingCancel => '',
                                    $request->status === 'approved' => 'approved',
                                    $request->status === 'rejected' => 'rejected',
                                    $request->status === 'cancelled' && $hasSAActed => 'approved',
                                    default => ''
                                };
                                $bossChain     = app(\App\Services\HierarchyService::class)->getBossChain($request->user);
                                $hasRejection  = $chainEntries->contains('action', 'rejected') || $request->status === 'rejected_by_manager';
                                $rejectedLevel = $request->rejectionLevel();
                                $isSAResolved  = in_array($request->status, ['approved', 'rejected']) || ($request->status === 'cancelled' && $hasSAActed) || $saReviewer !== null;
                            @endphp
                            <div class="d-chain-data"
                                 data-chain-target="{{ $chainTarget }}"
                                 data-chain-max="{{ $maxLevel }}"
                                 data-sa-action="{{ $saAction }}"
                                 data-sa-name="{{ $saReviewer?->name ?? ($saApprovedBy?->name ?? '') }}"
                                 data-sa-date="{{ (($saReviewer || $saApprovedBy) && !$hasPendingCancel) ? ($request->reviewed_at?->format('M d, Y') ?? '') : '' }}"
                                 data-has-rejection="{{ $hasRejection ? '1' : '0' }}"
                                 data-rejected-level="{{ $rejectedLevel }}"
                                 data-sa-resolved="{{ $isSAResolved ? '1' : '0' }}"
                                 data-status="{{ $request->status }}"
                                 data-is-admin-marked="{{ $request->is_admin_marked ? '1' : '0' }}"
                            >
                                @for($lvl = 1; $lvl <= max($maxLevel, 1); $lvl++)
                                    @php
                                        $entry = $chainEntries->firstWhere('level', $lvl);
                                        $boss  = $bossChain[$lvl - 1] ?? null;
                                        $isMissing = ($entry === null && $boss === null);
                                        $entryAct  = $entry?->action ?? '';
                                        $isSkipped = empty($entryAct) && (($hasRejection && $lvl > $rejectedLevel) || $isSAResolved || $request->status === 'cancelled');
                                    @endphp
                                    <span class="chain-step"
                                          data-level="{{ $lvl }}"
                                          data-action="{{ $entryAct }}"
                                          data-name="{{ $entry?->approver?->name ?? ($boss?->name ?? '') }}"
                                          data-designation="{{ $entry?->approver?->designation?->name ?? ($boss?->designation?->name ?? 'Employee') }}"
                                          data-date="{{ $entry?->acted_at?->format('M d, Y') ?? '' }}"
                                          data-is-missing="{{ $isMissing ? '1' : '0' }}"
                                          data-is-skipped="{{ $isSkipped ? '1' : '0' }}"
                                    ></span>
                                @endfor
                            </div>
                            <div class="d-comments">
                                @forelse($request->comments->sortBy('created_at') as $comment)
                                    @php
                                        $cLower = strtolower($comment->comment);
                                        $isSystem = str_contains($cLower, 'reset') || str_contains($cLower, 'requested') || str_contains($cLower, 'updated') || str_starts_with($comment->comment, '[System]');
                                        $isEmp = $comment->user_id === $request->user_id;
                                        $isSA = $comment->user?->isSuperAdmin() ?? false;
                                        $isLM = $comment->user?->hasSubordinates() ?? false;
                                        
                                        $bgColor = 'bg-secondary bg-opacity-10 text-white border border-secondary border-opacity-25';
                                        $badgeText = '';
                                        $badgeClass = '';
                                        if ($isSA) {
                                            $bgColor = 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25';
                                            $badgeText = 'Super Admin';
                                            $badgeClass = 'row-badge-red';
                                        } elseif ($isLM) {
                                            $bgColor = 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25';
                                            $badgeText = 'Line Manager';
                                            $badgeClass = 'row-badge-blue';
                                        } elseif ($isEmp) {
                                            $bgColor = 'bg-secondary bg-opacity-10 text-white border border-secondary border-opacity-25';
                                            $badgeText = 'Employee';
                                            $badgeClass = 'row-badge';
                                        }
                                    @endphp
                                    @if($isSystem)
                                        <div class="my-3 text-center px-2 d-flex align-items-center justify-content-center gap-2">
                                            <div class="flex-grow-1 border-bottom border-secondary border-opacity-25" style="border-style: dashed !important;"></div>
                                            <div class="text-white opacity-40 small fst-italic px-1" style="font-size: 0.73rem; letter-spacing: 0.2px;">
                                                --- <span>{{ $comment->comment }}</span> <span class="ms-1 opacity-75" style="font-size: 0.68rem;">• {{ $comment->formatted_created_at }}</span> ---
                                            </div>
                                            <div class="flex-grow-1 border-bottom border-secondary border-opacity-25" style="border-style: dashed !important;"></div>
                                        </div>
                                    @else
                                        <div class="mb-3 p-3 rounded {{ $bgColor }}">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <div>
                                                    <strong class="small text-white">{{ $comment->user->name ?? 'System' }}</strong>
                                                    @if($isSA || $isLM)
                                                        @if($comment->is_hidden_from_employee)
                                                            <i class="bi bi-eye-slash-fill text-warning ms-1" title="Hidden from employee" style="font-size: 0.85rem;"></i>
                                                        @else
                                                            <i class="bi bi-eye-fill text-success ms-1" title="Visible to employee" style="font-size: 0.85rem;"></i>
                                                        @endif
                                                    @endif
                                                </div>
                                                <small class="text-white opacity-50" style="font-size: 0.75rem;">{{ $comment->formatted_created_at }}</small>
                                            </div>
                                            <p class="mb-0 small mt-1 text-white opacity-75">{{ $comment->comment }}</p>
                                        </div>
                                    @endif
                                @empty
                                    <div class="d-flex flex-column justify-content-center align-items-center h-100 text-center py-4 my-auto">
                                        <i class="bi bi-chat-left-dots text-white opacity-25 fs-3 mb-2"></i>
                                        <p class="text-white opacity-50 small mb-0 fst-italic">No comments yet. Start a conversation!</p>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-white opacity-50 small">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                No pending leave requests.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($requests->hasPages())
            <div class="px-4 py-3 border-top border-secondary border-opacity-25">{{ $requests->links() }}</div>
        @endif
    </div>

    @push('modals')
    <!-- View Info / Action Modal -->
    <div class="modal fade" id="requestModal" tabindex="-1" style="z-index: 1060;">
        <div class="modal-dialog modal-lg modal-dialog-centered" id="modalDialog" style="transition: max-width 0.3s ease;">
            <div class="modal-content bg-dark border-0 shadow" style="overflow: hidden; background-color: #212529 !important;">
                <div class="modal-header border-bottom border-secondary border-opacity-25 text-white px-4 py-3 bg-dark">
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="requestModalLabel">Leave Request Overview</h5>
                        <div id="m_change_badge" class="small fst-italic text-decoration-underline d-none mt-0.5" style="color: #a855f7 !important; font-size: 0.75rem;">changes requested</div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="d-flex flex-row flex-nowrap align-items-stretch" style="min-height: 560px;">
                        <!-- Left Side: Request Info Details (Fixed width ~320px) -->
                        <div class="p-4 bg-dark border-end border-secondary border-opacity-25 d-flex flex-column justify-content-between overflow-auto custom-scrollbar" style="width: 320px; flex-shrink: 0; max-height: 580px;">
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h6 class="text-uppercase text-white opacity-50 fw-bold small mb-0"><i class="bi bi-info-circle me-1"></i> Request Info</h6>
                                    <button type="button" class="btn btn-xs rounded-circle d-none align-items-center justify-content-center p-0" id="btn_toggle_original" onclick="toggleOriginalRequestedChanges()" data-custom-tooltip="View Original" data-tooltip-placement="top" style="width: 28px; height: 28px; border: 1px solid #f59e0b; color: #f59e0b; background: transparent; transition: all 0.2s ease;">
                                        <i class="bi bi-arrow-left-right" style="font-size: 0.75rem;"></i>
                                    </button>
                                </div>
                                <div class="mb-3">
                                    <label class="small text-white opacity-50 mb-0">Employee</label>
                                    <div class="fw-medium text-white">
                                        <span id="m_name"></span>
                                        <span class="small text-white opacity-50 fw-normal ms-1 d-none" id="m_dept_wrapper">(<span id="m_dept"></span>)</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="small text-white opacity-50 mb-0">Leave Type</label>
                                    <div class="fw-medium text-primary" id="m_type"></div>
                                </div>
                                <div class="mb-3">
                                    <label class="small text-white opacity-50 mb-0">Duration</label>
                                    <div class="fw-medium text-white" id="m_dates"></div>
                                </div>
                                <div class="mb-3">
                                    <label class="small text-white opacity-50 mb-0">Reason Provided</label>
                                    <div class="bg-dark p-3 rounded shadow-sm small mt-1 border border-secondary text-white" id="m_reason" style="min-height: 80px; background-color: rgba(255,255,255,0.02); word-break: break-word;"></div>
                                </div>
                                <div id="m_cancel_reason_container" class="mb-3 d-none">
                                    <label class="small text-danger mb-0 fw-semibold">Reason for Cancellation</label>
                                    <div class="small text-white mt-1" id="m_cancel_reason" style="word-break: break-word;"></div>
                                </div>
                            </div>

                            <div class="mt-4 pt-3 border-top border-secondary border-opacity-25">
                                <button type="button" class="btn btn-outline-secondary text-white btn-sm w-100 d-flex align-items-center justify-content-between px-3 py-2 opacity-75 hover-opacity-100" id="btn-toggle-chain" onclick="toggleApprovalChainPanel()" style="font-size: 0.8rem; border-radius: 6px;">
                                    <span><i class="bi bi-diagram-3 me-2 text-info"></i> Approval Chain Timeline</span>
                                    <i class="bi bi-chevron-right fs-6" id="chain-chevron"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Middle Column: Comments & Actions (Flex Grow) -->
                        <div class="p-4 d-flex flex-column flex-grow-1 bg-dark overflow-auto custom-scrollbar" style="max-height: 580px; min-width: 360px;">
                            <h6 class="text-uppercase text-white opacity-50 fw-bold small mb-3"><i class="bi bi-chat-dots me-1"></i> Conversation &amp; Actions</h6>
                            
                            <!-- Comments Area -->
                            <div class="flex-grow-1 overflow-auto mb-3 custom-scrollbar chat-history-outline d-flex flex-column" id="m_comments" style="max-height: 290px;">
                                <!-- Comments appended here -->
                            </div>
                            
                            <!-- Action Form -->
                            <form method="POST" id="actionForm" class="mt-auto">
                                @csrf
                                <div id="modal-alert-container"></div>
                                
                                <div id="m_cancelled_alert" class="alert bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 py-2 px-3 small mb-3 d-none">
                                    <i class="bi bi-x-circle me-1"></i> <span id="m_cancelled_alert_text"></span>
                                </div>
                                <div id="m_super_admin_cancel_alert" class="alert bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 py-2 px-3 small mb-3 d-none">
                                    <i class="bi bi-clock-history me-1"></i> <strong>Cancellation Requested:</strong> Awaiting CEO / Super Admin review.
                                </div>
                                <div id="m_super_admin_alert" class="alert bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 py-2 px-3 small mb-3 d-none">
                                    <i class="bi bi-shield-check me-1"></i> This request has been <strong>resolved by Super Admin</strong>. You can only add comments.
                                </div>
                                <div id="m_manager_approved_alert" class="alert bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 py-2 px-3 small mb-3 d-none">
                                    <i class="bi bi-check-circle me-1"></i> You have already approved this leave request. You can add comments while it awaits higher approval.
                                </div>
                                <div id="m_chain_completed_alert" class="alert bg-info bg-opacity-10 text-info border border-info border-opacity-25 py-2 px-3 small mb-3 d-none">
                                    <i class="bi bi-info-circle me-1"></i> <span id="m_chain_completed_text">All required line manager approvals are complete. Awaiting CEO / Super Admin final approval.</span>
                                </div>
                                <div id="m_chain_waiting_lower_alert" class="alert bg-secondary bg-opacity-10 text-white opacity-75 border border-secondary border-opacity-25 py-2 px-3 small mb-3 d-none">
                                    <i class="bi bi-hourglass-split me-1"></i> This request is currently awaiting review by lower-level management in the approval chain.
                                </div>
                                <div id="m_manager_rejected_alert" class="alert bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 py-2 px-3 small mb-3 d-none">
                                    <i class="bi bi-x-circle me-1"></i> <span id="m_manager_rejected_text">This leave request was rejected. The approval chain has stopped.</span>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input custom-switch-dark" type="checkbox" name="is_hidden_from_employee" id="is_hidden_from_employee" value="1">
                                        <label class="form-check-label text-white opacity-75 small cursor-pointer" for="is_hidden_from_employee">Hide from employee</label>
                                    </div>
                                    <textarea name="comment" class="form-control text-white" rows="2" placeholder="Type your message or reasoning..."
                                              style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important;"></textarea>
                                </div>
 
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <button type="button" class="row-btn" onclick="submitAction('comment')">Just Comment</button>
                                    </div>
                                    <div id="m_action_buttons" class="d-flex gap-2">
                                        <button type="button" class="row-btn-outline-red" id="btn-reject" onclick="submitAction('reject')"><i class="bi bi-x-circle me-1"></i> Reject Leave</button>
                                        <button type="button" class="row-btn-outline-green" id="btn-approve" onclick="submitAction('approve')"><i class="bi bi-check-circle me-1"></i> Approve Leave</button>
                                    </div>
                                    <div id="m_change_buttons" class="d-flex gap-2 d-none">
                                        <button type="button" class="row-btn-outline-red" id="btn-change-reject" onclick="submitAction('reject-change')"><i class="bi bi-x-circle me-1"></i> Reject Change</button>
                                        <button type="button" class="row-btn-outline-green" id="btn-change-approve" onclick="submitAction('approve-change')"><i class="bi bi-check-circle me-1"></i> Approve Change</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Right Collapsible Panel: Approval Chain -->
                        <div id="m_chain_panel" class="d-none border-start border-secondary border-opacity-25 p-4 bg-dark d-flex flex-column" style="width: 330px; flex-shrink: 0; max-height: 580px; background-color: #1a1e24 !important;">
                            <h6 class="text-uppercase text-white opacity-50 fw-bold small mb-3"><i class="bi bi-diagram-3 me-1"></i> Approval Chain</h6>
                            <div class="flex-grow-1 overflow-auto custom-scrollbar p-3 rounded" id="m_chain_body" style="position: relative; background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.1);">
                                <!-- Vertical Dotted Timeline Rendered via JS -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Beautiful Custom Confirm Modal -->
    <div class="modal fade" id="confirmActionModal" tabindex="-1" data-bs-backdrop="false" style="z-index: 1070; background: rgba(0, 0, 0, 0.55); backdrop-filter: blur(2px);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="background-color: #0c0c0c !important; border: 1px solid rgba(255, 255, 255, 0.08) !important;">
                <div class="modal-body text-center p-4">
                    <div class="text-warning mb-3">
                        <i class="bi bi-exclamation-circle-fill" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-white" id="confirmTitle">Are you sure?</h5>
                    <p class="text-white opacity-50 small mb-4" id="confirmText">Do you really want to proceed with this action?</p>
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 145px;">Cancel</button>
                        <button type="button" class="row-btn-blue" id="btnConfirmAction" style="width: 145px;">Yes, Proceed</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endpush
 
    @push('scripts')
        <script>
            let currentRequestId = null;
            let pendingActionType = null;
            const actionForm = document.getElementById('actionForm');
 
            let isShowingOriginal = false;
            let currentReqDetails = {};

            window.toggleOriginalRequestedChanges = function() {
                isShowingOriginal = !isShowingOriginal;
                const btnToggle = document.getElementById('btn_toggle_original');
                const typeEl = document.getElementById('m_type');
                const datesEl = document.getElementById('m_dates');
                const reasonEl = document.getElementById('m_reason');

                const isTypeDiff = (currentReqDetails.curType || '').trim() !== (currentReqDetails.origType || '').trim();
                const isDatesDiff = (currentReqDetails.curStart && currentReqDetails.origStart)
                    ? (currentReqDetails.curStart !== currentReqDetails.origStart || currentReqDetails.curEnd !== currentReqDetails.origEnd)
                    : (currentReqDetails.curDates || '').replace(/\s+/g, ' ').trim() !== (currentReqDetails.origDates || '').replace(/\s+/g, ' ').trim();
                const isReasonDiff = (currentReqDetails.curReason || '').trim() !== (currentReqDetails.origReason || '').trim();

                if (btnToggle) {
                    btnToggle.setAttribute('data-custom-tooltip', isShowingOriginal ? 'View Changes Requested' : 'View Original');
                }

                if (isShowingOriginal) {
                    typeEl.textContent = currentReqDetails.origType;
                    datesEl.textContent = currentReqDetails.origDates;
                    reasonEl.textContent = currentReqDetails.origReason;
                    if (btnToggle) {
                        btnToggle.style.color = '#ffffff';
                        btnToggle.style.borderColor = '#ffffff';
                    }
                    typeEl.className = 'fw-medium text-white opacity-75';
                    datesEl.className = 'fw-medium text-white';
                    if (reasonEl) reasonEl.className = 'bg-dark p-3 rounded shadow-sm small mt-1 border border-secondary text-white';
                } else {
                    typeEl.textContent = currentReqDetails.curType;
                    datesEl.textContent = currentReqDetails.curDates;
                    reasonEl.textContent = currentReqDetails.curReason;
                    if (btnToggle) {
                        btnToggle.style.color = '#f59e0b';
                        btnToggle.style.borderColor = '#f59e0b';
                    }
                    typeEl.className = isTypeDiff ? 'fw-medium text-warning' : 'fw-medium text-primary';
                    datesEl.className = isDatesDiff ? 'fw-medium text-warning' : 'fw-medium text-white';
                    if (reasonEl) reasonEl.className = isReasonDiff 
                        ? 'bg-dark p-3 rounded shadow-sm small mt-1 border border-warning text-warning'
                        : 'bg-dark p-3 rounded shadow-sm small mt-1 border border-secondary text-white';
                }
            };

            window.openRequestModal = function(id) {
                currentRequestId = id;
                const dataDiv = document.getElementById('request-data-' + id);
                
                // Populate Details
                const dept = (dataDiv.querySelector('.d-dept').textContent || '').trim();
                document.getElementById('m_name').textContent = dataDiv.querySelector('.d-name').textContent;
                const deptEl = document.getElementById('m_dept');
                const deptWrapper = document.getElementById('m_dept_wrapper');
                if (dept && dept !== 'N/A') {
                    deptEl.textContent = dept;
                    if (deptWrapper) deptWrapper.classList.remove('d-none');
                } else {
                    deptEl.textContent = '';
                    if (deptWrapper) deptWrapper.classList.add('d-none');
                }
                document.getElementById('m_type').textContent = dataDiv.querySelector('.d-type').textContent;
                document.getElementById('m_dates').textContent = dataDiv.querySelector('.d-dates').textContent;
                document.getElementById('m_reason').textContent = dataDiv.querySelector('.d-reason').textContent;
                
                // Populate Comments
                const commentsContainer = document.getElementById('m_comments');
                commentsContainer.innerHTML = dataDiv.querySelector('.d-comments').innerHTML;
                
                // Scroll to bottom of comments
                setTimeout(() => {
                    commentsContainer.scrollTop = commentsContainer.scrollHeight;
                }, 100);

                // Handle In-Place Original vs Requested Changes Toggle
                isShowingOriginal = false;
                const hasOrig = dataDiv.querySelector('.d-has-orig-snapshot')?.textContent.trim() === '1';
                const hasOutstandingChange = dataDiv.querySelector('.d-has-change')?.textContent.trim() === '1';
                const btnToggleOriginal = document.getElementById('btn_toggle_original');
                const reasonEl = document.getElementById('m_reason');
                if (btnToggleOriginal) {
                    btnToggleOriginal.setAttribute('data-custom-tooltip', 'View Original');
                    btnToggleOriginal.style.color = '#f59e0b';
                    btnToggleOriginal.style.borderColor = '#f59e0b';
                }

                currentReqDetails = {
                    curType: dataDiv.querySelector('.d-type').textContent,
                    curDates: dataDiv.querySelector('.d-dates').textContent,
                    curReason: dataDiv.querySelector('.d-reason').textContent,
                    curStart: dataDiv.querySelector('.d-cur-start')?.textContent.trim() || '',
                    curEnd: dataDiv.querySelector('.d-cur-end')?.textContent.trim() || '',
                    origType: dataDiv.querySelector('.d-orig-type')?.textContent.trim() || '',
                    origDates: dataDiv.querySelector('.d-orig-dates')?.textContent.trim() || '',
                    origReason: dataDiv.querySelector('.d-orig-reason')?.textContent.trim() || '',
                    origStart: dataDiv.querySelector('.d-orig-start')?.textContent.trim() || '',
                    origEnd: dataDiv.querySelector('.d-orig-end')?.textContent.trim() || '',
                };

                const isTypeDiff = hasOrig && (currentReqDetails.curType || '').trim() !== (currentReqDetails.origType || '').trim();
                const isDatesDiff = hasOrig && (
                    (currentReqDetails.curStart && currentReqDetails.origStart)
                        ? (currentReqDetails.curStart !== currentReqDetails.origStart || currentReqDetails.curEnd !== currentReqDetails.origEnd)
                        : (currentReqDetails.curDates || '').replace(/\s+/g, ' ').trim() !== (currentReqDetails.origDates || '').replace(/\s+/g, ' ').trim()
                );
                const isReasonDiff = hasOrig && (currentReqDetails.curReason || '').trim() !== (currentReqDetails.origReason || '').trim();

                const mChangeBadge = document.getElementById('m_change_badge');
                if (hasOrig) {
                    if (btnToggleOriginal) {
                        btnToggleOriginal.classList.remove('d-none');
                        btnToggleOriginal.classList.add('d-inline-flex');
                    }
                    document.getElementById('m_type').className = isTypeDiff ? 'fw-medium text-warning' : 'fw-medium text-primary';
                    document.getElementById('m_dates').className = isDatesDiff ? 'fw-medium text-warning' : 'fw-medium text-white';
                    if (reasonEl) reasonEl.className = isReasonDiff 
                        ? 'bg-dark p-3 rounded shadow-sm small mt-1 border border-warning text-warning'
                        : 'bg-dark p-3 rounded shadow-sm small mt-1 border border-secondary text-white';
                    if (mChangeBadge) {
                        mChangeBadge.style.setProperty('display', 'inline-flex', 'important');
                        mChangeBadge.classList.remove('d-none');
                        const changeBadgeText = dataDiv.querySelector('.d-change-badge-text')?.textContent.trim() || 'Changes Requested';
                        mChangeBadge.textContent = changeBadgeText;
                    }
                } else {
                    if (btnToggleOriginal) {
                        btnToggleOriginal.classList.add('d-none');
                        btnToggleOriginal.classList.remove('d-inline-flex');
                    }
                    document.getElementById('m_type').className = 'fw-medium text-primary';
                    document.getElementById('m_dates').className = 'fw-medium text-white';
                    if (reasonEl) reasonEl.className = 'bg-dark p-3 rounded shadow-sm small mt-1 border border-secondary text-white';
                    if (mChangeBadge) {
                        mChangeBadge.style.setProperty('display', 'none', 'important');
                        mChangeBadge.classList.add('d-none');
                    }
                }

                const changeStatus = dataDiv.querySelector('.d-change-status')?.textContent.trim() || '';
                const isCancelReq = dataDiv.querySelector('.d-is-cancel-req')?.textContent.trim() === '1';

                if (isCancelReq && changeStatus === 'pending') {
                    if (mChangeBadge) {
                        mChangeBadge.style.setProperty('display', 'inline-block', 'important');
                        mChangeBadge.classList.remove('d-none');
                        mChangeBadge.textContent = 'pending cancellation';
                        mChangeBadge.className = 'small fst-italic text-decoration-underline mt-0.5';
                        mChangeBadge.style.color = '#a855f7';
                    }
                }

                const changeResetAlert = document.getElementById('m_change_reset_alert');
                if (changeResetAlert) {
                    if (hasOrig && !isCancelReq && (changeStatus === 'pending' || changeStatus === '')) {
                        changeResetAlert.classList.remove('d-none');
                    } else {
                        changeResetAlert.classList.add('d-none');
                    }
                }

                // Handle Cancellation Reason box
                const cancelReason = dataDiv.querySelector('.d-cancel-reason')?.textContent.trim();
                const cancelReasonContainer = document.getElementById('m_cancel_reason_container');
                const cancelReasonEl = document.getElementById('m_cancel_reason');

                if (cancelReason) {
                    cancelReasonContainer.classList.remove('d-none');
                    cancelReasonEl.textContent = cancelReason;
                } else {
                    cancelReasonContainer.classList.add('d-none');
                    cancelReasonEl.textContent = '';
                }

                // Super Admin resolved checks
                const isSuperAdminResolved = dataDiv.querySelector('.d-super-admin-resolved')?.textContent?.trim() === 'true';
                const mActionButtons = document.getElementById('m_action_buttons');
                const mChangeButtons = document.getElementById('m_change_buttons');
                const superAdminAlert = document.getElementById('m_super_admin_alert');
                
                const status = dataDiv.querySelector('.d-status').textContent.trim();
                const isFinal = status === 'approved' || status === 'rejected' || status === 'cancelled';
                
                const canApprove = dataDiv.querySelector('.d-can-approve') ? dataDiv.querySelector('.d-can-approve').textContent.trim() === '1' : false;
                const canReject = dataDiv.querySelector('.d-can-reject') ? dataDiv.querySelector('.d-can-reject').textContent.trim() === '1' : false;
                const hasApproved = dataDiv.querySelector('.d-has-approved') ? dataDiv.querySelector('.d-has-approved').textContent.trim() === '1' : false;
                const chainTarget = dataDiv.querySelector('.d-chain-target') ? parseInt(dataDiv.querySelector('.d-chain-target').textContent.trim() || '0') : 0;
                const chainProgress = dataDiv.querySelector('.d-chain-progress') ? parseInt(dataDiv.querySelector('.d-chain-progress').textContent.trim() || '0') : 0;

                const managerApprovedAlert = document.getElementById('m_manager_approved_alert');
                const chainCompletedAlert = document.getElementById('m_chain_completed_alert');
                const chainWaitingLowerAlert = document.getElementById('m_chain_waiting_lower_alert');
                const managerRejectedAlert = document.getElementById('m_manager_rejected_alert');
                const cancelledAlert = document.getElementById('m_cancelled_alert');
                const cancelledAlertText = document.getElementById('m_cancelled_alert_text');
                const cancelBannerText = dataDiv.querySelector('.d-cancel-banner-text') ? dataDiv.querySelector('.d-cancel-banner-text').textContent.trim() : '';

                const isCancelPending = isCancelReq && changeStatus === 'pending';
                const superAdminCancelAlert = document.getElementById('m_super_admin_cancel_alert');

                if (superAdminAlert) superAdminAlert.classList.add('d-none');
                if (superAdminCancelAlert) superAdminCancelAlert.classList.add('d-none');
                if (managerApprovedAlert) managerApprovedAlert.classList.add('d-none');
                if (chainCompletedAlert) chainCompletedAlert.classList.add('d-none');
                if (chainWaitingLowerAlert) chainWaitingLowerAlert.classList.add('d-none');
                if (managerRejectedAlert) managerRejectedAlert.classList.add('d-none');
                if (cancelledAlert) cancelledAlert.classList.add('d-none');

                if (isCancelPending) {
                    if (mActionButtons) mActionButtons.classList.add('d-none');
                    if (mChangeButtons) mChangeButtons.classList.add('d-none');
                    if (superAdminCancelAlert) superAdminCancelAlert.classList.remove('d-none');
                } else if (status === 'cancelled') {
                    if (mActionButtons) mActionButtons.classList.add('d-none');
                    if (mChangeButtons) mChangeButtons.classList.add('d-none');
                    if (mChangeBadge) {
                        if (hasOrig) {
                            mChangeBadge.style.setProperty('display', 'inline-block', 'important');
                            mChangeBadge.classList.remove('d-none');
                            mChangeBadge.textContent = 'changes request cancelled';
                            mChangeBadge.className = 'small fst-italic text-decoration-underline mt-0.5';
                            mChangeBadge.style.color = '#a855f7';
                        } else {
                            mChangeBadge.style.setProperty('display', 'none', 'important');
                            mChangeBadge.classList.add('d-none');
                        }
                    }
                    if (cancelledAlert && cancelBannerText) {
                        cancelledAlert.classList.remove('d-none');
                        if (cancelledAlertText) cancelledAlertText.innerHTML = cancelBannerText;
                    }
                } else if (isSuperAdminResolved || isFinal) {
                    if (mActionButtons) mActionButtons.classList.add('d-none');
                    if (isSuperAdminResolved || status === 'approved' || status === 'rejected') {
                        if (superAdminAlert) superAdminAlert.classList.remove('d-none');
                    }
                } else if (hasApproved) {
                    // Current manager has already approved at their level
                    if (mActionButtons) mActionButtons.classList.add('d-none');
                    if (managerApprovedAlert) managerApprovedAlert.classList.remove('d-none');
                } else if (!canApprove && !canReject) {
                    // Current manager cannot act (e.g. higher manager not in chain, or awaiting lower level)
                    if (mActionButtons) mActionButtons.classList.add('d-none');
                    if (status === 'rejected_by_manager') {
                        if (managerRejectedAlert) {
                            const rejName = dataDiv.querySelector('.d-rejected-by-name')?.textContent.trim() || 'Line Manager';
                            const rejLevel = dataDiv.querySelector('.d-rejected-by-level')?.textContent.trim() || '1';
                            const rejText = document.getElementById('m_manager_rejected_text');
                            if (rejText) {
                                rejText.innerHTML = `This leave request was <strong>rejected by ${rejName} (Level ${rejLevel} of ${chainTarget})</strong>. The approval chain has stopped.`;
                            }
                            managerRejectedAlert.classList.remove('d-none');
                        }
                    } else if (status === 'approved_by_manager' || (chainTarget > 0 && chainProgress >= chainTarget)) {
                        if (chainCompletedAlert) {
                            const chainText = document.getElementById('m_chain_completed_text');
                            if (chainText) {
                                chainText.textContent = `All required line manager approvals (${chainProgress} of ${chainTarget}) are complete. Awaiting CEO / Super Admin final approval.`;
                            }
                            chainCompletedAlert.classList.remove('d-none');
                        }
                    } else {
                        if (chainWaitingLowerAlert) chainWaitingLowerAlert.classList.remove('d-none');
                    }
                } else {
                    // Manager CAN act (approve / reject)
                    if (mActionButtons) mActionButtons.classList.remove('d-none');
                    const btnApprove = document.getElementById('btn-approve');
                    const btnReject = document.getElementById('btn-reject');
                    if (btnApprove) btnApprove.classList.toggle('d-none', !canApprove);
                    if (btnReject) btnReject.classList.toggle('d-none', !canReject);
                }

                // Clear inline alerts
                const alertContainer = document.getElementById('modal-alert-container');
                if (alertContainer) alertContainer.innerHTML = '';
 
                // Reset form safely
                if (typeof actionForm !== 'undefined' && actionForm) {
                    actionForm.reset();
                } else {
                    const formEl = document.getElementById('actionForm') || document.getElementById('managerActionForm');
                    if (formEl) formEl.reset();
                }

                // ── Render Approval Chain Timeline ──
                renderApprovalChain(dataDiv);
                toggleApprovalChainPanel(true); // Close panel on open by default

                // Ensure modal is at body level to prevent stacking context or overflow bugs
                const modalEl = document.getElementById('requestModal');
                if (modalEl && modalEl.parentElement !== document.body) {
                    document.body.appendChild(modalEl);
                }

                // Show modal
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            };

            window.toggleApprovalChainPanel = function(forceClose = false) {
                const panel = document.getElementById('m_chain_panel');
                const dialog = document.getElementById('modalDialog');
                const btnText = document.getElementById('chain-btn-text');
                const btnIcon = document.getElementById('chain-btn-icon');
                if (!panel || !dialog) return;

                const isCurrentlyOpen = !panel.classList.contains('d-none');

                if (forceClose || isCurrentlyOpen) {
                    panel.classList.add('d-none');
                    dialog.classList.remove('modal-xl');
                    dialog.classList.add('modal-lg');
                    if (btnText) btnText.textContent = 'Approval Chain';
                    if (btnIcon) btnIcon.style.transform = 'rotate(0deg)';
                } else {
                    panel.classList.remove('d-none');
                    dialog.classList.remove('modal-lg');
                    dialog.classList.add('modal-xl');
                    if (btnText) btnText.textContent = 'Hide Chain';
                    if (btnIcon) btnIcon.style.transform = 'rotate(90deg)';
                }
            };

            function renderApprovalChain(dataDiv) {
                const chainDataEl = dataDiv.querySelector('.d-chain-data');
                const chainBody   = document.getElementById('m_chain_body');
                const btnToggle   = document.getElementById('btn-toggle-chain');
                if (!chainBody) return;
                chainBody.innerHTML = '';

                if (!chainDataEl) {
                    if (btnToggle) btnToggle.classList.add('d-none');
                    return;
                }

                const steps         = chainDataEl.querySelectorAll('.chain-step');
                const saAction      = chainDataEl.dataset.saAction;
                const saName        = chainDataEl.dataset.saName;
                const saDate        = chainDataEl.dataset.saDate;
                const maxLevel      = parseInt(chainDataEl.dataset.chainMax || '0');
                const hasRejection  = chainDataEl.dataset.hasRejection === '1';
                const rejectedLevel = parseInt(chainDataEl.dataset.rejectedLevel || '0');
                const status        = chainDataEl.dataset.status;
                const isAdminMarked = chainDataEl.dataset.isAdminMarked === '1';

                // ── ADMIN-MARKED: show single CEO node, no chain steps ───────────────
                if (isAdminMarked) {
                    if (btnToggle) btnToggle.classList.remove('d-none');
                    const hasPendingChange = dataDiv.querySelector('.d-is-cancel-req')?.textContent.trim() !== '1'
                        && dataDiv.querySelector('.d-change-status')?.textContent.trim() === 'pending'
                        && dataDiv.querySelector('.d-has-change')?.textContent.trim() === '1';

                    let dotBg = '#22c55e';
                    let statusHtml = `<span class="text-success" style="font-size: 0.72rem; font-weight: 500;"><i class="bi bi-check2 me-0.5"></i> Approved</span>`;

                    if (hasPendingChange) {
                        dotBg = 'rgba(255, 255, 255, 0.3)';
                        statusHtml = `<span class="text-white text-opacity-40 fst-italic" style="font-size: 0.72rem;">Awaiting</span>`;
                    }

                    const saDisplayName = saName || 'CEO / Super Admin';
                    const saDisplayDate = (!hasPendingChange && saDate && saDate !== '—') ? saDate : '';

                    chainBody.insertAdjacentHTML('beforeend', `
                        <div class="mb-4 p-3 rounded-3" style="background: rgba(168, 85, 247, 0.08); border: 1px solid rgba(168, 85, 247, 0.25); padding: 14px 18px !important;">
                            <div class="fw-semibold text-white mb-1" style="font-size: 0.82rem; letter-spacing: 0.2px;">Super Admin — Chain Bypassed</div>
                            <div class="text-white opacity-60" style="font-size: 0.74rem; line-height: 1.45;">This leave was marked directly by the CEO. The approval chain was bypassed.</div>
                        </div>
                        <div class="position-relative" style="padding-left: 26px;">
                            <div style="position: absolute; left: 4px; top: 6px; width: 8px; height: 8px; border-radius: 50%; background: ${dotBg}; z-index: 2;"></div>
                            <div>
                                <div class="d-flex align-items-center justify-content-between gap-2" style="line-height: 1.3;">
                                    <span class="text-white text-opacity-90 text-truncate" style="font-size: 0.78rem; font-weight: 400; max-width: 135px;" title="${saDisplayName}">${saDisplayName}</span>
                                    ${statusHtml}
                                </div>
                                <div class="d-flex align-items-center justify-content-between text-white text-opacity-40 mt-0.5" style="font-size: 0.70rem;">
                                    <span>Super Admin (CEO)</span>
                                    <span>${saDisplayDate}</span>
                                </div>
                            </div>
                        </div>
                    `);
                    return;
                }

                if (steps.length === 0 && maxLevel === 0) {
                    if (btnToggle) btnToggle.classList.add('d-none');
                    return;
                }

                if (btnToggle) btnToggle.classList.remove('d-none');

                steps.forEach((step) => {
                    const lvl       = step.dataset.level;
                    const act       = step.dataset.action;
                    const name      = step.dataset.name;
                    const desig     = step.dataset.designation;
                    const date      = step.dataset.date;
                    const isMissing = step.dataset.isMissing === '1';
                    const isSkipped = step.dataset.isSkipped === '1';

                    let dotBg = 'rgba(255, 255, 255, 0.3)';
                    let statusHtml = `<span class="text-white text-opacity-40 fst-italic" style="font-size: 0.72rem;">Awaiting</span>`;

                    if (act === 'approved') {
                        dotBg = '#22c55e';
                        statusHtml = `<span class="text-success" style="font-size: 0.72rem; font-weight: 500;"><i class="bi bi-check2 me-0.5"></i> Approved</span>`;
                    } else if (act === 'rejected') {
                        dotBg = '#ef4444';
                        statusHtml = `<span class="text-danger" style="font-size: 0.72rem; font-weight: 500;"><i class="bi bi-x me-0.5"></i> Rejected</span>`;
                    } else if (isMissing) {
                        dotBg = 'rgba(255, 255, 255, 0.15)';
                        statusHtml = `<span class="text-white text-opacity-40 fst-italic" style="font-size: 0.72rem;">N/A</span>`;
                    } else if (isSkipped || status === 'cancelled') {
                        dotBg = 'rgba(255, 255, 255, 0.15)';
                        statusHtml = `<span class="text-white text-opacity-40 fst-italic" style="font-size: 0.72rem;">Skipped</span>`;
                    }

                    let nameDisplay = name ? name : `Level ${lvl} Approver`;
                    let desigDisplay = desig || 'Manager (L' + lvl + ')';

                    if (isMissing) {
                        nameDisplay = `Level ${lvl} (No Manager)`;
                        desigDisplay = 'Not in Hierarchy';
                    }

                    const dateDisplay = date && date !== '—' ? date : '';

                    chainBody.insertAdjacentHTML('beforeend', `
                        <div class="position-relative" style="padding-left: 26px; padding-bottom: 20px;">
                            <!-- Dotted Vertical Connecting Line -->
                            <div style="position: absolute; left: 7.5px; top: 12px; bottom: 0; width: 1px; border-left: 1px dotted rgba(255, 255, 255, 0.2); z-index: 1;"></div>
                            
                            <!-- Step Dot (Small & Minimal) -->
                            <div style="position: absolute; left: 4px; top: 6px; width: 8px; height: 8px; border-radius: 50%; background: ${dotBg}; z-index: 2;"></div>
                            
                            <!-- Step Info -->
                            <div>
                                <div class="d-flex align-items-center justify-content-between gap-2" style="line-height: 1.3;">
                                    <span class="text-white text-opacity-90 text-truncate" style="font-size: 0.78rem; font-weight: 400; max-width: 135px;" title="${nameDisplay}">${nameDisplay}</span>
                                    ${statusHtml}
                                </div>
                                <div class="d-flex align-items-center justify-content-between text-white text-opacity-40 mt-0.5" style="font-size: 0.70rem;">
                                    <span>${desigDisplay}</span>
                                    <span>${dateDisplay}</span>
                                </div>
                            </div>
                        </div>
                    `);
                });

                // Final Super Admin / CEO Step
                let saDotBg = 'rgba(255, 255, 255, 0.3)';
                let saStatusHtml = `<span class="text-white text-opacity-40 fst-italic" style="font-size: 0.72rem;">Awaiting</span>`;

                const isCancelPending = dataDiv.querySelector('.d-is-cancel-req')?.textContent.trim() === '1' && dataDiv.querySelector('.d-change-status')?.textContent.trim() === 'pending';

                if (saAction === 'approved' && !isCancelPending) {
                    saDotBg = '#22c55e';
                    saStatusHtml = `<span class="text-success" style="font-size: 0.72rem; font-weight: 500;"><i class="bi bi-check2 me-0.5"></i> Approved</span>`;
                } else if (saAction === 'rejected' && !isCancelPending) {
                    saDotBg = '#ef4444';
                    saStatusHtml = `<span class="text-danger" style="font-size: 0.72rem; font-weight: 500;"><i class="bi bi-x me-0.5"></i> Rejected</span>`;
                } else if (status === 'cancelled') {
                    saDotBg = 'rgba(255, 255, 255, 0.15)';
                    saStatusHtml = `<span class="text-white text-opacity-40 fst-italic" style="font-size: 0.72rem;">Skipped</span>`;
                }

                const saNameDisplay = saName ? saName : 'CEO / Super Admin';
                const saDateDisplay = (saAction && !isCancelPending && saDate && saDate !== '—') ? saDate : '';

                chainBody.insertAdjacentHTML('beforeend', `
                    <div class="position-relative" style="padding-left: 26px;">
                        <!-- Step Dot (Small & Minimal) -->
                        <div style="position: absolute; left: 4px; top: 6px; width: 8px; height: 8px; border-radius: 50%; background: ${saDotBg}; z-index: 2;"></div>
                        
                        <!-- Step Info -->
                        <div>
                            <div class="d-flex align-items-center justify-content-between gap-2" style="line-height: 1.3;">
                                <span class="text-white text-opacity-90 text-truncate" style="font-size: 0.78rem; font-weight: 400; max-width: 135px;" title="${saNameDisplay}">${saNameDisplay}</span>
                                ${saStatusHtml}
                            </div>
                            <div class="d-flex align-items-center justify-content-between text-white text-opacity-40 mt-0.5" style="font-size: 0.70rem;">
                                <span>Super Admin</span>
                                <span>${saDateDisplay}</span>
                            </div>
                        </div>
                    </div>
                `);
            }
 
            window.submitAction = function(actionType) {
                if (!currentRequestId) return;
                
                // Set the correct endpoint based on action
                actionForm.action = `/admin/leave-requests/${currentRequestId}/${actionType}`;
                
                // Reset inline alerts
                const alertContainer = document.getElementById('modal-alert-container');
                if (alertContainer) alertContainer.innerHTML = '';
 
                // Check if comment is required for "Just Comment"
                if (actionType === 'comment') {
                    const commentVal = actionForm.querySelector('textarea[name="comment"]').value.trim();
                    if (!commentVal) {
                        if (alertContainer) {
                            alertContainer.innerHTML = `
                                <div class="alert alert-danger alert-dismissible fade show py-2 px-3 small mb-3 d-flex align-items-center justify-content-between" role="alert">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-exclamation-circle-fill"></i>
                                        <span>Please enter a comment before submitting.</span>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.6rem; font-size: 0.65rem; filter: none !important;"></button>
                                </div>`;
                        }
                        return;
                    }
                } else {
                    // For approve/reject/approve-change/reject-change, use confirmation modal
                    pendingActionType = actionType;
                    const confirmTitle = document.getElementById('confirmTitle');
                    const confirmText = document.getElementById('confirmText');
                    const btnConfirmAction = document.getElementById('btnConfirmAction');
 
                    if (actionType === 'approve') {
                        confirmTitle.textContent = 'Approve Leave Request?';
                        confirmText.textContent = 'Are you sure you want to approve this leave request? This will record your recommendation.';
                        btnConfirmAction.className = 'row-btn-green px-4';
                        btnConfirmAction.textContent = 'Yes, Approve';
                    } else if (actionType === 'reject') {
                        confirmTitle.textContent = 'Reject Leave Request?';
                        confirmText.textContent = 'Are you sure you want to reject this leave request? This will decline the request.';
                        btnConfirmAction.className = 'row-btn-red px-4';
                        btnConfirmAction.textContent = 'Yes, Reject';
                    } else if (actionType === 'approve-change') {
                        confirmTitle.textContent = 'Approve Requested Changes?';
                        confirmText.textContent = 'Are you sure you want to approve these changes? This will override the original leave request.';
                        btnConfirmAction.className = 'row-btn-green px-4';
                        btnConfirmAction.textContent = 'Approve Changes';
                    } else if (actionType === 'reject-change') {
                        confirmTitle.textContent = 'Reject Requested Changes?';
                        confirmText.textContent = 'Are you sure you want to reject these changes? The original approved leave will remain unchanged.';
                        btnConfirmAction.className = 'row-btn-red px-4';
                        btnConfirmAction.textContent = 'Reject Changes';
                    }
 
                    const confirmModalEl = document.getElementById('confirmActionModal');
                    if (confirmModalEl && confirmModalEl.parentElement !== document.body) {
                        document.body.appendChild(confirmModalEl);
                    }
                    const confirmModal = bootstrap.Modal.getOrCreateInstance(confirmModalEl);
                    confirmModal.show();
                    return;
                }
                
                actionForm.submit();
            };
 
            document.getElementById('btnConfirmAction').addEventListener('click', function() {
                if (pendingActionType) {
                    const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmActionModal'));
                    if (confirmModal) confirmModal.hide();
                    actionForm.submit();
                }
            });

            window.selectFilterOption = function(e, inputId, value) {
                e.preventDefault();
                const input = document.getElementById(inputId);
                if (input) {
                    input.value = value;
                }
                const form = document.getElementById('filterForm');
                if (form) {
                    form.submit();
                }
            };
        </script>
    @endpush
</x-app-layout>

