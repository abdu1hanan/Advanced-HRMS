<x-app-layout>
    <x-slot name="header">Team Leave Requests</x-slot>
    
    <form class="row g-3 mb-4 align-items-center" method="GET" id="filterForm">
        <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Department Filter</label>
            <select name="department_id" class="form-select border-0 shadow-sm" onchange="document.getElementById('filterForm').submit()">
                <option value="">All departments</option>
                @foreach($departments as $dept)
                    <option value="{{ $dept->id }}" @selected(request('department_id')==$dept->id)>{{ $dept->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Employee Filter</label>
            <select name="user_id" class="form-select border-0 shadow-sm" onchange="document.getElementById('filterForm').submit()">
                <option value="">All employees</option>
                @foreach($allUsers as $u)
                    <option value="{{ $u->id }}" @selected(request('user_id')==$u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6 text-end mt-auto">
            <a href="{{ route('admin.team.leaves.history') }}" class="btn btn-outline-secondary shadow-sm"><i class="bi bi-clock-history me-1"></i> Team History</a>
        </div>
    </form>
    
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Employee</th>
                        <th>Department</th>
                        <th>Type</th>
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $request)
                        <tr class="cursor-pointer">
                            <td class="ps-4 fw-medium">{{ $request->user->name }}</td>
                            <td>{{ $request->user->department?->name ?? 'N/A' }}</td>
                            <td>{{ $request->leaveType->name }}</td>
                            <td>{{ $request->start_date->format('M d, Y') }} – {{ $request->end_date->format('M d, Y') }}</td>
                            <td>{{ $request->days_count }}</td>
                            <td>
                                @if($request->status === 'approved_by_manager')
                                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">
                                        <i class="bi bi-clock-history me-1"></i> Approved (Pending CEO)
                                    </span>
                                @elseif($request->status === 'approved')
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle-fill me-1"></i> Fully Approved
                                    </span>
                                @elseif($request->status === 'rejected')
                                    <span class="badge bg-danger">
                                        <i class="bi bi-x-circle-fill me-1"></i> Rejected by CEO
                                    </span>
                                @elseif($request->status === 'rejected_by_manager')
                                    <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle">
                                        <i class="bi bi-x-circle-fill me-1"></i> Rejected by You
                                    </span>
                                @elseif($request->status === 'cancelled')
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-dash-circle-fill me-1"></i> Cancelled
                                    </span>
                                @else
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-clock-fill me-1"></i> Pending Manager Approval
                                    </span>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex align-items-center gap-2">
                                    @if($request->comments->count() > 0)
                                        <span class="badge bg-info-subtle text-info-emphasis rounded-pill" title="Has conversation">
                                            <i class="bi bi-chat-text-fill"></i> {{ $request->comments->count() }}
                                        </span>
                                    @endif
                                    <button type="button" class="btn btn-sm btn-primary" onclick="openRequestModal({{ $request->id }})">View / Action</button>
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
                            <span class="d-super-admin-resolved">{{ ($request->reviewer && $request->reviewer->isSuperAdmin()) ? 'true' : 'false' }}</span>
                            <div class="d-comments">
                                @forelse($request->comments->sortBy('created_at') as $comment)
                                    @php
                                        $isEmp = $comment->user_id === $request->user_id;
                                        $isSA = $comment->user->isSuperAdmin();
                                        $isLM = $comment->user->isAdmin();
                                        
                                        $bgColor = 'bg-light text-dark border';
                                        $badgeText = '';
                                        $badgeClass = '';
                                        if ($isSA) {
                                            $bgColor = 'bg-danger-subtle text-danger-emphasis border border-danger-subtle';
                                            $badgeText = 'Super Admin';
                                            $badgeClass = 'badge bg-danger';
                                        } elseif ($isLM) {
                                            $bgColor = 'bg-primary-subtle text-primary-emphasis border border-primary-subtle';
                                            $badgeText = 'Line Manager';
                                            $badgeClass = 'badge bg-primary';
                                        } elseif ($isEmp) {
                                            $bgColor = 'bg-light text-dark border';
                                            $badgeText = 'Employee';
                                            $badgeClass = 'badge bg-secondary';
                                        }
                                    @endphp
                                    <div class="mb-3 p-3 rounded {{ $bgColor }}">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <div>
                                                <strong class="small">{{ $comment->user->name }}</strong>
                                                @if($badgeText)
                                                    <span class="{{ $badgeClass }} ms-1" style="font-size: 0.7rem;">{{ $badgeText }}</span>
                                                @endif
                                            </div>
                                            <small class="text-muted" style="font-size: 0.75rem;">{{ $comment->created_at->diffForHumans() }}</small>
                                        </div>
                                        <p class="mb-0 small mt-1">{{ $comment->comment }}</p>
                                    </div>
                                @empty
                                    <p class="text-muted small mb-0 text-center fst-italic py-3">No comments yet. Start a conversation!</p>
                                @endforelse
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                No pending leave requests.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($requests->hasPages())
            <div class="card-footer bg-white border-0 pt-3">{{ $requests->links() }}</div>
        @endif
    </div>

    <!-- View / Action Modal -->
    <div class="modal fade" id="requestModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light border-0">
                    <h5 class="modal-title fw-bold">Leave Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="row g-0">
                        <!-- Left Side: Details -->
                        <div class="col-md-5 bg-light border-end p-4">
                            <h6 class="text-uppercase text-muted fw-bold small mb-3"><i class="bi bi-info-circle me-1"></i> Request Info</h6>
                            <div class="mb-3">
                                <label class="small text-muted mb-0">Employee</label>
                                <div class="fw-medium" id="m_name"></div>
                                <div class="small text-muted" id="m_dept"></div>
                            </div>
                            <div class="mb-3">
                                <label class="small text-muted mb-0">Leave Type</label>
                                <div class="fw-medium text-primary" id="m_type"></div>
                            </div>
                            <div class="mb-3">
                                <label class="small text-muted mb-0">Duration</label>
                                <div class="fw-medium" id="m_dates"></div>
                            </div>
                            <div class="mb-3">
                                <label class="small text-muted mb-0">Reason Provided</label>
                                <div class="bg-white p-3 rounded shadow-sm small mt-1 border" id="m_reason" style="min-height: 80px;"></div>
                            </div>
                        </div>
                        
                        <!-- Right Side: Comments & Actions -->
                        <div class="col-md-7 p-4 d-flex flex-column h-100">
                            <h6 class="text-uppercase text-muted fw-bold small mb-3"><i class="bi bi-chat-dots me-1"></i> Conversation & Actions</h6>
                            
                            <!-- Comments Area -->
                            <div class="flex-grow-1 overflow-auto mb-3 pe-2" id="m_comments" style="max-height: 250px;">
                                <!-- Comments appended here -->
                            </div>
                            
                            <!-- Action Form -->
                            <form method="POST" id="actionForm" class="mt-auto">
                                @csrf
                                <div id="modal-alert-container"></div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Add a Comment (Visible to Employee)</label>
                                    <textarea name="comment" class="form-control" rows="2" placeholder="Type your message or reasoning to the employee..."></textarea>
                                </div>
                                
                                <div id="m_super_admin_alert" class="alert alert-warning py-2 px-3 small mb-3 d-none">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Checked and resolved by Super Admin
                                </div>

                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="submitAction('comment')">Just Comment</button>
                                    </div>
                                    <div id="m_action_buttons">
                                        <button type="button" class="btn btn-danger btn-sm me-2" id="btn-reject" onclick="submitAction('reject')"><i class="bi bi-x-circle me-1"></i> Reject Leave</button>
                                        <button type="button" class="btn btn-success btn-sm" id="btn-approve" onclick="submitAction('approve')"><i class="bi bi-check-circle me-1"></i> Approve Leave</button>
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
        <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
            <div class="modal-content border-0 shadow">
                <div class="modal-body text-center p-4">
                    <div class="text-warning mb-3">
                        <i class="bi bi-exclamation-circle-fill fs-1"></i>
                    </div>
                    <h5 class="fw-bold mb-2" id="confirmTitle">Are you sure?</h5>
                    <p class="text-muted small mb-4" id="confirmText">Do you really want to proceed with this action?</p>
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary px-4" id="btnConfirmAction">Yes, Proceed</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                
                // Super Admin resolved checks
                const isSuperAdminResolved = dataDiv.querySelector('.d-super-admin-resolved').textContent.trim() === 'true';
                const mActionButtons = document.getElementById('m_action_buttons');
                const superAdminAlert = document.getElementById('m_super_admin_alert');
                
                if (isSuperAdminResolved) {
                    mActionButtons.classList.add('d-none');
                    superAdminAlert.classList.remove('d-none');
                } else {
                    superAdminAlert.classList.add('d-none');
                    mActionButtons.classList.remove('d-none');
                    
                    // Toggle action buttons based on status
                    const status = dataDiv.querySelector('.d-status').textContent.trim();
                    const btnApprove = document.getElementById('btn-approve');
                    const btnReject = document.getElementById('btn-reject');
                    
                    btnApprove.classList.toggle('d-none', status === 'approved_by_manager' || status === 'approved');
                    btnReject.classList.toggle('d-none', status === 'rejected_by_manager' || status === 'rejected' || status === 'cancelled');
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
                actionForm.action = `/admin/leave-requests/${currentRequestId}/${actionType}`;
                
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
                    // For approve/reject, use custom confirmation modal instead of browser confirm()
                    pendingActionType = actionType;
                    const confirmTitle = document.getElementById('confirmTitle');
                    const confirmText = document.getElementById('confirmText');
                    const btnConfirmAction = document.getElementById('btnConfirmAction');

                    if (actionType === 'approve') {
                        confirmTitle.textContent = 'Approve Leave Request?';
                        confirmText.textContent = 'Are you sure you want to approve this leave request? This will record your recommendation.';
                        btnConfirmAction.className = 'btn btn-success px-4';
                        btnConfirmAction.textContent = 'Yes, Approve';
                    } else if (actionType === 'reject') {
                        confirmTitle.textContent = 'Reject Leave Request?';
                        confirmText.textContent = 'Are you sure you want to reject this leave request? This will decline the request.';
                        btnConfirmAction.className = 'btn btn-danger px-4';
                        btnConfirmAction.textContent = 'Yes, Reject';
                    }

                    const confirmModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmActionModal'));
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
