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
                                <div>
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
                                </div>
                                @if($req->hasPendingChangeRequest())
                                    <div class="mt-1">
                                        <span class="row-badge-purple">
                                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Pending Changes
                                        </span>
                                    </div>
                                @elseif($req->hasApprovedManagerChangeRequest())
                                    <div class="mt-1">
                                        <span class="row-badge-purple" style="background: rgba(139, 92, 246, 0.12) !important; border: 1px solid rgba(139, 92, 246, 0.25) !important;">
                                            <i class="bi bi-clock-history me-1"></i> Changes Approved (Pending CEO Approval)
                                        </span>
                                    </div>
                                @elseif($req->hasRejectedManagerChangeRequest())
                                    <div class="mt-1">
                                        <span class="row-badge-purple" style="background: rgba(139, 92, 246, 0.12) !important; border: 1px solid rgba(139, 92, 246, 0.25) !important;">
                                            <i class="bi bi-clock-history me-1"></i> Changes Rejected (Pending CEO Approval)
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
                                    @if($req->status === 'pending' || $req->status === 'approved_by_manager')
                                        <button type="button" class="row-btn-outline-red btn-cancel-leave" data-id="{{ $req->id }}">Cancel</button>
                                    @endif
                                    @if(!in_array($req->status, ['cancelled', 'rejected']) && !$req->hasOutstandingChangeRequest())
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
                            <span class="d-dates">{{ $req->start_date->format('M d, Y') }} – {{ $req->end_date->format('M d, Y') }} ({{ $req->days_count }} days)</span>
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
                                        {{ \Carbon\Carbon::parse($req->change_request['start_date'] ?? $req->start_date)->format('M d, Y') }} – {{ \Carbon\Carbon::parse($req->change_request['end_date'] ?? $req->end_date)->format('M d, Y') }}
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
                <div class="modal-header bg-dark border-bottom border-secondary border-opacity-25 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="modal-title fw-bold text-white fs-6 mb-0">Leave Request Conversation</h5>
                        <span id="m_change_badge" class="row-badge-purple d-none" style="font-size: 0.78rem !important; padding: 0 0.9rem !important; height: 30px !important;">Changes Requested</span>
                    </div>
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

                            <!-- Comparison Section (visible when change requested) -->
                            <div id="m_comparison_section" class="d-none mt-3 p-3 rounded" style="background: rgba(255, 193, 7, 0.05); border: 1px solid rgba(255, 193, 7, 0.15);">
                                <h6 class="text-warning small fw-bold mb-3"><i class="bi bi-arrow-left-right me-1"></i> Requested Changes</h6>
                                
                                <div id="m_change_cancel_alert" class="alert alert-danger py-2 px-3 small d-none">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> <strong>Cancellation Requested!</strong>
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

                        <!-- Option: Request Cancellation -->
                        <div class="mb-4 p-3" id="cancel-option-container" 
                            style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.35); border-radius: 16px;">
                            
                            <div class="d-flex justify-content-between align-items-center mb-2 px-3 py-2" 
                                style="background: rgba(239, 68, 68, 0.05); border-radius: 50px; border: 1px solid rgba(239, 68, 68, 0.2); width: 100%;">
                                
                                <label class="form-check-label fw-semibold text-danger mb-0 cursor-pointer" for="request_cancellation" style="user-select: none; font-size: 0.85rem; line-height: 1;">
                                    Request Full Cancellation of Approved Leave
                                </label>
                                
                                <input class="form-check-input bg-dark border-danger cursor-pointer" 
                                    type="checkbox" 
                                    id="request_cancellation" 
                                    name="request_cancellation" 
                                    value="1" 
                                    style="border-radius: 50%; width: 1.15rem; height: 1.15rem; margin: 0; transform: none;">
                            </div>
                            
                            <small class="text-white opacity-50 d-block ms-1" style="font-size: 0.78rem; letter-spacing: 0.2px;">
                                Turn this checkbox ON if you wish to completely cancel this approved leave request.
                            </small>
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
                                        <option value="{{ $type->id }}" class="bg-dark text-white">{{ $type->name }}</option>
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

                    const isCancel = dataDiv.querySelector('.d-change-cancel').textContent.trim() === '1';
                    const changeCancelAlert = document.getElementById('m_change_cancel_alert');
                    const changeDetailsPane = document.getElementById('m_change_details_pane');

                    if (isCancel) {
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
                form.action = `/employee/leave-requests/${id}/change-request`;

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

                // If approved / approved_by_manager, show request cancellation switch
                const cancelContainer = document.getElementById('cancel-option-container');
                const cancelSwitch = document.getElementById('request_cancellation');
                cancelSwitch.checked = false;
                if (status === 'approved' || status === 'approved_by_manager') {
                    cancelContainer.classList.remove('d-none');
                } else {
                    cancelContainer.classList.add('d-none');
                }
                
                document.getElementById('change-details-section').classList.remove('d-none');

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

            document.getElementById('request_cancellation').addEventListener('change', function() {
                const detailsSection = document.getElementById('change-details-section');
                if (this.checked) {
                    detailsSection.classList.add('d-none');
                } else {
                    detailsSection.classList.remove('d-none');
                }
            });

            let currentLeaveRequestId = null;
            let pendingChangeFormData = null;
            let pendingChangeFormAction = null;

            document.getElementById('changeRequestForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const form = this;

                const requestCancellation = document.getElementById('request_cancellation').checked;
                const dateChange = document.getElementById('toggle_date_change').checked;
                const typeChange = document.getElementById('toggle_type_change').checked;

                const newReason = document.getElementById('c_reason').value.trim();
                const reasonChanged = newReason !== origReason.trim();

                if (!requestCancellation && !dateChange && !typeChange && !reasonChanged) {
                    document.getElementById('change-error-text').textContent = 'Please select at least one change to request (Date, Type, Cancellation) or modify the reason.';
                    document.getElementById('change-error-alert').classList.remove('d-none');
                    return;
                }

                if (!requestCancellation) {
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
                    const newReason = document.getElementById('c_reason').value.trim();
                    if (newReason !== origReason.trim()) {
                        hasRealChanges = true;
                    }

                    if (!hasRealChanges) {
                        document.getElementById('change-error-text').textContent = 'You checked the change options but did not make any edits to the selected fields.';
                        document.getElementById('change-error-alert').classList.remove('d-none');
                        return;
                    }
                }

                document.getElementById('change-error-alert').classList.add('d-none');

                // Build Form Data manually
                pendingChangeFormData = new FormData();
                pendingChangeFormData.append('_token', form.querySelector('input[name="_token"]').value);

                if (requestCancellation) {
                    pendingChangeFormData.append('request_cancellation', '1');
                } else {
                    if (dateChange) {
                        pendingChangeFormData.append('start_date', document.getElementById('c_start_date').value);
                        pendingChangeFormData.append('end_date', document.getElementById('c_end_date').value);
                    }
                    if (typeChange) {
                        pendingChangeFormData.append('leave_type_id', document.getElementById('c_leave_type_id').value);
                    }
                    pendingChangeFormData.append('reason', document.getElementById('c_reason').value);
                }

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
        <script src="{{ asset('js/leave-request.js') }}"></script>
    @endpush
</x-app-layout>
