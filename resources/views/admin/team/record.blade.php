<x-app-layout>
    <x-slot name="header">My Leave Record</x-slot>

    <!-- Filters Row -->
    <form class="row g-3 mb-4 align-items-center" method="GET" id="filterForm">
        <div class="col-md-4">
            <label class="form-label small text-muted mb-1">Leave Type</label>
            <select name="leave_type_id" class="form-select border-0 shadow-sm" onchange="document.getElementById('filterForm').submit()">
                <option value="">All Leave Types</option>
                @foreach($leaveTypes as $type)
                    <option value="{{ $type->id }}" @selected(request('leave_type_id') == $type->id)>{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small text-muted mb-1">Status</label>
            <select name="status" class="form-select border-0 shadow-sm" onchange="document.getElementById('filterForm').submit()">
                <option value="">All Statuses</option>
                @foreach([
                    'pending' => 'Pending Approval',
                    'approved_by_manager' => 'Manager Approved (Pending CEO)',
                    'approved' => 'Fully Approved',
                    'rejected' => 'Rejected',
                    'rejected_by_manager' => 'Rejected by Line Manager',
                    'cancelled' => 'Cancelled'
                ] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') == $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 text-end mt-auto">
            <a href="{{ route('admin.leaves.record') }}" class="btn btn-outline-secondary shadow-sm w-100"><i class="bi bi-x-circle me-1"></i> Clear Filters</a>
        </div>
    </form>

    <!-- Leave Record Card Table (Full Width) -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 pt-4 pb-0">
            <h6 class="text-uppercase text-muted fw-bold small mb-0"><i class="bi bi-list-task me-1"></i> My Leave Record & Conversations</h6>
        </div>
        <div class="table-responsive mt-3">
            <table class="table table-hover align-middle mb-0" id="leave-requests-table">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Type</th>
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Messages</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentRequests as $req)
                        <tr id="leave-row-{{ $req->id }}">
                            <td class="ps-4 fw-medium">{{ $req->leaveType->name }}</td>
                            <td>{{ $req->start_date->format('M d') }} – {{ $req->end_date->format('M d, Y') }}</td>
                            <td>{{ $req->days_count }}</td>
                            <td>
                                @if($req->status === 'approved_by_manager')
                                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">
                                        <i class="bi bi-clock-history me-1"></i> Manager Approved (Pending CEO Approval)
                                    </span>
                                @elseif($req->status === 'approved')
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle-fill me-1"></i> Fully Approved
                                    </span>
                                @elseif($req->status === 'rejected')
                                    <span class="badge bg-danger">
                                        <i class="bi bi-x-circle-fill me-1"></i> Rejected
                                    </span>
                                @elseif($req->status === 'rejected_by_manager')
                                    <span class="badge bg-danger-subtle text-danger-emphasis border border-danger-subtle">
                                        <i class="bi bi-x-circle-fill me-1"></i> Rejected by Line Manager
                                    </span>
                                @elseif($req->status === 'cancelled')
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-dash-circle-fill me-1"></i> Cancelled
                                    </span>
                                @else
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-clock-fill me-1"></i> Pending Manager Approval
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($req->comments->count() > 0)
                                    <span class="badge bg-info-subtle text-info-emphasis rounded-pill">
                                        <i class="bi bi-chat-text-fill me-1"></i> {{ $req->comments->count() }}
                                    </span>
                                @else
                                    <span class="text-muted small">No messages</span>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-flex gap-2 justify-content-end align-items-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="openRequestModal({{ $req->id }})">
                                        <i class="bi bi-chat-dots me-1"></i> Chat
                                    </button>
                                    @if($req->status === 'pending' || $req->status === 'approved_by_manager')
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-cancel-leave" data-id="{{ $req->id }}">Cancel</button>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        <!-- Hidden Data for Chat Modal -->
                        <div id="request-data-{{ $req->id }}" class="d-none">
                            <span class="d-type">{{ $req->leaveType->name }}</span>
                            <span class="d-dates">{{ $req->start_date->format('M d, Y') }} – {{ $req->end_date->format('M d, Y') }} ({{ $req->days_count }} days)</span>
                            <span class="d-reason">{{ $req->reason }}</span>
                            <span class="d-status">
                                @if($req->status === 'approved_by_manager')
                                    Manager Approved (Pending CEO Approval)
                                @elseif($req->status === 'approved')
                                    Fully Approved
                                @elseif($req->status === 'rejected')
                                    Rejected
                                @elseif($req->status === 'cancelled')
                                    Cancelled
                                @else
                                    Pending Manager Approval
                                @endif
                            </span>
                            <div class="d-comments">
                                @forelse($req->comments->sortBy('created_at') as $comment)
                                    @php
                                        $isEmp = $comment->user_id === $req->user_id;
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
                                    <p class="text-muted small mb-0 text-center fst-italic py-3">No messages yet. Chat with Super Admin here!</p>
                                @endforelse
                            </div>
                        </div>
                    @empty
                        <tr><td colspan="6" class="text-center py-5 text-muted">No leave requests match the filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($recentRequests->hasPages())
            <div class="card-footer bg-white border-0 pt-3">{{ $recentRequests->links() }}</div>
        @endif
    </div>

    <!-- View Info / Chat Modal -->
    <div class="modal fade" id="requestModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light border-0">
                    <h5 class="modal-title fw-bold">Leave Request Conversation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="row g-0">
                        <!-- Left Side: Details -->
                        <div class="col-md-5 bg-light border-end p-4">
                            <h6 class="text-uppercase text-muted fw-bold small mb-3"><i class="bi bi-info-circle me-1"></i> Request Details</h6>
                            <div class="mb-3">
                                <label class="small text-muted mb-0">Leave Type</label>
                                <div class="fw-medium text-primary" id="m_type"></div>
                            </div>
                            <div class="mb-3">
                                <label class="small text-muted mb-0">Duration</label>
                                <div class="fw-medium text-dark" id="m_dates"></div>
                            </div>
                            <div class="mb-3">
                                <label class="small text-muted mb-0">Status</label>
                                <div class="fw-medium" id="m_status"></div>
                            </div>
                            <div class="mb-3">
                                <label class="small text-muted mb-0">Reason Provided</label>
                                <div class="bg-white p-3 rounded shadow-sm small mt-1 border" id="m_reason" style="min-height: 80px;"></div>
                            </div>
                        </div>
                        
                        <!-- Right Side: Chat -->
                        <div class="col-md-7 p-4 d-flex flex-column h-100">
                            <h6 class="text-uppercase text-muted fw-bold small mb-3"><i class="bi bi-chat-dots me-1"></i> Conversation Log</h6>
                            
                            <!-- Comments Area -->
                            <div class="flex-grow-1 overflow-auto mb-3 pe-2" id="m_comments" style="max-height: 250px;">
                                <!-- Comments appended here -->
                            </div>
                            
                            <!-- Action Form -->
                            <form method="POST" id="actionForm" class="mt-auto">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Add Message</label>
                                    <textarea name="comment" class="form-control" rows="2" placeholder="Send a message to super admin..." required></textarea>
                                </div>
                                
                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-send me-1"></i> Send Message</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            window.leaveCancelUrl = "{{ url('/employee/leave-requests') }}";
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
                actionForm.action = `/employee/leave-requests/${currentRequestId}/comment`;
                
                // Reset form input
                actionForm.reset();

                // Show modal
                const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('requestModal'));
                modal.show();
            };
        </script>
        <script src="{{ asset('js/leave-request.js') }}"></script>
    @endpush
</x-app-layout>
