<x-app-layout>
    <x-slot name="header">My Leave Record</x-slot>

    <!-- Filters Row -->
    <form class="row g-3 mb-4 align-items-center" method="GET" id="filterForm">
        <div class="col-md-2">
            <label class="form-label small text-white opacity-50 mb-1">Leave Type</label>
            <select name="leave_type_id" class="form-select border-0 shadow-sm" 
                    style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important;"
                    onchange="document.getElementById('filterForm').submit()">
                <option value="" class="bg-dark text-white">All Leave Types</option>
                @foreach($leaveTypes as $type)
                    <option value="{{ $type->id }}" class="bg-dark text-white" @selected(request('leave_type_id') == $type->id)>{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small text-white opacity-50 mb-1">Status</label>
            <select name="status" class="form-select border-0 shadow-sm" 
                    style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important;"
                    onchange="document.getElementById('filterForm').submit()">
                <option value="" class="bg-dark text-white">All Statuses</option>
                @foreach([
                    'pending' => 'Pending Approval',
                    'approved_by_manager' => 'Manager Approved (Pending CEO)',
                    'approved' => 'Fully Approved',
                    'rejected' => 'Rejected',
                    'rejected_by_manager' => 'Rejected by Line Manager',
                    'cancelled' => 'Cancelled'
                ] as $value => $label)
                    <option value="{{ $value }}" class="bg-dark text-white" @selected(request('status') == $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small text-white opacity-50 mb-1">From Date</label>
            <input type="date" name="start_date" class="form-control border-0 shadow-sm" 
                   style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important;"
                   value="{{ request('start_date') }}" onchange="document.getElementById('filterForm').submit()">
        </div>
        <div class="col-md-3">
            <label class="form-label small text-white opacity-50 mb-1">To Date</label>
            <input type="date" name="end_date" class="form-control border-0 shadow-sm" 
                   style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important;"
                   value="{{ request('end_date') }}" onchange="document.getElementById('filterForm').submit()">
        </div>
        <div class="col-md-2 text-end mt-auto">
            <a href="{{ route('admin.leaves.record') }}" class="btn btn-outline-secondary text-decoration-none shadow-sm w-100" style="height: 38px; display: flex; align-items: center; justify-content: center; gap: 6px;">
                <i class="bi bi-x-circle fs-6"></i> Clear Filters
            </a>
        </div>
    </form>

    <!-- Leave Record Card Table (Full Width) -->
    <div class="spotify-card mb-4">
        <div class="card-header bg-transparent border-0 p-4 pb-0">
            <h6 class="text-uppercase text-white fw-bold small mb-0 opacity-75" style="letter-spacing: 0.05em;">
                <i class="bi bi-list-task me-2 text-primary"></i> My Leave Record & Conversations
            </h6>
            <div class="grid-separator mt-3"></div>
        </div>
        <div class="table-responsive mt-2">
            <table class="table table-hover align-middle mb-0" id="leave-requests-table">
                <thead>
                    <tr>
                        <th class="ps-4 text-white opacity-50">Type</th>
                        <th class="text-white opacity-50">Dates</th>
                        <th class="text-white opacity-50">Entry On</th>
                        <th class="text-white opacity-50">Days</th>
                        <th class="text-white opacity-50">Status</th>
                        <th class="text-white opacity-50">Messages</th>
                        <th class="text-end pe-4 text-white opacity-50">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentRequests as $req)
                        <tr id="leave-row-{{ $req->id }}" class="hover-highlight">
                            <td class="ps-4 fw-semibold text-white">{{ $req->leaveType->name }}</td>
                            <td><span class="text-white opacity-75">{{ $req->start_date->format('M d') }} – {{ $req->end_date->format('M d, Y') }}</span></td>
                            <td><span class="text-white opacity-75">{{ $req->created_at->format('M d, Y') }}</span></td>
                            <td><span class="row-badge" style="background: rgba(255, 255, 255, 0.08); color: rgba(255, 255, 255, 0.85); border: transparent;">{{ $req->days_count }}</span></td>
                            <td>
                                @if($req->status === 'approved_by_manager')
                                    <span class="row-badge-green"><i class="bi bi-clock-history me-1"></i> Manager Approved</span>
                                @elseif($req->status === 'approved')
                                    <span class="row-badge-green"><i class="bi bi-check-circle-fill me-1"></i> Fully Approved</span>
                                @elseif($req->status === 'rejected')
                                    <span class="row-badge-red"><i class="bi bi-x-circle-fill me-1"></i> Rejected</span>
                                @elseif($req->status === 'rejected_by_manager')
                                    <span class="row-badge-red"><i class="bi bi-x-circle-fill me-1"></i> Rejected by Manager</span>
                                @elseif($req->status === 'cancelled')
                                    <span class="row-badge-gray"><i class="bi bi-x-circle me-1"></i> Cancelled</span>
                                @else
                                    <span class="row-badge-warning"><i class="bi bi-clock-fill me-1"></i> Pending Manager</span>
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
                                    @if($req->status === 'pending' || $req->status === 'approved_by_manager')
                                        <button type="button" class="row-btn-outline-red btn-cancel-leave" data-id="{{ $req->id }}">Cancel</button>
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
                                    <p class="text-white opacity-50 small mb-0 text-center fst-italic py-3">No messages yet. Chat with Super Admin here!</p>
                                @endforelse
                            </div>
                        </div>
                    @empty
                        <tr><td colspan="6" class="text-center py-5 text-white opacity-50"><i class="bi bi-inbox fs-3 d-block mb-2"></i> No leave requests match the filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($recentRequests->hasPages())
            <div class="px-4 py-3 border-top border-secondary border-opacity-25">{{ $recentRequests->links() }}</div>
        @endif
    </div>

    @push('modals')
    <!-- View Info / Chat Modal -->
    <div class="modal fade" id="requestModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-dark border-0 shadow" style="overflow: hidden; background-color: #212529 !important;">
                <div class="modal-header bg-dark border-bottom border-secondary border-opacity-25">
                    <h5 class="modal-title fw-bold text-white fs-6">Leave Request Conversation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="row g-0">
                        <!-- Left Side: Details -->
                        <div class="col-md-5 bg-dark border-end border-secondary border-opacity-25 p-4">
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
                        </div>
                        
                        <!-- Right Side: Chat -->
                        <div class="col-md-7 p-4 d-flex flex-column h-100 bg-dark">
                            <h6 class="text-uppercase text-white opacity-50 fw-bold small mb-3"><i class="bi bi-chat-dots me-1"></i> Conversation Log</h6>
                            
                            <!-- Comments Area -->
                            <div class="flex-grow-1 overflow-auto mb-3 pe-2 custom-scrollbar" id="m_comments" style="max-height: 250px;">
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
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endpush

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
