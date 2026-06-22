<x-app-layout>
    <x-slot name="header">All Leave Requests</x-slot>
    
    <div class="spotify-card mb-4">
        <div class="card-header-area px-4 pt-4 d-none">
            <h6 class="text-uppercase text-white fw-semibold small mb-2 opacity-75" style="letter-spacing: 0.05em;">Filters</h6>
            <div class="grid-separator"></div>
        </div>
        <div class="p-4 pt-4">
            <form class="row g-3 align-items-center" method="GET" id="filterForm">
                <div class="col-md-2">
                    <label class="form-label text-white opacity-75 small mb-1">Status Filter</label>
                    <select name="status" class="form-select bg-dark border-secondary text-white" onchange="document.getElementById('filterForm').submit()">
                        <option value="all" @selected(!request()->has('status') || request('status') === 'all')>All Leaves</option>
                        <option value="" @selected(request()->has('status') && request('status') === '')>All Pending Leaves</option>
                        @foreach([
                            'pending' => 'Manager Approval Pending',
                            'approved_by_manager' => 'Manager Approved (CEO Pending)',
                            'rejected_by_manager' => 'Rejected by Line Manager',
                            'approved' => 'Fully Approved (by CEO)',
                            'rejected' => 'Rejected (by CEO)',
                            'cancelled' => 'Cancelled'
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status')==$value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-white opacity-75 small mb-1">Department Filter</label>
                    <select name="department_id" class="form-select bg-dark border-secondary text-white" onchange="document.getElementById('filterForm').submit()">
                        <option value="">All departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" @selected(request('department_id')==$dept->id)>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-white opacity-75 small mb-1">Employee Filter</label>
                    <select name="user_id" class="form-select bg-dark border-secondary text-white" onchange="document.getElementById('filterForm').submit()">
                        <option value="">All employees</option>
                        @foreach($allUsers as $u)
                            <option value="{{ $u->id }}" @selected(request('user_id')==$u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-white opacity-75 small mb-1">Leave Type Filter</label>
                    <select name="leave_type_id" class="form-select bg-dark border-secondary text-white" onchange="document.getElementById('filterForm').submit()">
                        <option value="">All types</option>
                        @foreach($leaveTypes as $type)
                            <option value="{{ $type->id }}" @selected(request('leave_type_id')==$type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="w-100">
                        <label class="form-label d-block small">&nbsp;</label>
                        <a href="{{ route('super-admin.leave-requests.index') }}" class="row-btn w-100 text-decoration-none justify-content-center" style="height: 37px !important;"><i class="bi bi-x-circle me-1"></i> Clear Filters</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <div class="spotify-card mb-4">
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
                        <tr>
                            <td class="ps-4">
                                <span class="fw-semibold text-white">{{ $request->user->name }}</span>
                            </td>
                            <td><span class="text-white opacity-75">{{ $request->user->department?->name ?? 'N/A' }}</span></td>
                            <td><span class="text-white opacity-75">{{ $request->leaveType->name }}</span></td>
                            <td><span class="text-white opacity-75">{{ $request->start_date->format('M d, Y') }} – {{ $request->end_date->format('M d, Y') }}</span></td>
                            <td><span class="text-white opacity-75">{{ $request->created_at->format('M d, Y') }}</span></td>
                            <td><span class="row-badge" style="background: rgba(255, 255, 255, 0.1); color: rgba(255, 255, 255, 0.75); border: transparent;">{{ $request->days_count }}</span></td>
                            <td>
                                @if($request->status === 'approved_by_manager')
                                    <span class="row-badge-blue">
                                        <i class="bi bi-clock-history me-1"></i> Manager Approved (Pending CEO)
                                    </span>
                                @elseif($request->status === 'approved')
                                    <span class="row-badge-green">
                                        <i class="bi bi-check-circle-fill me-1"></i> Fully Approved
                                    </span>
                                @elseif($request->status === 'rejected')
                                    <span class="row-badge-red">
                                        <i class="bi bi-x-circle-fill me-1"></i> Rejected
                                    </span>
                                @elseif($request->status === 'rejected_by_manager')
                                    <span class="row-badge-red">
                                        <i class="bi bi-x-circle-fill me-1"></i> Rejected by Line Manager
                                    </span>
                                @elseif($request->status === 'cancelled')
                                    <span class="row-badge-gray"><i class="bi bi-x-circle me-1"></i> Cancelled</span>
                                @else
                                    <span class="row-badge-warning">
                                        <i class="bi bi-clock-fill me-1"></i> Pending Manager Approval
                                    </span>
                                @endif

                                @if($request->hasPendingChangeRequest())
                                    <div class="mt-1">
                                        <span class="row-badge-purple">
                                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Pending Changes
                                        </span>
                                    </div>
                                @elseif($request->hasApprovedManagerChangeRequest())
                                    <div class="mt-1">
                                        <span class="row-badge-purple" style="background: rgba(139, 92, 246, 0.12) !important; border: 1px solid rgba(139, 92, 246, 0.25) !important;">
                                            <i class="bi bi-clock-history me-1"></i> Changes Approved (Pending CEO Approval)
                                        </span>
                                    </div>
                                @elseif($request->hasRejectedManagerChangeRequest())
                                    <div class="mt-1">
                                        <span class="row-badge-purple" style="background: rgba(139, 92, 246, 0.12) !important; border: 1px solid rgba(139, 92, 246, 0.25) !important;">
                                            <i class="bi bi-clock-history me-1"></i> Changes Rejected (Pending CEO Approval)
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex align-items-center gap-2">
                                    @if($request->comments->count() > 0)
                                        <span class="badge" style="background: rgba(13, 110, 253, 0.1); color: #3b82f6; border: 1px solid rgba(13, 110, 253, 0.2);" title="Has conversation">
                                            <i class="bi bi-chat-text-fill"></i> {{ $request->comments->count() }}
                                        </span>
                                    @endif
                                    <button type="button" class="row-btn-outline-blue" onclick="openRequestModal({{ $request->id }})">View / Action</button>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Hidden Data for Modal -->
                        <div id="request-data-{{ $request->id }}" class="d-none">
                            <span class="d-name">{{ $request->user->name }}</span>
                            <span class="d-dept">{{ $request->user->department?->name ?? 'N/A' }}</span>
                            <span class="d-type">{{ $request->leaveType->name }}</span>
                            <span class="d-dates">{{ $request->start_date->format('M d, Y') }} – {{ $request->end_date->format('M d, Y') }} ({{ $request->days_count }} days)</span>
                            <span class="d-reason">{{ $request->reason }}</span>
                            <span class="d-status">{{ $request->status }}</span>
                            <span class="d-has-change">{{ $request->hasOutstandingChangeRequest() ? '1' : '0' }}</span>
                            <span class="d-change-status">{{ $request->change_request_status }}</span>
                            @if($request->hasOutstandingChangeRequest())
                                <span class="d-change-cancel">{{ !empty($request->change_request['request_cancellation']) ? '1' : '0' }}</span>
                                <span class="d-change-dates">
                                    @if(!empty($request->change_request['start_date']) || !empty($request->change_request['end_date']))
                                        {{ \Carbon\Carbon::parse($request->change_request['start_date'] ?? $request->start_date)->format('M d, Y') }} – {{ \Carbon\Carbon::parse($request->change_request['end_date'] ?? $request->end_date)->format('M d, Y') }}
                                    @else
                                        N/A
                                    @endif
                                </span>
                                <span class="d-change-type">
                                    @if(!empty($request->change_request['leave_type_id']))
                                        {{ \App\Models\LeaveType::find($request->change_request['leave_type_id'])?->name ?? 'N/A' }}
                                    @else
                                        N/A
                                    @endif
                                </span>
                                <span class="d-change-reason">{{ $request->change_request['reason'] ?? 'N/A' }}</span>
                            @endif
                            <div class="d-comments">
                                @forelse($request->comments->sortBy('created_at') as $comment)
                                    @php
                                        $isEmp = $comment->user_id === $request->user_id;
                                        $isSA = $comment->user->isSuperAdmin();
                                        $isLM = $comment->user->isAdmin();
                                        
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
                                    <div class="mb-3 p-3 rounded border {{ $bgColor }}">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <div>
                                                <strong class="small">{{ $comment->user->name }}</strong>
                                                @if($badgeText)
                                                    <span class="{{ $badgeClass }} ms-1" style="font-size: 0.7rem;">{{ $badgeText }}</span>
                                                @endif
                                            </div>
                                            <small class="text-white opacity-50" style="font-size: 0.75rem;">{{ $comment->created_at->diffForHumans() }}</small>
                                        </div>
                                        <p class="mb-0 small mt-1">{{ $comment->comment }}</p>
                                    </div>
                                @empty
                                    <p class="text-white opacity-50 small mb-0 text-center fst-italic py-3">No comments yet. Start a conversation!</p>
                                @endforelse
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-white opacity-50">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                No leave requests found matching the criteria.
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
    <!-- View / Action Modal -->
    <div class="modal fade" id="requestModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-dark border-0 shadow" style="overflow: hidden; background-color: #212529 !important;">
                <div class="modal-header bg-dark border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="modal-title fw-bold text-white fs-6 mb-0">Leave Request Details</h5>
                        <span id="m_change_badge" class="row-badge-purple d-none" style="font-size: 0.78rem !important; padding: 0 0.9rem !important; height: 30px !important;">Changes Requested</span>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="row g-0">
                        <!-- Left Side: Details -->
                        <div class="col-md-5 bg-dark border-end border-secondary border-opacity-25 p-4">
                            <div class="mb-3">
                                <h6 class="text-uppercase text-white opacity-50 fw-bold small mb-0"><i class="bi bi-info-circle me-1"></i> Request Info</h6>
                            </div>
                            <div class="mb-3">
                                <label class="small text-white opacity-50 mb-0">Employee</label>
                                <div class="fw-medium text-white" id="m_name"></div>
                                <div class="small text-white opacity-50" id="m_dept"></div>
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
                                <div class="bg-dark p-3 rounded shadow-sm small mt-1 border border-secondary text-white" id="m_reason" style="min-height: 80px;"></div>
                            </div>

                            <!-- Comparison Section (visible when change requested) -->
                            <div id="m_comparison_section" class="d-none mt-3 p-3 rounded" style="background: rgba(255, 193, 7, 0.05); border: 1px solid rgba(255, 193, 7, 0.15);">
                                <h6 class="text-warning small fw-bold mb-3"><i class="bi bi-arrow-left-right me-1"></i> Requested Changes</h6>
                                
                                <div id="m_change_cancel_alert" class="alert alert-danger py-2 px-3 small d-none">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> <strong>Cancellation Requested!</strong> The employee has requested to cancel this approved leave.
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
                        
                        <!-- Right Side: Comments & Actions -->
                        <div class="col-md-7 p-4 d-flex flex-column h-100 bg-dark">
                            <h6 class="text-uppercase text-white opacity-50 fw-bold small mb-3"><i class="bi bi-chat-dots me-1"></i> Conversation & Actions</h6>
                            
                            <!-- Comments Area -->
                            <div class="flex-grow-1 overflow-auto mb-3 pe-2 custom-scrollbar" id="m_comments" style="max-height: 250px;">
                                <!-- Comments appended here -->
                            </div>
                            
                            <!-- Action Form -->
                            <form method="POST" id="actionForm" class="mt-auto">
                                @csrf
                                <div id="modal-alert-container"></div>
                                <div class="mb-3">
                                    <label class="form-label text-white opacity-75 small fw-semibold">Add a Comment (Visible to Employee)</label>
                                    <textarea name="comment" class="form-control bg-dark text-white border-secondary" rows="2" placeholder="Type your message or reasoning to the employee..."></textarea>
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

            window.openRequestModal = function(id) {
                currentRequestId = id;
                const dataDiv = document.getElementById('request-data-' + id);
                
                // Populate Details
                document.getElementById('m_name').textContent = dataDiv.querySelector('.d-name').textContent;
                document.getElementById('m_dept').textContent = dataDiv.querySelector('.d-dept').textContent;
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
                
                // Toggle action buttons based on status
                const status = dataDiv.querySelector('.d-status').textContent.trim();
                const hasChange = dataDiv.querySelector('.d-has-change') ? dataDiv.querySelector('.d-has-change').textContent.trim() : '0';
                const changeStatus = dataDiv.querySelector('.d-change-status') ? dataDiv.querySelector('.d-change-status').textContent.trim() : '';
                const mChangeBadge = document.getElementById('m_change_badge');
                const mComparisonSection = document.getElementById('m_comparison_section');
                const mActionButtons = document.getElementById('m_action_buttons');
                const mChangeButtons = document.getElementById('m_change_buttons');
                
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
                    mComparisonSection.classList.remove('d-none');
                    mActionButtons.classList.add('d-none');
                    mChangeButtons.classList.remove('d-none');

                    const isCancel = dataDiv.querySelector('.d-change-cancel').textContent.trim() === '1';
                    const changeCancelAlert = document.getElementById('m_change_cancel_alert');
                    const changeDetailsPane = document.getElementById('m_change_details_pane');

                    if (isCancel) {
                        changeCancelAlert.classList.remove('d-none');
                        changeDetailsPane.classList.add('d-none');
                    } else {
                        changeCancelAlert.classList.add('d-none');
                        changeDetailsPane.classList.remove('d-none');
                        document.getElementById('m_change_dates').textContent = dataDiv.querySelector('.d-change-dates').textContent;
                        document.getElementById('m_change_type').textContent = dataDiv.querySelector('.d-change-type').textContent;
                        document.getElementById('m_change_reason').textContent = dataDiv.querySelector('.d-change-reason').textContent;
                    }
                } else {
                    if (mChangeBadge) {
                        mChangeBadge.style.setProperty('display', 'none', 'important');
                        mChangeBadge.classList.add('d-none');
                    }
                    mComparisonSection.classList.add('d-none');
                    mChangeButtons.classList.add('d-none');

                    if (status === 'cancelled') {
                        mActionButtons.classList.add('d-none');
                    } else {
                        mActionButtons.classList.remove('d-none');
                        const btnApprove = document.getElementById('btn-approve');
                        const btnReject = document.getElementById('btn-reject');
                        
                        btnApprove.classList.toggle('d-none', status === 'approved');
                        btnReject.classList.toggle('d-none', status === 'rejected');
                    }
                }

                // Clear inline alerts
                const alertContainer = document.getElementById('modal-alert-container');
                if (alertContainer) alertContainer.innerHTML = '';

                // Reset form
                actionForm.reset();

                // Show modal
                const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('requestModal'));
                modal.show();
            };

            window.submitAction = function(actionType) {
                if (!currentRequestId) return;
                
                // Set the correct endpoint based on action
                actionForm.action = `/super-admin/leave-requests/${currentRequestId}/${actionType}`;
                
                // Reset inline alerts
                const alertContainer = document.getElementById('modal-alert-container');
                if (alertContainer) alertContainer.innerHTML = '';

                // Check if comment is required for "Just Comment"
                if (actionType === 'comment') {
                    const commentVal = actionForm.querySelector('textarea[name="comment"]').value.trim();
                    if (!commentVal) {
                        if (alertContainer) {
                            alertContainer.innerHTML = '<div class="alert alert-danger py-2 px-3 small mb-3"><i class="bi bi-exclamation-circle-fill me-1"></i> Please enter a comment before submitting.</div>';
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
                        confirmText.textContent = 'Are you sure you want to approve this leave request? This will authorize the employee leave.';
                        btnConfirmAction.className = 'row-btn-green';
                        btnConfirmAction.style = 'width: 145px;';
                        btnConfirmAction.textContent = 'Yes, Approve';
                    } else if (actionType === 'reject') {
                        confirmTitle.textContent = 'Reject Leave Request?';
                        confirmText.textContent = 'Are you sure you want to reject this leave request? This will decline the employee request.';
                        btnConfirmAction.className = 'row-btn-red';
                        btnConfirmAction.style = 'width: 145px;';
                        btnConfirmAction.textContent = 'Yes, Reject';
                    } else if (actionType === 'approve-change') {
                        confirmTitle.textContent = 'Approve Requested Changes?';
                        confirmText.textContent = 'Are you sure you want to approve these changes? This will override the original leave request.';
                        btnConfirmAction.className = 'row-btn-green';
                        btnConfirmAction.style = 'width: 145px;';
                        btnConfirmAction.textContent = 'Approve Changes';
                    } else if (actionType === 'reject-change') {
                        confirmTitle.textContent = 'Reject Requested Changes?';
                        confirmText.textContent = 'Are you sure you want to reject these changes? The original approved leave will remain unchanged.';
                        btnConfirmAction.className = 'row-btn-red';
                        btnConfirmAction.style = 'width: 145px;';
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
        </script>
    @endpush
</x-app-layout>
