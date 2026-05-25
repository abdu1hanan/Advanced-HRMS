<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center">
            <span>Leave History {{ $user ? '— ' . $user->name : '(All Employees)' }}</span>
            <div style="width: 300px;">
                <select class="form-select border-0 shadow-sm" onchange="window.location.href='/super-admin/leave-requests/history' + (this.value ? '?user_id='+this.value : '')">
                    <option value="">All Employees</option>
                    @foreach($allUsers as $u)
                        <option value="{{ $u->id }}" @selected($user && $u->id == $user->id)>{{ $u->name }} ({{ $u->department?->name ?? 'No Dept' }})</option>
                    @endforeach
                </select>
            </div>
        </div>
    </x-slot>

    @if($user)
        <!-- Leave Balances Row -->
        <div class="row g-4 mb-4">
            @foreach($balances as $balance)
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h6 class="text-uppercase text-muted fw-bold small mb-1">{{ $balance->leaveType->name }}</h6>
                            <div class="d-flex align-items-baseline">
                                <span class="fs-3 fw-bold text-primary">{{ $balance->entitled }}</span>
                                <span class="text-muted ms-1">entitled</span>
                            </div>
                            <div class="row g-0 mt-2 pt-2 border-top text-center small text-muted">
                                <div class="col-4 border-end">
                                    <strong class="d-block text-dark">{{ $balance->used }}</strong> Used
                                </div>
                                <div class="col-4 border-end">
                                    <strong class="d-block text-dark">{{ $balance->pending }}</strong> Pending
                                </div>
                                <div class="col-4">
                                    <strong class="d-block text-success">{{ $balance->entitled - $balance->used }}</strong> Left
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        @if(!$user)
                            <th class="ps-4">Employee</th>
                        @endif
                        <th class="{{ $user ? 'ps-4' : '' }}">Type</th>
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Status</th>
                        <th>Reviewed By</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($history as $req)
                        <tr class="cursor-pointer">
                            @if(!$user)
                                <td class="ps-4 fw-medium">{{ $req->user->name }}</td>
                            @endif
                            <td class="{{ $user ? 'ps-4' : '' }} fw-medium">{{ $req->leaveType->name }}</td>
                            <td>{{ $req->start_date->format('M d, Y') }} – {{ $req->end_date->format('M d, Y') }}</td>
                            <td>{{ $req->days_count }}</td>
                            <td>
                                @if($req->status === 'approved')
                                    <span class="badge bg-success">Approved</span>
                                @elseif($req->status === 'rejected')
                                    <span class="badge bg-danger">Rejected</span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst($req->status) }}</span>
                                @endif
                            </td>
                            <td>{{ $req->reviewer?->name ?? '—' }}</td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex align-items-center gap-2">
                                    @if($req->comments->count() > 0)
                                        <span class="badge bg-info-subtle text-info-emphasis rounded-pill" title="Has conversation">
                                            <i class="bi bi-chat-text-fill"></i> {{ $req->comments->count() }}
                                        </span>
                                    @endif
                                    <button type="button" class="btn btn-sm btn-info text-white" onclick="openRequestModal({{ $req->id }})"><i class="bi bi-info-circle me-1"></i> Info</button>
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
                                No leave history found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($history->hasPages())
            <div class="card-footer bg-white border-0 pt-3">{{ $history->links() }}</div>
        @endif
    </div>
    
    <a href="{{ route('super-admin.leave-requests.index') }}" class="btn btn-link mt-2 text-decoration-none text-muted">← Back to All Requests</a>

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
                            <h6 class="text-uppercase text-muted fw-bold small mb-3"><i class="bi bi-chat-dots me-1"></i> Conversation</h6>
                            
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
                                
                                <div class="text-end">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="submitAction('comment')">Just Comment</button>
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
            let currentRequestId = null;
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

                // Reset inline alerts
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
                }
                
                actionForm.submit();
            };
        </script>
    @endpush
</x-app-layout>
