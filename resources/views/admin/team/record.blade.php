<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <span>My Attendance &amp; Logs</span>
            <a href="{{ route('admin.team.track-record', ['user_id' => auth()->id()]) }}" class="row-btn text-decoration-none d-inline-flex align-items-center gap-2" style="font-size: 0.85rem; font-weight: normal; text-transform: none; line-height: 24px; padding: 6px 16px;">
                <i class="bi bi-file-earmark-bar-graph"></i> View Your Track Record
            </a>
        </div>
    </x-slot>

    <style>
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

    @php
        $type = $type ?? 'leaves';
        $typeLabels = [
            'leaves' => 'Leave Requests',
            'absent' => 'Absence Logs',
            'remote' => 'Remote Logs (WFH)',
        ];
        $currentTypeLabel = $typeLabels[$type] ?? 'Leave Requests';

        $statusLabels = [
            '' => 'All Statuses',
            'pending' => 'Pending Manager Approval',
            'approved_by_manager' => 'Pending CEO Approval',
            'approved' => 'Fully Approved',
            'rejected_by_manager' => 'Rejected by Line Manager',
            'rejected' => 'Fully Rejected',
            'changes_requested' => 'Changes Requested',
            'pending_cancellation' => 'Pending Cancellation',
            'cancelled' => 'Cancelled',
        ];
        $currentStatus = (string)request('status', '');
        $currentStatusLabel = $statusLabels[$currentStatus] ?? 'All Statuses';

        $currentLeaveType = $leaveTypes->firstWhere('id', request('leave_type_id'));
        $currentLeaveTypeLabel = $currentLeaveType ? $currentLeaveType->name : 'All Leave Types';
    @endphp

    <!-- Filters Row -->
    <div class="spotify-card mb-4" style="position: relative; z-index: 1050 !important; overflow: visible !important;">
        <div class="p-4 pt-4">
            <form class="row g-3 align-items-center" method="GET" id="filterForm">
                <input type="hidden" name="type" id="filter_type" value="{{ $type }}">
                <input type="hidden" name="leave_type_id" id="filter_leave_type_id" value="{{ request('leave_type_id') }}">
                <input type="hidden" name="status" id="filter_status" value="{{ request('status') }}">

                <!-- Record Type Dropdown -->
                <div class="{{ $type === 'leaves' ? 'col-md-2' : 'col-md-4' }}">
                    <label class="form-label small text-white opacity-50 mb-1">Record Type</label>
                    <div class="dropdown department-dropdown w-100">
                        <button class="form-select border-0 shadow-sm text-start text-white w-100" 
                                type="button" 
                                id="recordTypeDropdown" 
                                data-bs-toggle="dropdown" 
                                aria-expanded="false"
                                style="border: 1px solid rgba(255, 255, 255, 0.15) !important; background-color: rgba(255, 255, 255, 0.03); border-radius: 4px; height: 38px; font-size: 0.85rem; color: #ffffff !important;">
                            <span class="text-truncate d-inline-block w-100" style="max-width: calc(100% - 15px);">{{ $currentTypeLabel }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark p-0 w-100" aria-labelledby="recordTypeDropdown">
                            @foreach($typeLabels as $val => $lbl)
                                <li>
                                    <a class="dropdown-item py-2 text-white @if($type === $val) active @endif" 
                                       href="#" 
                                       onclick="selectFilterOption(event, 'filter_type', '{{ $val }}')">{{ $lbl }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                @if($type === 'leaves')
                <!-- Leave Type Dropdown -->
                <div class="col-md-2">
                    <label class="form-label small text-white opacity-50 mb-1">Leave Type</label>
                    <div class="dropdown department-dropdown w-100">
                        <button class="form-select border-0 shadow-sm text-start text-white w-100" 
                                type="button" 
                                id="leaveTypeDropdown" 
                                data-bs-toggle="dropdown" 
                                aria-expanded="false"
                                style="border: 1px solid rgba(255, 255, 255, 0.15) !important; background-color: rgba(255, 255, 255, 0.03); border-radius: 4px; height: 38px; font-size: 0.85rem; color: #ffffff !important;">
                            <span class="text-truncate d-inline-block w-100" style="max-width: calc(100% - 15px);">{{ $currentLeaveTypeLabel }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark p-0 w-100" aria-labelledby="leaveTypeDropdown">
                            <li>
                                <a class="dropdown-item py-2 text-white @if(!request('leave_type_id')) active @endif" 
                                   href="#" 
                                   onclick="selectFilterOption(event, 'filter_leave_type_id', '')">All Leave Types</a>
                            </li>
                            @foreach($leaveTypes as $t)
                                <li>
                                    <a class="dropdown-item py-2 text-white @if(request('leave_type_id') == $t->id) active @endif" 
                                       href="#" 
                                       onclick="selectFilterOption(event, 'filter_leave_type_id', '{{ $t->id }}')">{{ $t->name }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <!-- Status Dropdown -->
                <div class="col-md-2">
                    <label class="form-label small text-white opacity-50 mb-1">Status</label>
                    <div class="dropdown department-dropdown w-100">
                        <button class="form-select border-0 shadow-sm text-start text-white w-100" 
                                type="button" 
                                id="statusDropdown" 
                                data-bs-toggle="dropdown" 
                                aria-expanded="false"
                                style="border: 1px solid rgba(255, 255, 255, 0.15) !important; background-color: rgba(255, 255, 255, 0.03); border-radius: 4px; height: 38px; font-size: 0.85rem; color: #ffffff !important;">
                            <span class="text-truncate d-inline-block w-100" style="max-width: calc(100% - 15px);">{{ $currentStatusLabel }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark p-0 w-100" aria-labelledby="statusDropdown">
                            @foreach($statusLabels as $val => $lbl)
                                <li>
                                    <a class="dropdown-item py-2 text-white @if($currentStatus === (string)$val) active @endif" 
                                       href="#" 
                                       onclick="selectFilterOption(event, 'filter_status', '{{ $val }}')">{{ $lbl }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                @endif

                <div class="{{ $type === 'leaves' ? 'col-md-2' : 'col-md-3' }}">
                    <label class="form-label small text-white opacity-50 mb-1">From Date</label>
                    <input type="date" name="start_date" class="form-control border-0 text-white" value="{{ request('start_date') }}" onchange="document.getElementById('filterForm').submit()"
                           style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; height: 38px;">
                </div>
                <div class="{{ $type === 'leaves' ? 'col-md-2' : 'col-md-3' }}">
                    <label class="form-label small text-white opacity-50 mb-1">To Date</label>
                    <input type="date" name="end_date" class="form-control border-0 text-white" value="{{ request('end_date') }}" onchange="document.getElementById('filterForm').submit()"
                           style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; height: 38px;">
                </div>
                <div class="col-md-2 text-end mt-auto">
                    <a href="{{ route('admin.leaves.record') }}" class="btn btn-outline-secondary px-4 text-decoration-none shadow-sm d-flex align-items-center justify-content-center gap-1.5" style="height: 38px; width: 100%;">
                        <i class="bi bi-x-circle me-1"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Leave Record / Attendance Log Card (Full Width) -->
    <div class="spotify-card mb-4" style="overflow: hidden; border: 1px solid rgba(255, 255, 255, 0.08) !important;">
        <div class="card-header-area px-4 pt-4">
            <h6 class="text-uppercase text-white fw-bold small mb-0 opacity-75" style="letter-spacing: 0.05em;">
                <i class="bi bi-list-task me-2 text-primary"></i>
                @if($type === 'leaves')
                    My Leave Record &amp; Conversations
                @elseif($type === 'absent')
                    My Absence Logs
                @else
                    My Remote Logs (WFH)
                @endif
            </h6>
            <div class="grid-separator mt-3"></div>
        </div>
        <div class="table-responsive mt-3">
            @if($type === 'leaves')
            <table class="table table-hover align-middle mb-0" id="leave-requests-table">
                <thead>
                    <tr class="border-bottom border-secondary border-opacity-25 text-white opacity-50 small text-uppercase">
                        <th class="ps-4">Type</th>
                        <th>Dates</th>
                        <th>Entry On</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Messages</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentRequests as $req)
                        <tr id="leave-row-{{ $req->id }}" class="hover-highlight">
                            <td class="ps-4 fw-semibold text-white">{{ $req->leaveType->name }}</td>
                            <td><span class="text-white opacity-75">{{ $req->start_date->format('M d') }} - {{ $req->end_date->format('M d, Y') }}</span></td>
                            <td><span class="text-white opacity-75">{{ $req->created_at->format('M d, Y') }}</span></td>
                            <td><span class="row-badge" style="background: rgba(255, 255, 255, 0.08); color: rgba(255, 255, 255, 0.85); border: transparent;">{{ $req->days_count }}</span></td>
                            <td>
                                <div>
                                    @php
                                        $cp = (int)$req->approval_chain_progress;
                                        $ct = (int)$req->approval_chain_target;
                                        $rejLevel = $req->rejectionLevel();
                                    @endphp
                                    @if($req->hasPendingCancellation())
                                        <span class="row-badge-gray"><i class="bi bi-clock-history me-1"></i> Pending Cancellation</span>
                                    @elseif($req->status === 'approved_by_manager')
                                        <span class="row-badge-blue">
                                            <i class="bi bi-clock-history me-1"></i>
                                            @if($cp > 0 && $ct > 0)
                                                Level {{ $cp }}/{{ $ct }} Approved &mdash; Pending CEO
                                            @else
                                                Manager Approved (Pending CEO)
                                            @endif
                                        </span>
                                    @elseif($req->status === 'approved' && $req->is_admin_marked && $req->hasOutstandingChangeRequest() && !$req->hasPendingCancellation())
                                        <span class="row-badge-purple"><i class="bi bi-clock-history me-1"></i> Pending Changes</span>
                                    @elseif($req->status === 'approved')
                                        <span class="row-badge-green"><i class="bi bi-check-circle-fill me-1"></i> Fully Approved</span>
                                    @elseif($req->status === 'rejected')
                                        <span class="row-badge-red"><i class="bi bi-x-circle-fill me-1"></i> Rejected</span>
                                    @elseif($req->status === 'rejected_by_manager')
                                        <span class="row-badge-red">
                                            <i class="bi bi-x-circle-fill me-1"></i>
                                            @if($rejLevel > 0 && $ct > 0)
                                                Rejected at Level {{ $rejLevel }}/{{ $ct }}
                                            @else
                                                Rejected by Manager
                                            @endif
                                        </span>
                                    @elseif($req->status === 'cancelled')
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
                                                    Pending Manager
                                                @endif
                                            </span>
                                        @endif
                                    @endif
                                </div>
                                @if($req->hasOutstandingChangeRequest() && !$req->hasPendingCancellation() && !($req->status === 'approved' && $req->is_admin_marked))
                                    <div class="mt-1">
                                        <span class="row-badge-purple">
                                            <i class="bi bi-clock-history me-1"></i> {{ $req->changeRequestBadgeText() }}
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($req->comments->count() > 0)
                                    <span class="row-badge-blue">
                                        <i class="bi bi-chat-text-fill me-1"></i> {{ $req->comments->count() }}
                                    </span>
                                @else
                                    <span class="text-white opacity-50 small">No messages</span>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-flex gap-2 justify-content-end align-items-center">
                                    <button type="button" class="row-btn-outline-blue" onclick="openRequestModal({{ $req->id }})">
                                        <i class="bi bi-chat-dots me-1"></i> Chat
                                    </button>
                                    @php
                                        $canCancel = !in_array($req->status, ['cancelled', 'rejected']) 
                                            && !($req->change_request_status === 'pending' && !empty($req->change_request['request_cancellation']));
                                        $canEdit = in_array($req->status, ['pending', 'approved_by_manager', 'rejected_by_manager']) 
                                            && !$req->hasOutstandingChangeRequest();
                                        $requiresApproval = \Carbon\Carbon::parse($req->start_date)->lte(\Carbon\Carbon::today()) && $req->status === \App\Models\LeaveRequest::STATUS_APPROVED;
                                    @endphp

                                    @if($canCancel)
                                        <button type="button" class="row-btn-outline-red px-3 py-1 btn-cancel-leave" 
                                                data-id="{{ $req->id }}" 
                                                data-start="{{ $req->start_date->format('Y-m-d') }}"
                                                data-end="{{ $req->end_date->format('Y-m-d') }}"
                                                data-requires-approval="{{ $requiresApproval ? '1' : '0' }}"
                                                data-future="{{ $requiresApproval ? '0' : '1' }}"
                                                style="font-size: 0.78rem;">Cancel</button>
                                    @endif
                                    @if($canEdit)
                                        <button type="button" class="btn-circle-gray d-inline-flex align-items-center justify-content-center" 
                                                data-id="{{ $req->id }}"
                                                data-start="{{ $req->start_date->format('Y-m-d') }}"
                                                data-end="{{ $req->end_date->format('Y-m-d') }}"
                                                data-type="{{ $req->leave_type_id }}"
                                                data-status="{{ $req->status }}"
                                                data-reason="{{ $req->reason }}"
                                                onclick="openChangeRequestModal(this)"
                                                data-custom-tooltip="Change request"
                                                data-tooltip-placement="top">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        <!-- Hidden Data for Chat Modal -->
                        <div id="request-data-{{ $req->id }}" class="d-none">
                            <span class="d-type">{{ $req->leaveType->name }}</span>
                            <span class="d-dates">{{ $req->start_date->format('M d, Y') }} - {{ $req->end_date->format('M d, Y') }} ({{ $req->formatted_days_count }})</span>
                            <span class="d-reason">{{ $req->reason }}</span>
                            <span class="d-status">
                                @if($req->status === 'approved_by_manager')
                                    Manager Approved
                                @elseif($req->status === 'approved')
                                    Fully Approved
                                @elseif($req->status === 'rejected')
                                    Rejected
                                @elseif($req->status === 'cancelled')
                                    Cancelled
                                @else
                                    Pending Manager
                                @endif
                            </span>
                            <span class="d-has-change">{{ $req->hasOutstandingChangeRequest() ? '1' : '0' }}</span>
                            <span class="d-change-status">{{ $req->change_request_status }}</span>
                            @if($req->hasOutstandingChangeRequest())
                                <span class="d-change-cancel">{{ !empty($req->change_request['request_cancellation']) ? '1' : '0' }}</span>
                                <span class="d-change-dates">
                                    @if(!empty($req->change_request['start_date']) || !empty($req->change_request['end_date']))
                                        {{ \Carbon\Carbon::parse($req->change_request['start_date'] ?? $req->start_date)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($req->change_request['end_date'] ?? $req->end_date)->format('M d, Y') }}
                                    @else
                                        N/A
                                    @endif
                                </span>
                                <span class="d-change-type">
                                    @if(!empty($req->change_request['leave_type_id']))
                                        {{ \App\Models\LeaveType::find($req->change_request['leave_type_id'])?->name ?? 'N/A' }}
                                    @else
                                        N/A
                                    @endif
                                </span>
                                <span class="d-change-reason">{{ $req->change_request['reason'] ?? 'N/A' }}</span>
                            @endif
                            <span class="d-cancel-reason">{{ $req->change_request['cancel_reason'] ?? '' }}</span>
                            <span class="d-cancel-banner-text">{{ $req->cancellationBannerText() }}</span>
                            <span class="d-is-admin-marked">{{ $req->is_admin_marked ? '1' : '0' }}</span>
                            {{-- Approval Chain Timeline Data --}}
                            @php
                                $chainEntries  = $req->approvalChain;
                                $chainTarget   = (int)$req->approval_chain_target;
                                $chainProgress = (int)$req->approval_chain_progress;
                                $maxLevel      = max($chainTarget, $chainEntries->max('level') ?? 0);
                                $saReviewer    = ($req->reviewer && $req->reviewer->isSuperAdmin()) ? $req->reviewer : null;
                                $saApprovedBy  = !empty($req->change_request['approved_by']) ? \App\Models\User::find($req->change_request['approved_by']) : null;
                                $hasSAActed    = ($saReviewer !== null || ($saApprovedBy && $saApprovedBy->isSuperAdmin()));
                                $hasPendingCancel = $req->hasPendingCancellation();
                                $saAction      = match(true) {
                                    $hasPendingCancel => '',
                                    $req->status === 'approved' => 'approved',
                                    $req->status === 'rejected' => 'rejected',
                                    $req->status === 'cancelled' && $hasSAActed => 'approved',
                                    default => ''
                                };
                                $bossChain     = app(\App\Services\HierarchyService::class)->getBossChain($req->user);
                                $hasRejection  = $chainEntries->contains('action', 'rejected') || $req->status === 'rejected_by_manager';
                                $rejectedLevel = $req->rejectionLevel();
                                $isSAResolved  = in_array($req->status, ['approved', 'rejected']) || ($req->status === 'cancelled' && $hasSAActed) || $saReviewer !== null;
                            @endphp
                            <div class="d-chain-data"
                                 data-chain-target="{{ $chainTarget }}"
                                 data-chain-max="{{ $maxLevel }}"
                                 data-sa-action="{{ $saAction }}"
                                 data-sa-name="{{ $saReviewer?->name ?? ($saApprovedBy?->name ?? '') }}"
                                 data-sa-date="{{ (($saReviewer || $saApprovedBy) && !$hasPendingCancel) ? ($req->reviewed_at?->format('M d, Y') ?? '') : '' }}"
                                 data-has-rejection="{{ $hasRejection ? '1' : '0' }}"
                                 data-rejected-level="{{ $rejectedLevel }}"
                                 data-sa-resolved="{{ $isSAResolved ? '1' : '0' }}"
                                 data-status="{{ $req->status }}"
                                 data-is-admin-marked="{{ $req->is_admin_marked ? '1' : '0' }}"
                            >
                                @for($lvl = 1; $lvl <= max($maxLevel, 1); $lvl++)
                                    @php
                                        $entry = $chainEntries->firstWhere('level', $lvl);
                                        $boss  = $bossChain[$lvl - 1] ?? null;
                                        $isMissing = ($entry === null && $boss === null);
                                        $entryAct  = $entry?->action ?? '';
                                        $isSkipped = empty($entryAct) && (($hasRejection && $lvl > $rejectedLevel) || $isSAResolved || $req->status === 'cancelled');
                                    @endphp
                                    <span class="chain-step"
                                          data-level="{{ $lvl }}"
                                          data-action="{{ $entry?->action ?? '' }}"
                                          data-name="{{ $entry?->approver?->name ?? ($boss?->name ?? '') }}"
                                          data-designation="{{ $entry?->approver?->designation?->name ?? ($boss?->designation?->name ?? 'Employee') }}"
                                          data-date="{{ $entry?->acted_at?->format('M d, Y') ?? '' }}"
                                          data-is-missing="{{ $isMissing ? '1' : '0' }}"
                                          data-is-skipped="{{ $isSkipped ? '1' : '0' }}"
                                    ></span>
                                @endfor
                            </div>
                            <div class="d-comments">
                                @forelse($req->comments->sortBy('created_at') as $comment)
                                    @php
                                        $cLower = strtolower($comment->comment);
                                        $isSystem = str_contains($cLower, 'reset') || str_contains($cLower, 'requested') || str_contains($cLower, 'updated') || str_starts_with($comment->comment, '[System]');
                                        $isEmp = $comment->user_id === $req->user_id;
                                        $isSA = $comment->user?->isSuperAdmin() ?? false;
                                        $isLM = $comment->user?->hasSubordinates() ?? false;
                                        
                                        $bgColor = 'bg-dark text-white border-secondary border-opacity-25';
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
                                            $bgColor = 'bg-dark text-white border border-secondary border-opacity-25';
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
                                        <div class="mb-3 p-3 rounded border {{ $bgColor }}">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <div>
                                                    <strong class="small text-white">{{ $comment->user->name ?? 'System' }}</strong>
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
                        <tr><td colspan="7" class="text-center py-5 text-white opacity-50"><i class="bi bi-inbox fs-3 d-block mb-2"></i> No leave requests match the filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @else
            <table class="table table-hover align-middle mb-0" id="attendance-logs-table">
                <thead>
                    <tr class="border-bottom border-secondary border-opacity-25 text-white opacity-50 small text-uppercase">
                        <th class="ps-4">Dates</th>
                        <th>Status</th>
                        <th>Days</th>
                        <th>Reason/Notes</th>
                        <th class="text-end pe-4">Marked By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentRequests as $log)
                        <tr class="hover-highlight text-white" style="border-bottom: 1px solid rgba(255, 255, 255, 0.03) !important;">
                            <td class="ps-4 font-monospace text-warning">{{ $log->date_range_display }}</td>
                            <td>
                                @if($type === 'remote')
                                    <span class="badge" style="font-size: 0.65rem; padding: 0.2rem 0.6rem; background-color: rgba(59, 130, 246, 0.12); border: 1px solid rgba(59, 130, 246, 0.3); color: #60a5fa; border-radius: 50px !important; font-weight: normal;">Working Remote</span>
                                @else
                                    <span class="badge" style="font-size: 0.65rem; padding: 0.2rem 0.6rem; background-color: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; border-radius: 50px !important; font-weight: normal;">Absent</span>
                                @endif
                            </td>
                            <td>
                                <span class="row-badge" style="background: rgba(255, 255, 255, 0.1); color: rgba(255, 255, 255, 0.75); border: transparent;">
                                    {{ $log->days_count }} {{ Str::plural('day', $log->days_count) }}
                                </span>
                            </td>
                            <td><span class="text-white opacity-75 small">{{ $log->notes ?? '-' }}</span></td>
                            <td class="text-end pe-4">
                                <span class="row-badge-green">{{ $log->marker->name ?? 'System' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-white opacity-50">
                                <div class="mb-2"><i class="bi bi-calendar-x fs-3 text-warning"></i></div>
                                No status logs match the filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            @endif
        </div></div>
        @if($recentRequests->hasPages())
            <div class="card-footer bg-transparent border-0 pt-3">{{ $recentRequests->links() }}</div>
        @endif
    </div>

    @push('modals')
    <!-- View Request Details / Conversation Modal -->
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
                        <!-- Left Side: Details (Fixed width ~320px) -->
                        <div class="p-4 bg-dark border-end border-secondary border-opacity-25 d-flex flex-column justify-content-between overflow-auto custom-scrollbar" style="width: 320px; flex-shrink: 0; max-height: 580px;">
                            <div>
                                <h6 class="text-uppercase text-white opacity-50 fw-bold small mb-3"><i class="bi bi-info-circle me-1"></i> Request Details</h6>
                                <div class="mb-3">
                                    <label class="small text-white opacity-50 mb-0">Leave Type</label>
                                    <div class="fw-medium text-primary" id="m_type"></div>
                                </div>
                                <div class="mb-3">
                                    <label class="small text-white opacity-50 mb-0">Duration</label>
                                    <div class="fw-medium text-white" id="m_dates"></div>
                                </div>
                                <div class="mb-3">
                                    <label class="small text-white opacity-50 mb-0">Status</label>
                                    <div class="fw-medium text-white" id="m_status"></div>
                                </div>
                                <div class="mb-3">
                                    <label class="small text-white opacity-50 mb-0">Reason Provided</label>
                                    <div class="bg-dark p-3 rounded shadow-sm small mt-1 border border-secondary text-white" id="m_reason" style="min-height: 80px; background-color: rgba(255,255,255,0.02);"></div>
                                </div>
                                <div id="m_cancel_reason_container" class="mb-3 d-none">
                                    <label class="small text-danger mb-0 fw-semibold">Reason for Cancellation</label>
                                    <div class="small text-white mt-1" id="m_cancel_reason" style="word-break: break-word;"></div>
                                </div>

                                <!-- Comparison Section (visible when change requested) -->
                                <div id="m_comparison_section" class="d-none mt-3 p-3 rounded" style="background: rgba(255, 193, 7, 0.05); border: 1px solid rgba(255, 193, 7, 0.15);">
                                    <h6 class="text-warning small fw-bold mb-3"><i class="bi bi-arrow-left-right me-1"></i> Requested Changes</h6>
                                    
                                    <div id="m_change_cancel_alert" class="alert alert-warning py-2 px-3 small d-none" style="background: rgba(255, 193, 7, 0.08); border: 1px solid rgba(255, 193, 7, 0.25); color: #ffc107;">
                                        <i class="bi bi-clock-history me-1"></i> <strong>Cancellation Requested!</strong> Awaiting CEO / Super Admin approval for cancellation.
                                    </div>

                                    <div id="m_change_details_pane">
                                        <div class="mb-2">
                                            <label class="small text-white opacity-50 mb-0">Requested Dates</label>
                                            <div class="fw-medium text-warning" id="m_change_dates"></div>
                                        </div>
                                        <div class="mb-2">
                                            <label class="small text-white opacity-50 mb-0">Requested Leave Type</label>
                                            <div class="fw-medium text-warning" id="m_change_type"></div>
                                        </div>
                                        <div class="mb-2">
                                            <label class="small text-white opacity-50 mb-0">Updated Reason</label>
                                            <div class="small text-white" id="m_change_reason" style="word-break: break-word;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom of Left Card: Toggle Approval Chain Button (Full Width) -->
                            <div class="pt-3 mt-3 border-top border-secondary border-opacity-25 w-100">
                                <button type="button" class="btn btn-sm btn-outline-secondary w-100 d-flex align-items-center justify-content-between text-white py-2 px-3" id="btn-toggle-chain" onclick="toggleApprovalChainPanel()" style="font-size: 0.78rem; border-color: rgba(255,255,255,0.15) !important; background: rgba(255,255,255,0.03); border-radius: 8px;">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-diagram-3 text-primary fs-6"></i>
                                        <span id="chain-btn-text" class="fw-medium">Approval Chain</span>
                                    </div>
                                    <i class="bi bi-chevron-right text-white opacity-50" id="chain-btn-icon" style="transition: transform 0.25s ease; font-size: 0.72rem;"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Middle Column: Conversation Log (Flex Grow) -->
                        <div class="col-md-7 p-4 d-flex flex-column flex-grow-1 bg-dark overflow-auto custom-scrollbar" style="max-height: 580px; min-width: 360px;">
                            <div id="m_cancelled_alert" class="alert bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 py-2 px-3 small mb-3 d-none">
                                <i class="bi bi-x-circle-fill me-1"></i> <span id="m_cancelled_alert_text"></span>
                            </div>
                            <h6 class="text-uppercase text-white opacity-50 fw-bold small mb-3"><i class="bi bi-chat-dots me-1"></i> Conversation Log</h6>
                            
                            <!-- Comments Area -->
                            <div class="flex-grow-1 overflow-auto mb-3 custom-scrollbar chat-history-outline d-flex flex-column" id="m_comments" style="max-height: 290px;">
                                <!-- Comments appended here -->
                            </div>
                            
                            <!-- Action Form -->
                            <form method="POST" id="actionForm" class="mt-auto">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label text-white opacity-75 small fw-semibold">Add Message</label>
                                    <textarea name="comment" class="form-control text-white" rows="2" placeholder="Send a message to super admin..." 
                                              style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important;" required></textarea>
                                </div>
                                
                                <div class="text-end">
                                    <button type="submit" class="row-btn-blue btn-sm px-3 d-flex align-items-center gap-1 ms-auto" style="height: 34px; border: none;"><i class="bi bi-send fs-6"></i> Send Message</button>
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

    <!-- Leave Change Request Modal -->
    <div class="modal fade" id="changeRequestModal" tabindex="-1" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark border-0 shadow" style="overflow: hidden; background-color: #212529 !important; border: 1px solid rgba(255, 255, 255, 0.08) !important;">
                <div class="modal-header bg-dark border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
                    <h5 class="modal-title fw-bold text-white fs-6 mb-0">Request Leave Changes</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="changeRequestForm" method="POST">
                    @csrf
                    <div class="modal-body p-4 text-white">
                        <div id="change-error-alert" class="d-none mb-4 p-3 d-flex align-items-center gap-3" style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.35); border-radius: 12px;">
                            <i class="bi bi-exclamation-triangle-fill text-danger fs-4"></i>
                            <div class="text-white small opacity-75 fw-semibold" id="change-error-text"></div>
                        </div>

                        <div id="change-details-section">
                            <!-- Toggle: Date Change -->
                            <div class="d-flex justify-content-between align-items-center mb-3 px-3 py-2" 
                                style="background: rgba(255, 255, 255, 0.05); border-radius: 50px; border: 1px solid rgba(255, 255, 255, 0.1); width: 100%;">
                                <label class="form-check-label fw-semibold text-white mb-0 cursor-pointer" for="toggle_date_change" style="user-select: none; font-size: 0.85rem; line-height: 1;">
                                    Request Date Change
                                </label>
                                <input class="form-check-input bg-dark border-secondary cursor-pointer" 
                                    type="checkbox" 
                                    id="toggle_date_change" 
                                    value="1" 
                                    style="border-radius: 50%; width: 1.15rem; height: 1.15rem; margin: 0; transform: none;">
                            </div>

                            <!-- Date Picker Fields -->
                            <div class="row g-3 mb-4 mx-0" id="date_change_fields">
                                <div class="col-md-6 ps-0">
                                    <label class="form-label small opacity-50 ms-1">New Start Date</label>
                                    <input type="date" name="start_date" id="c_start_date" class="form-control text-white border-secondary" disabled
                                        style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;">
                                </div>
                                <div class="col-md-6 pe-0">
                                    <label class="form-label small opacity-50 ms-1">New End Date</label>
                                    <input type="date" name="end_date" id="c_end_date" class="form-control text-white border-secondary" disabled
                                        style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;">
                                </div>
                            </div>

                            <!-- Toggle: Leave Type Change -->
                            <div class="d-flex justify-content-between align-items-center mb-3 px-3 py-2" 
                                style="background: rgba(255, 255, 255, 0.05); border-radius: 50px; border: 1px solid rgba(255, 255, 255, 0.1); width: 100%;">
                                <label class="form-check-label fw-semibold text-white mb-0 cursor-pointer" for="toggle_type_change" style="user-select: none; font-size: 0.85rem; line-height: 1;">
                                    Request Leave Type Change
                                </label>
                                <input class="form-check-input bg-dark border-secondary cursor-pointer" 
                                    type="checkbox" 
                                    id="toggle_type_change" 
                                    value="1" 
                                    style="border-radius: 50%; width: 1.15rem; height: 1.15rem; margin: 0; transform: none;">
                            </div>
                            <!-- Leave Type Dropdown -->
                            <div class="mb-4 mx-0" id="type_change_fields">
                                <label class="form-label small opacity-50 ms-1">New Leave Type</label>
                                <select name="leave_type_id" id="c_leave_type_id" class="form-select text-white border-secondary" disabled
                                        style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;">
                                    @foreach($leaveTypes as $type)
                                        @if($type->slug !== 'absent')
                                            <option value="{{ $type->id }}" class="bg-dark text-white">{{ $type->name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>

                            <!-- Reason textarea (Always editable) -->
                            <div class="mb-3">
                                <label class="form-label small opacity-50">Reason for Request / Edit Reason</label>
                                <textarea name="reason" id="c_reason" class="form-control text-white border-secondary" rows="3"
                                          style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;" placeholder="Provide details of your change request..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-dark border-top border-secondary border-opacity-25 d-flex justify-content-right">
                        <button type="button" class="row-btn-red" data-bs-dismiss="modal" style="min-width: 160px;">Cancel</button>
                        <button type="submit" id="btnSubmitChange" class="row-btn-green" style="min-width: 160px;"><i class="bi bi-check-circle me-1"></i> Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Confirm Submit Change Request Modal -->
    <div class="modal fade" id="confirmSubmitChangeModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="false" style="z-index: 1070; background: rgba(0, 0, 0, 0.55); backdrop-filter: blur(2px);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="background-color: #0c0c0c !important; border: 1px solid rgba(255, 255, 255, 0.08) !important;">
                <div class="modal-body p-4 text-center">
                    <div class="mb-3 text-warning">
                        <i class="bi bi-exclamation-circle-fill" style="font-size: 3.5rem;"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-white">Confirm Change Request</h5>
                    <p class="text-white opacity-50 small mb-4">
                        Are you sure you want to submit this change request? It will be sent for approval.
                    </p>
                    <div class="d-flex justify-content-center gap-2 mt-4">
                        <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 145px;">Cancel</button>
                        <button type="button" class="row-btn-green" id="confirmChangeSubmitBtn" style="width: 175px;">Yes, Submit</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endpush

    @push('scripts')
        <script>
            window.selectFilterOption = function(event, inputId, value) {
                if (event) event.preventDefault();
                const input = document.getElementById(inputId);
                if (input) {
                    input.value = value;
                    document.getElementById('filterForm').submit();
                }
            };

            window.leaveCancelUrl = "{{ url('/admin/leave-requests') }}";
            let currentRequestId = null;
            const actionForm = document.getElementById('actionForm');

            window.openRequestModal = function(id) {
                currentRequestId = id;
                const dataDiv = document.getElementById('request-data-' + id);
                
                // Populate Details
                document.getElementById('m_type').textContent = dataDiv.querySelector('.d-type').textContent;
                document.getElementById('m_dates').textContent = dataDiv.querySelector('.d-dates').textContent;
                document.getElementById('m_status').textContent = dataDiv.querySelector('.d-status').textContent;
                document.getElementById('m_reason').textContent = dataDiv.querySelector('.d-reason').textContent;
                
                // Populate Comments
                const commentsContainer = document.getElementById('m_comments');
                commentsContainer.innerHTML = dataDiv.querySelector('.d-comments').innerHTML;
                
                // Scroll to bottom of comments
                setTimeout(() => {
                    commentsContainer.scrollTop = commentsContainer.scrollHeight;
                }, 100);

                // Set form action endpoint
                const formEl = (typeof actionForm !== 'undefined' && actionForm) ? actionForm : document.getElementById('actionForm');
                if (formEl) {
                    formEl.action = `/admin/leave-requests/${currentRequestId}/comment`;
                    formEl.reset();
                }

                // ── Render Approval Chain Timeline ──
                renderApprovalChain(dataDiv);
                toggleApprovalChainPanel(true); // Close panel on open by default

                // Check for Cancel Reason info
                const cancelReason = dataDiv.querySelector('.d-cancel-reason') ? dataDiv.querySelector('.d-cancel-reason').textContent.trim() : '';
                const cancelReasonContainer = document.getElementById('m_cancel_reason_container');
                const cancelReasonEl = document.getElementById('m_cancel_reason');
                if (cancelReason && cancelReasonContainer && cancelReasonEl) {
                    cancelReasonContainer.classList.remove('d-none');
                    cancelReasonEl.textContent = cancelReason;
                } else if (cancelReasonContainer) {
                    cancelReasonContainer.classList.add('d-none');
                    if (cancelReasonEl) cancelReasonEl.textContent = '';
                }

                // Check for Cancelled Alert Banner
                const cancelledAlert = document.getElementById('m_cancelled_alert');
                const cancelledAlertText = document.getElementById('m_cancelled_alert_text');
                const cancelBannerText = dataDiv.querySelector('.d-cancel-banner-text') ? dataDiv.querySelector('.d-cancel-banner-text').textContent.trim() : '';
                const reqStatus = dataDiv.querySelector('.d-status') ? dataDiv.querySelector('.d-status').textContent.trim().toLowerCase() : '';

                if (cancelBannerText && (reqStatus === 'cancelled' || dataDiv.querySelector('.d-chain-data')?.dataset.status === 'cancelled')) {
                    if (cancelledAlert) cancelledAlert.classList.remove('d-none');
                    if (cancelledAlertText) cancelledAlertText.innerHTML = cancelBannerText;
                } else if (cancelledAlert) {
                    cancelledAlert.classList.add('d-none');
                }

                // Populate and toggle Change Request details in Modal
                const hasChange = dataDiv.querySelector('.d-has-change') ? dataDiv.querySelector('.d-has-change').textContent.trim() : '0';
                const changeStatus = dataDiv.querySelector('.d-change-status') ? dataDiv.querySelector('.d-change-status').textContent.trim() : '';
                const mChangeBadge = document.getElementById('m_change_badge');
                const mComparisonSection = document.getElementById('m_comparison_section');
                
                if (hasChange === '1') {
                    if (mChangeBadge) {
                        mChangeBadge.style.setProperty('display', 'inline-flex', 'important');
                        mChangeBadge.classList.remove('d-none');
                        if (changeStatus === 'approved_by_manager') {
                            mChangeBadge.textContent = 'Changes Approved (Pending CEO)';
                        } else if (changeStatus === 'rejected_by_manager') {
                            mChangeBadge.textContent = 'Changes Rejected (Pending CEO)';
                        } else {
                            mChangeBadge.textContent = 'Changes Requested';
                        }
                    }
                    if (mComparisonSection) mComparisonSection.classList.remove('d-none');
                // Super Admin resolved checks
                const isSuperAdminResolved = dataDiv.querySelector('.d-super-admin-resolved')?.textContent?.trim() === 'true';

                    const isCancel = dataDiv.querySelector('.d-change-cancel').textContent.trim() === '1';
                    const changeCancelAlert = document.getElementById('m_change_cancel_alert');
                    const changeDetailsPane = document.getElementById('m_change_details_pane');

                    if (isCancel) {
                        if (mChangeBadge) {
                            mChangeBadge.style.setProperty('display', 'inline-block', 'important');
                            mChangeBadge.classList.remove('d-none');
                            mChangeBadge.textContent = 'pending cancellation';
                            mChangeBadge.className = 'small fst-italic text-decoration-underline mt-0.5';
                            mChangeBadge.style.color = '#a855f7';
                        }
                        if (changeCancelAlert) changeCancelAlert.classList.remove('d-none');
                        if (changeDetailsPane) changeDetailsPane.classList.add('d-none');
                    } else {
                        if (changeCancelAlert) changeCancelAlert.classList.add('d-none');
                        if (changeDetailsPane) changeDetailsPane.classList.remove('d-none');
                        const datesEl = document.getElementById('m_change_dates');
                        const typeEl = document.getElementById('m_change_type');
                        const reasonEl = document.getElementById('m_change_reason');
                        if (datesEl) datesEl.textContent = dataDiv.querySelector('.d-change-dates').textContent;
                        if (typeEl) typeEl.textContent = dataDiv.querySelector('.d-change-type').textContent;
                        if (reasonEl) reasonEl.textContent = dataDiv.querySelector('.d-change-reason').textContent;
                    }
                } else {
                    if (mChangeBadge) {
                        mChangeBadge.style.setProperty('display', 'none', 'important');
                        mChangeBadge.classList.add('d-none');
                    }
                    if (mComparisonSection) mComparisonSection.classList.add('d-none');
                }

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

                const isCancelPending = dataDiv.querySelector('.d-change-cancel')?.textContent.trim() === '1' && dataDiv.querySelector('.d-change-status')?.textContent.trim() === 'pending';

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

            let origStartDate = '';
            let origEndDate = '';
            let origLeaveTypeId = '';
            let origReason = '';

            window.openChangeRequestModal = function(btn) {
                const id = btn.getAttribute('data-id');
                currentLeaveRequestId = id;
                const startDate = btn.getAttribute('data-start');
                const endDate = btn.getAttribute('data-end');
                const typeId = btn.getAttribute('data-type');
                const status = btn.getAttribute('data-status');
                const reason = btn.getAttribute('data-reason');

                origStartDate = startDate;
                origEndDate = endDate;
                origLeaveTypeId = typeId;
                origReason = reason || '';

                const form = document.getElementById('changeRequestForm');
                form.action = `/admin/leave-requests/${id}/change-request`;

                // Reset modal state
                document.getElementById('change-error-alert').classList.add('d-none');
                document.getElementById('change-error-text').textContent = '';

                // Populate values
                document.getElementById('c_start_date').value = startDate;
                document.getElementById('c_end_date').value = endDate;
                document.getElementById('c_leave_type_id').value = typeId;
                document.getElementById('c_reason').value = reason;

                // Reset toggles
                document.getElementById('toggle_date_change').checked = false;
                document.getElementById('toggle_type_change').checked = false;
                document.getElementById('c_start_date').disabled = true;
                document.getElementById('c_end_date').disabled = true;
                document.getElementById('c_leave_type_id').disabled = true;

                // Ensure modal is at body level to prevent stacking context or overflow bugs
                const modalEl = document.getElementById('changeRequestModal');
                if (modalEl && modalEl.parentElement !== document.body) {
                    document.body.appendChild(modalEl);
                }

                // Show modal
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            };

            // Checkbox Toggles Logic
            document.getElementById('toggle_date_change').addEventListener('change', function() {
                document.getElementById('c_start_date').disabled = !this.checked;
                document.getElementById('c_end_date').disabled = !this.checked;
            });

            document.getElementById('toggle_type_change').addEventListener('change', function() {
                document.getElementById('c_leave_type_id').disabled = !this.checked;
            });

            let currentLeaveRequestId = null;
            let pendingChangeFormData = null;
            let pendingChangeFormAction = null;

            document.getElementById('changeRequestForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const form = this;

                const dateChange = document.getElementById('toggle_date_change').checked;
                const typeChange = document.getElementById('toggle_type_change').checked;

                const newReason = document.getElementById('c_reason').value.trim();
                const reasonChanged = newReason !== origReason.trim();

                if (!dateChange && !typeChange && !reasonChanged) {
                    document.getElementById('change-error-text').textContent = 'Please select at least one change to request (Date or Leave Type) or modify the reason.';
                    document.getElementById('change-error-alert').classList.remove('d-none');
                    return;
                }

                let hasRealChanges = false;
                if (dateChange) {
                    const newStart = document.getElementById('c_start_date').value;
                    const newEnd = document.getElementById('c_end_date').value;
                    if (newStart !== origStartDate || newEnd !== origEndDate) {
                        hasRealChanges = true;
                    }
                }
                if (typeChange) {
                    const newType = document.getElementById('c_leave_type_id').value;
                    if (String(newType) !== String(origLeaveTypeId)) {
                        hasRealChanges = true;
                    }
                }
                if (newReason !== origReason.trim()) {
                    hasRealChanges = true;
                }

                if (!hasRealChanges) {
                    document.getElementById('change-error-text').textContent = 'You checked the change options but did not make any edits to the selected fields.';
                    document.getElementById('change-error-alert').classList.remove('d-none');
                    return;
                }

                document.getElementById('change-error-alert').classList.add('d-none');

                // Build Form Data manually
                pendingChangeFormData = new FormData();
                pendingChangeFormData.append('_token', form.querySelector('input[name="_token"]').value);

                if (dateChange) {
                    pendingChangeFormData.append('start_date', document.getElementById('c_start_date').value);
                    pendingChangeFormData.append('end_date', document.getElementById('c_end_date').value);
                }
                if (typeChange) {
                    pendingChangeFormData.append('leave_type_id', document.getElementById('c_leave_type_id').value);
                }
                pendingChangeFormData.append('reason', document.getElementById('c_reason').value);

                pendingChangeFormAction = form.action;

                const confirmModalEl = document.getElementById('confirmSubmitChangeModal');
                
                // Ensure confirm modal is at body level
                if (confirmModalEl.parentElement !== document.body) {
                    document.body.appendChild(confirmModalEl);
                }
                
                const confirmModal = bootstrap.Modal.getOrCreateInstance(confirmModalEl);
                confirmModal.show();
            });

            document.getElementById('confirmChangeSubmitBtn').addEventListener('click', function() {
                const btn = this;
                const originalText = btn.innerHTML;
                
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Submitting...';

                fetch(pendingChangeFormAction, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: pendingChangeFormData
                })
                .then(response => response.json().then(data => ({ status: response.status, data })))
                .then(({ status, data }) => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;

                    if (status === 200 && data.success) {
                        const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmSubmitChangeModal'));
                        confirmModal.hide();
                        
                        const mainModal = bootstrap.Modal.getInstance(document.getElementById('changeRequestModal'));
                        if (mainModal) mainModal.hide();
                        
                        window.showToast(data.message || 'Change request submitted successfully.', 'success');
                        
                        // Dynamically update the row without reload
                        const row = document.getElementById(`leave-row-${currentLeaveRequestId}`);
                        if (row) {
                            const editBtn = row.querySelector('.btn-circle-gray');
                            if (editBtn) editBtn.remove();
                            
                            const statusDiv = row.querySelector('td:nth-child(5) > div');
                            if (statusDiv) {
                                const existingPurple = row.querySelector('.row-badge-purple');
                                if (existingPurple) {
                                    const mt1 = existingPurple.closest('.mt-1');
                                    if (mt1) mt1.remove();
                                }
                                
                                const newBadgeDiv = document.createElement('div');
                                newBadgeDiv.className = 'mt-1';
                                newBadgeDiv.innerHTML = `
                                    <span class="row-badge-purple">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Pending Changes
                                    </span>
                                `;
                                statusDiv.parentNode.appendChild(newBadgeDiv);
                            }
                        }
                    } else {
                        const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmSubmitChangeModal'));
                        confirmModal.hide();

                        if (data.message && (data.message.toLowerCase().includes('balance') || data.message.toLowerCase().includes('insufficient'))) {
                            document.getElementById('change-error-text').innerHTML = `${data.message}<br><div class="mt-2"><a href="/dashboard" class="text-info text-decoration-underline fw-semibold small"><i class="bi bi-arrow-left-short"></i> View your current balance</a></div>`;
                        } else {
                            document.getElementById('change-error-text').textContent = data.message || 'An error occurred. Please try again.';
                        }
                        document.getElementById('change-error-alert').classList.remove('d-none');
                    }
                })
                .catch(error => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    
                    const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmSubmitChangeModal'));
                    confirmModal.hide();

                    document.getElementById('change-error-text').textContent = 'A system error occurred. Please check your inputs.';
                    document.getElementById('change-error-alert').classList.remove('d-none');
                    console.error(error);
                });
            });
        </script>
        <script src="{{ asset('js/leave-request.js') }}?v={{ time() }}"></script>
    @endpush
</x-app-layout>

