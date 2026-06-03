<x-app-layout>
    <x-slot name="header">Leave History</x-slot>
    
    <div class="spotify-card mb-4">
        <div class="card-header-area px-4 pt-4 d-none">
            <h6 class="text-uppercase text-white fw-semibold small mb-2 opacity-75" style="letter-spacing: 0.05em;">Filters</h6>
            <div class="grid-separator"></div>
        </div>
        <div class="p-4 pt-4">
            <form class="row g-3 align-items-center" method="GET" id="filterForm">
                <div class="col-md-2">
                    <label class="form-label text-white opacity-50 small mb-1">Status Filter</label>
                    <select name="status" class="form-select border-0 shadow-sm" onchange="document.getElementById('filterForm').submit()"
                            style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important;">
                        <option value="" class="bg-dark text-white">All History statuses</option>
                        @foreach([
                            'approved' => 'Fully Approved (by CEO)',
                            'rejected' => 'Rejected (by CEO)',
                            'rejected_by_manager' => 'Rejected by Line Manager',
                            'approved_by_manager' => 'Manager Approved (CEO Pending)',
                            'cancelled' => 'Cancelled'
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status')==$value) class="bg-dark text-white">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-white opacity-50 small mb-1">Department Filter</label>
                    <select name="department_id" class="form-select border-0 shadow-sm" onchange="document.getElementById('filterForm').submit()"
                            style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important;">
                        <option value="" class="bg-dark text-white">All departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" @selected(request('department_id')==$dept->id) class="bg-dark text-white">{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-white opacity-50 small mb-1">Employee Filter</label>
                    <select name="user_id" class="form-select border-0 shadow-sm" onchange="document.getElementById('filterForm').submit()"
                            style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important;">
                        <option value="" class="bg-dark text-white">All employees</option>
                        @foreach($allUsers as $u)
                            <option value="{{ $u->id }}" @selected(request('user_id')==$u->id) class="bg-dark text-white">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-white opacity-50 small mb-1">Leave Type Filter</label>
                    <select name="leave_type_id" class="form-select border-0 shadow-sm" onchange="document.getElementById('filterForm').submit()"
                            style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important;">
                        <option value="" class="bg-dark text-white">All types</option>
                        @foreach($leaveTypes as $type)
                            <option value="{{ $type->id }}" @selected(request('leave_type_id')==$type->id) class="bg-dark text-white">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="w-100">
                        <label class="form-label d-block small">&nbsp;</label>
                        <a href="{{ route('super-admin.leave-requests.history') }}" class="row-btn w-100 text-decoration-none justify-content-center" style="height: 37px !important;"><i class="bi bi-x-circle me-1"></i> Clear Filters</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if($user)
        <!-- Leave Balances Row -->
        <div class="row g-4 mb-4">
            @foreach($balances as $balance)
                <div class="col-md-3">
                    <div class="spotify-card h-100">
                        <div class="card-header-area px-4 pt-4">
                            <h6 class="text-uppercase text-white fw-bold small mb-2 opacity-75" style="letter-spacing: 0.05em;">{{ $balance->leaveType->name }}</h6>
                            <div class="grid-separator"></div>
                        </div>
                        <div class="p-4 pt-2">
                            <div class="d-flex align-items-baseline mb-3">
                                <span class="fs-3 fw-bold text-white">{{ $balance->entitled }}</span>
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
                                    <strong class="d-block text-success">{{ max(0, $balance->entitled + $balance->carried_forward - $balance->used) }}</strong> Left
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="spotify-card mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        @if(!$user)
                            <th class="ps-4 text-white opacity-50">Employee</th>
                        @endif
                        <th class="{{ $user ? 'ps-4' : '' }} text-white opacity-50">Type</th>
                        <th class="text-white opacity-50">Dates</th>
                        <th class="text-white opacity-50">Entry On</th>
                        <th class="text-white opacity-50">Days</th>
                        <th class="text-white opacity-50">Status</th>
                        <th class="text-white opacity-50">Reviewed By</th>
                        <th class="text-end pe-4 text-white opacity-50">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($history as $req)
                        <tr class="cursor-pointer hover-highlight">
                            @if(!$user)
                                <td class="ps-4 fw-semibold text-white">{{ $req->user->name }}</td>
                            @endif
                            <td class="{{ $user ? 'ps-4' : '' }} fw-semibold text-white">{{ $req->leaveType->name }}</td>
                            <td><span class="text-white opacity-75">{{ $req->start_date->format('M d, Y') }} – {{ $req->end_date->format('M d, Y') }}</span></td>
                            <td><span class="text-white opacity-75">{{ $req->created_at->format('M d, Y') }}</span></td>
                            <td><span class="row-badge" style="background: rgba(255, 255, 255, 0.08); color: rgba(255, 255, 255, 0.85); border: transparent;">{{ $req->days_count }}</span></td>
                            <td>
                                @if($req->status === 'approved')
                                    <span class="row-badge-green">Fully Approved</span>
                                @elseif($req->status === 'rejected')
                                    <span class="row-badge-red">Rejected</span>
                                @elseif($req->status === 'rejected_by_manager')
                                    <span class="row-badge-red">Rejected by Line Manager</span>
                                @elseif($req->status === 'cancelled')
                                    <span class="row-badge-gray"><i class="bi bi-x-circle me-1"></i> Cancelled</span>
                                @else
                                    <span class="row-badge">{{ ucfirst($req->status) }}</span>
                                @endif
                            </td>
                            <td><span class="text-white opacity-75">{{ $req->reviewer?->name ?? '—' }}</span></td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex align-items-center gap-2">
                                    @if($req->comments->count() > 0)
                                        <span class="row-badge-blue" title="Has conversation">
                                            <i class="bi bi-chat-text-fill"></i> {{ $req->comments->count() }}
                                        </span>
                                    @endif
                                    <button type="button" class="row-btn-outline-blue btn-sm px-3" onclick="openRequestModal({{ $req->id }})"><i class="bi bi-info-circle me-1"></i> Info</button>
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Hidden Data for Modal -->
                        <div id="request-data-{{ $req->id }}" class="d-none">
                            <span class="d-name">{{ $req->user->name }}</span>
                            <span class="d-dept">{{ $req->user->department?->name ?? 'N/A' }}</span>
                            <span class="d-type">{{ $req->leaveType->name }}</span>
                            <span class="d-dates">{{ $req->start_date->format('M d, Y') }} – {{ $req->end_date->format('M d, Y') }} ({{ $req->days_count }} days)</span>
                            <span class="d-reason">{{ $req->reason }}</span>
                            <span class="d-status">{{ $req->status }}</span>
                            <div class="d-comments">
                                @forelse($req->comments->sortBy('created_at') as $comment)
                                    @php
                                        $isEmp = $comment->user_id === $req->user_id;
                                        $isSA = $comment->user->isSuperAdmin();
                                        $isLM = $comment->user->isAdmin();
                                        
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
                                    <div class="mb-3 p-3 rounded border {{ $bgColor }}">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <div>
                                                <strong class="small text-white">{{ $comment->user->name }}</strong>
                                                @if($badgeText)
                                                    <span class="{{ $badgeClass }} ms-1" style="font-size: 0.7rem;">{{ $badgeText }}</span>
                                                @endif
                                            </div>
                                            <small class="text-white opacity-50" style="font-size: 0.75rem;">{{ $comment->created_at->diffForHumans() }}</small>
                                        </div>
                                        <p class="mb-0 small mt-1 text-white opacity-75">{{ $comment->comment }}</p>
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
                                No leave history found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($history->hasPages())
            <div class="px-4 py-3 border-top border-secondary border-opacity-25">{{ $history->links() }}</div>
        @endif
    </div>

    @push('modals')

    <!-- View / Action Modal -->
    <div class="modal fade" id="requestModal" tabindex="-1" style="z-index: 1060;">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-dark border-0 shadow" style="overflow: hidden; background-color: #212529 !important;">
                <div class="modal-header bg-dark border-bottom border-secondary border-opacity-25">
                    <h5 class="modal-title fw-bold text-white fs-6">Leave Request Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="row g-0">
                        <!-- Left Side: Details -->
                        <div class="col-md-5 bg-dark border-end border-secondary border-opacity-25 p-4">
                            <h6 class="text-uppercase text-white opacity-50 fw-bold small mb-3"><i class="bi bi-info-circle me-1"></i> Request Info</h6>
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
                                <div class="bg-dark p-3 rounded shadow-sm small mt-1 border border-secondary text-white" id="m_reason" style="min-height: 80px; background-color: rgba(255,255,255,0.02);"></div>
                            </div>
                        </div>
                        
                        <!-- Right Side: Comments & Actions -->
                        <div class="col-md-7 p-4 d-flex flex-column h-100 bg-dark">
                            <h6 class="text-uppercase text-white opacity-50 fw-bold small mb-3"><i class="bi bi-chat-dots me-1"></i> Conversation</h6>
                            
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
                                    <textarea name="comment" class="form-control text-white" rows="2" placeholder="Type your message or reasoning to the employee..."
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
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Beautiful Custom Confirm Modal -->
    <div class="modal fade" id="confirmActionModal" tabindex="-1" style="z-index: 1060;">
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
                const mActionButtons = document.getElementById('m_action_buttons');
                
                if (status === 'cancelled') {
                    mActionButtons.classList.add('d-none');
                } else {
                    mActionButtons.classList.remove('d-none');
                    const btnApprove = document.getElementById('btn-approve');
                    const btnReject = document.getElementById('btn-reject');
                    
                    btnApprove.classList.toggle('d-none', status === 'approved');
                    btnReject.classList.toggle('d-none', status === 'rejected');
                }

                // Reset inline alerts
                const alertContainer = document.getElementById('modal-alert-container');
                if (alertContainer) alertContainer.innerHTML = '';

                // Reset form
                actionForm.reset();

                // Ensure modal is at body level to prevent stacking context or overflow bugs
                const modalEl = document.getElementById('requestModal');
                if (modalEl && modalEl.parentElement !== document.body) {
                    document.body.appendChild(modalEl);
                }

                // Show modal
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
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
                    // For approve/reject, use beautiful custom confirmation modal
                    pendingActionType = actionType;
                    const confirmTitle = document.getElementById('confirmTitle');
                    const confirmText = document.getElementById('confirmText');
                    const btnConfirmAction = document.getElementById('btnConfirmAction');

                    if (actionType === 'approve') {
                        confirmTitle.textContent = 'Approve Leave Request?';
                        confirmText.textContent = 'Are you sure you want to approve this leave request? This will authorize and finalize the leave.';
                        btnConfirmAction.className = 'row-btn-green';
                        btnConfirmAction.style = 'width: 145px;';
                        btnConfirmAction.textContent = 'Yes, Approve';
                    } else if (actionType === 'reject') {
                        confirmTitle.textContent = 'Reject Leave Request?';
                        confirmText.textContent = 'Are you sure you want to reject this leave request? This will decline the request.';
                        btnConfirmAction.className = 'row-btn-red';
                        btnConfirmAction.style = 'width: 145px;';
                        btnConfirmAction.textContent = 'Yes, Reject';
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
