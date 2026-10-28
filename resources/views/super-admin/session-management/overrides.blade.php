<x-app-layout>
    <x-slot name="header">Beetle Sync</x-slot>

    <!-- Filters Card -->
    <div class="spotify-card mb-3 mt-3">
        <div class="p-4">
            <div class="row g-3 align-items-end">
                <!-- Employee Name Filter -->
                <div class="col-md-3">
                    <label class="form-label text-white opacity-50 small mb-1">Employee Filter</label>
                    <select id="filter-employee" class="form-select border-0 shadow-sm text-start" style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important; height: 37px; border-radius: 6px; text-align: left !important; text-align-last: left !important; padding-left: 0.75rem !important;">
                        <option value="" class="bg-dark text-white" style="text-align: left !important;">All Employees</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp['value'] }}" class="bg-dark text-white" {{ request('employee_id') == $emp['value'] ? 'selected' : '' }}>{{ trim($emp['label']) }}</option>
                        @endforeach
                    </select>
                </div>
                
                <!-- Date Filter -->
                <div class="col-md-3">
                    <label class="form-label text-white opacity-50 small mb-1">Date Period</label>
                    <select id="filter-date-period" class="form-select border-0 shadow-sm text-start" style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important; height: 37px; border-radius: 6px; text-align: left !important; text-align-last: left !important; padding-left: 0.75rem !important;">
                        <option value="all" class="bg-dark text-white" style="text-align: left !important;">All Dates</option>
                        <option value="today" class="bg-dark text-white" style="text-align: left !important;">Today</option>
                        <option value="yesterday" class="bg-dark text-white" style="text-align: left !important;">Yesterday</option>
                        <option value="last_7" class="bg-dark text-white" style="text-align: left !important;">Last 7 Days</option>
                        <option value="last_30" class="bg-dark text-white" style="text-align: left !important;">Last 30 Days</option>
                    </select>
                </div>

                <!-- Custom Date Range Inputs (always visible, applies when dates are entered) -->
                <div class="col-md-4" id="custom-date-range-container">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label text-white opacity-50 small mb-1">Start Date</label>
                            <input type="date" id="filter-start-date" class="form-control border-0 shadow-sm" style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important; height: 37px; border-radius: 6px;">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-white opacity-50 small mb-1">End Date</label>
                            <input type="date" id="filter-end-date" class="form-control border-0 shadow-sm" style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important; height: 37px; border-radius: 6px;">
                        </div>
                    </div>
                </div>

                <!-- Clear Filters Button -->
                <div class="col-md-2 d-flex align-items-end">
                    <div class="w-100">
                        <label class="form-label text-white opacity-50 small mb-1">&nbsp;</label>
                        <button type="button" id="clear-filters-btn" class="row-btn w-100 text-decoration-none justify-content-center" style="height: 37px !important;">
                            <i class="bi bi-x-circle me-1"></i> Clear Filters
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Table Card -->
    <div class="spotify-card mb-4 mt-3">
        <div class="card-header-area px-4 pt-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-2">
                    <h6 class="text-uppercase text-white fw-semibold small mb-0 opacity-75" style="letter-spacing: 0.05em;">Incoming Message Feed</h6>
                    <i class="bi bi-info-circle text-white opacity-75 cursor-pointer" style="font-size: 0.85rem;" data-custom-tooltip="You can change your decision by clicking the badge again" data-tooltip-placement="top"></i>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <!-- Approval Required Toggle Badge -->
                    <div class="d-flex align-items-center gap-3 px-3 justify-content-between" style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; border-radius: 50px !important; height: 32px !important;">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-info-circle text-white opacity-75 cursor-pointer" 
                               data-custom-tooltip="Toggle to filter the stream and display only messages&#10;requiring your approval or action." 
                               data-tooltip-placement="top" 
                               style="font-size: 0.85rem;"></i>
                            <span class="text-white small opacity-75" style="user-select: none; font-size: 0.82rem; white-space: nowrap;">Approval Required</span>
                        </div>
                        <div class="form-check form-switch mb-0" style="padding-left: 0; display: flex; align-items: center;">
                            <input class="form-check-input bg-dark border-secondary cursor-pointer" type="checkbox" id="filter-approval-required" style="width: 2.2em; height: 1.1em; margin-left: 0; margin-top: 0;">
                        </div>
                    </div>

                    <span id="last-update" class="row-badge-warning d-inline-flex align-items-center" style="height: 32px !important; font-size: 0.82rem !important; padding: 0 1rem !important; border-radius: 50px !important; border: 1px solid rgba(245, 158, 11, 0.3) !important;">
                        Last synced: {{ now()->timezone('Asia/Karachi')->format('h:i A') }}
                    </span>
                    <span class="row-badge-green d-inline-flex align-items-center" style="height: 32px !important; font-size: 0.82rem !important; padding: 0 1rem !important; border-radius: 50px !important; border: 1px solid rgba(16, 185, 129, 0.3) !important; overflow: hidden;">
                        <span class="rounded-circle bg-success dot-pulse-soft" style="width: 6px; height: 6px; display: inline-block; background-color: #10b981 !important; margin-right: 6px; flex-shrink: 0;"></span>
                        Live Stream
                    </span>
                </div>
            </div>
            <div class="grid-separator"></div>
        </div>
        
        <div class="table-responsive scrollable-table-container">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-4" style="width: 38%;">Message Text</th>
                        <th style="width: 18%;" id="user-column-header">{{ $hasMappedNames ? 'Employee' : 'Slack User ID' }}</th>
                        <th style="width: 14%;">Channel ID</th>
                        <th style="width: 15%;">Timestamp</th>
                        <th class="text-center" style="width: 15%;">HR Approval</th>
                    </tr>
                </thead>
                <tbody id="message-table-body">
                    @php
                        $lastDate = null;
                    @endphp
                    @forelse($initialMessages as $msg)
                        @php
                            $currentDate = $msg['formatted_date'];
                        @endphp
                        @if($lastDate !== $currentDate)
                            <tr class="date-header-row" data-date="{{ $currentDate }}">
                                <td colspan="5" class="py-3 px-4" style="background-color: rgba(255, 255, 255, 0.01); border-bottom: 1px solid rgba(255, 255, 255, 0.03);">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-grow-1" style="border-top: 1px solid rgba(255, 255, 255, 0.08);"></div>
                                        <span class="mx-3 text-white opacity-75 small fw-semibold text-uppercase px-3 py-1 rounded-pill" style="letter-spacing: 0.08em; font-size: 0.72rem; background-color: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.15);">{{ $currentDate }}</span>
                                        <div class="flex-grow-1" style="border-top: 1px solid rgba(255, 255, 255, 0.08);"></div>
                                    </div>
                                </td>
                            </tr>
                            @php
                                $lastDate = $currentDate;
                            @endphp
                        @endif
                        <tr data-message-id="{{ $msg['id'] }}" class="{{ $msg['is_auto_appended'] ? 'auto-appended-row' : '' }}">
                            <td class="ps-4 fw-semibold text-white text-break">
                                <span>{{ $msg['message_text'] }}</span>
                                @if($msg['is_edited'])
                                    <span class="text-white opacity-75 small ms-1 edit-history-trigger" style="font-size: 0.75rem; cursor: pointer; text-decoration: underline;" data-message-id="{{ $msg['id'] }}">(edited)</span>
                                @endif
                                @php
                                    $lowerText = strtolower(trim($msg['message_text']));
                                    $isBreakOrBack = (preg_match('/^(break|brb|back)\b/i', $lowerText));
                                @endphp
                                @if($msg['break_exceeded'] && $isBreakOrBack)
                                    @php
                                        $isCheckIn = str_contains($lowerText, 'back');
                                        if ($isCheckIn) {
                                            $tooltipText = 'Resumed session after an unpaid break exceeding daily balance.';
                                        } else {
                                            $tooltipText = 'Exceeded daily break balance (unpaid after balance exhausted).';
                                        }
                                    @endphp
                                    <span class="ms-2 text-warning cursor-pointer" data-custom-tooltip="{{ $tooltipText }}" data-tooltip-placement="top">
                                        <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 0.85rem;"></i>
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($msg['display_name'] !== $msg['slack_user_id'])
                                    <span class="fw-semibold text-white">
                                        {{ $msg['display_name'] }}
                                    </span>
                                @else
                                    <code class="text-white opacity-75 px-3 py-1 rounded-pill bg-dark border border-secondary border-opacity-25" style="border-radius: 50px !important; font-size: 0.8rem; display: inline-block;">{{ $msg['slack_user_id'] }}</code>
                                @endif
                            </td>
                            <td><code class="text-white opacity-75 px-3 py-1 rounded-pill bg-dark border border-secondary border-opacity-25" style="border-radius: 50px !important; font-size: 0.8rem; display: inline-block;">{{ $msg['channel_id'] }}</code></td>
                            <td class="text-muted">{{ $msg['formatted_time'] }}</td>
                            <td class="text-center">
                                @if($msg['channel_id'] === 'manual_override' && ($msg['approval_status'] === 'pending' || $msg['approval_status'] === 'pending_edit'))
                                    @if($msg['user_id'])
                                        @php
                                            $fixUrl = route('super-admin.session-management.track-record', [
                                                'user_id' => $msg['user_id'],
                                                'period' => 'month',
                                                'month' => $msg['month'],
                                                'year' => $msg['year'],
                                                'edit' => 'true'
                                            ]);
                                        @endphp
                                        <a href="{{ $fixUrl }}" class="row-btn-blue d-inline-flex align-items-center justify-content-center" style="height: 24px !important; font-size: 0.72rem !important; padding: 0 0.8rem !important; border-radius: 50px !important; text-decoration: none;">
                                            Fix
                                        </a>
                                    @else
                                        <span class="text-white opacity-50 small">—</span>
                                    @endif
                                @elseif($msg['channel_id'] === 'manual_override' && $msg['approval_status'] === 'approved')
                                    <span class="d-inline-flex align-items-center justify-content-center" style="height: 24px !important; font-size: 0.72rem !important; padding: 0 0.8rem !important; border-radius: 50px !important; background-color: #16a34a !important; color: #ffffff !important; border: 1px solid #15803d !important; font-weight: 700 !important; line-height: 1 !important;">
                                        Fixed
                                    </span>
                                @elseif($msg['approval_status'] === 'pending' || $msg['approval_status'] === 'pending_edit')
                                    <div class="d-inline-flex gap-2 align-items-center approval-cell-container justify-content-center">
                                        <button type="button" class="btn-circle-blue btn-approve-override" data-message-id="{{ $msg['id'] }}" data-custom-tooltip="Approve" data-tooltip-placement="top">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                        <button type="button" class="btn-circle-red-filled btn-reject-override" data-message-id="{{ $msg['id'] }}" data-custom-tooltip="Reject" data-tooltip-placement="top">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                @elseif($msg['approval_status'] === 'approved')
                                    <span class="row-badge-green d-inline-flex align-items-center btn-reset-decision-trigger" data-message-id="{{ $msg['id'] }}" style="height: 24px !important; font-size: 0.72rem !important; padding: 0 0.8rem !important; border-radius: 50px !important; border: 1px solid rgba(16, 185, 129, 0.3) !important; cursor: pointer;" data-custom-tooltip="Click to change decision" data-tooltip-placement="top">
                                        Approved
                                    </span>
                                @elseif($msg['approval_status'] === 'rejected')
                                    <span class="row-badge-red d-inline-flex align-items-center btn-reset-decision-trigger" data-message-id="{{ $msg['id'] }}" style="height: 24px !important; font-size: 0.72rem !important; padding: 0 0.8rem !important; border-radius: 50px !important; border: 1px solid rgba(239, 68, 68, 0.3) !important; cursor: pointer;" data-custom-tooltip="Click to change decision" data-tooltip-placement="top">
                                        Rejected
                                    </span>
                                @else
                                    <!-- Unmodified real Slack message shows empty cell -->
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr id="empty-row">
                            <td colspan="5" class="text-center text-white opacity-50 py-5">
                                <div class="mb-2"><i class="bi bi-chat-left-dots fs-3"></i></div>
                                No messages received yet. Send a message in Slack to verify the connection!
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit History Modal -->
    <div class="modal fade" id="editHistoryModal" tabindex="-1" aria-labelledby="editHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-white border border-secondary border-opacity-50" style="background-color: #181818; border-radius: 8px; box-shadow: 0 8px 30px rgba(0,0,0,0.5);">
                <div class="modal-header border-secondary border-opacity-25" style="background-color: #1c1c1c;">
                    <h6 class="modal-title fw-bold text-white small text-uppercase mb-0" id="editHistoryModalLabel" style="letter-spacing: 0.05em;">
                        <i class="bi bi-clock-history me-2 text-warning"></i>Edit Audit Trail
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="font-size: 0.8rem;"></button>
                </div>
                <div class="modal-body" style="background-color: #181818; max-height: 400px; overflow-y: auto; padding: 20px 24px;">
                    <div id="edit-timeline" class="position-relative border-start border-secondary border-opacity-25 ms-2 py-1">
                        <!-- Dynamic timeline items will be injected here -->
                    </div>
                </div>
                <div class="modal-footer border-secondary border-opacity-25" style="background-color: #1c1c1c;">
                    <button type="button" class="btn btn-outline-light btn-sm py-1 px-3" data-bs-dismiss="modal" style="font-size: 0.85rem; border-color: rgba(255,255,255,0.15);">Close Audit</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Beautiful Custom Confirm Modal -->
    <div class="modal fade" id="confirmResetModal" tabindex="-1" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="background-color: #0c0c0c !important; border: 1px solid rgba(255, 255, 255, 0.08) !important;">
                <div class="modal-body text-center p-4">
                    <div class="text-warning mb-3">
                        <i class="bi bi-exclamation-circle-fill" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-white">Reset Decision?</h5>
                    <p class="text-white opacity-50 small mb-4">Are you sure you want to change your decision for this override request? This will reset the status to pending, allowing you to approve or reject it again.</p>
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 145px;">Cancel</button>
                        <button type="button" class="row-btn-blue" id="btnConfirmReset" style="width: 145px;">Yes, Proceed</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .scrollable-table-container {
            max-height: calc(100vh - 270px);
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.1) transparent;
        }
        .scrollable-table-container::-webkit-scrollbar {
            width: 6px;
        }
        .scrollable-table-container::-webkit-scrollbar-track {
            background: transparent;
        }
        .scrollable-table-container::-webkit-scrollbar-thumb {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
        }
        .scrollable-table-container table thead th {
            position: sticky;
            top: 0;
            background-color: #121212 !important;
            z-index: 100;
            box-shadow: inset 0 -1px 0 rgba(255, 255, 255, 0.1);
        }

        .dot-pulse-soft {
            animation: soft-pulse 2s ease-in-out infinite;
        }
        @keyframes soft-pulse {
            0%, 100% { opacity: 0.35; transform: scale(0.9); }
            50% { opacity: 1; transform: scale(1.1); }
        }
        .new-row-highlight {
            animation: highlight-flash 3s ease-out;
        }
        @keyframes highlight-flash {
            0% { background-color: rgba(25, 135, 84, 0.25); }
            100% { background-color: transparent; }
        }
        .btn-circle-blue {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 1px solid rgba(59, 130, 246, 0.3);
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            transition: all 0.2s ease-in-out;
            cursor: pointer;
        }
        .btn-circle-blue:hover {
            background: #3b82f6;
            color: #fff;
            border-color: #3b82f6;
        }
        .btn-circle-red-filled {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 1px solid #ef4444;
            background: #ef4444;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            transition: all 0.2s ease-in-out;
            cursor: pointer;
        }
        .btn-circle-red-filled:hover {
            background: #dc2626;
            color: #ffffff;
            border-color: #dc2626;
        }
        .auto-appended-row td {
            color: #ef4444 !important;
        }
        .auto-appended-row td span {
            color: #ef4444 !important;
        }
        .auto-appended-row td code {
            border-color: rgba(239, 68, 68, 0.4) !important;
            color: #fca5a5 !important;
        }
    </style>

    @push('scripts')
        <script>
            $(function() {
                let slackMessagesCache = @json($initialMessages);
                let nextCursor = @json($nextCursor);
                let hasMore = @json($hasMore);
                let maxSeenId = @json($maxId);
                let isLoadingMore = false;
                let isFilterFetching = false;

                function renderRowHtml(msg, isNew = false) {
                    const highlightClass = isNew ? 'new-row-highlight' : '';
                    const autoAppendedClass = msg.is_auto_appended ? 'auto-appended-row' : '';
                    
                    let editBadge = msg.is_edited 
                        ? `<span class="text-white opacity-75 small ms-1 edit-history-trigger" style="font-size: 0.75rem; cursor: pointer; text-decoration: underline;" data-message-id="${msg.id}">(edited)</span>` 
                        : '';

                    let userCell = '';
                    if (msg.display_name !== msg.slack_user_id) {
                        userCell = `<span class="fw-semibold text-white">${msg.display_name}</span>`;
                    } else {
                        userCell = `<code class="text-white opacity-75 px-3 py-1 rounded-pill bg-dark border border-secondary border-opacity-25" style="border-radius: 50px !important; font-size: 0.8rem; display: inline-block;">${msg.slack_user_id}</code>`;
                    }

                    let approvalCell = '';
                    if (msg.channel_id === 'manual_override' && (msg.approval_status === 'pending' || msg.approval_status === 'pending_edit')) {
                        if (msg.user_id) {
                            const fixUrl = `/super-admin/session-management/track-record?user_id=${msg.user_id}&period=month&month=${msg.month}&year=${msg.year}&edit=true`;
                            approvalCell = `
                                <a href="${fixUrl}" class="row-btn-blue d-inline-flex align-items-center justify-content-center" style="height: 24px !important; font-size: 0.72rem !important; padding: 0 0.8rem !important; border-radius: 50px !important; text-decoration: none;">
                                    Fix
                                </a>
                            `;
                        } else {
                            approvalCell = '';
                        }
                    } else if (msg.channel_id === 'manual_override' && msg.approval_status === 'approved') {
                        approvalCell = `
                            <span class="d-inline-flex align-items-center justify-content-center" style="height: 24px !important; font-size: 0.72rem !important; padding: 0 0.8rem !important; border-radius: 50px !important; background-color: #16a34a !important; color: #ffffff !important; border: 1px solid #15803d !important; font-weight: 700 !important; line-height: 1 !important;">
                                Fixed
                            </span>
                        `;
                    } else if (msg.approval_status === 'pending' || msg.approval_status === 'pending_edit') {
                        approvalCell = `
                            <div class="d-inline-flex gap-2 align-items-center approval-cell-container justify-content-center">
                                <button type="button" class="btn-circle-blue btn-approve-override" data-message-id="${msg.id}" data-custom-tooltip="Approve" data-tooltip-placement="top">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                                <button type="button" class="btn-circle-red-filled btn-reject-override" data-message-id="${msg.id}" data-custom-tooltip="Reject" data-tooltip-placement="top">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        `;
                    } else if (msg.approval_status === 'approved') {
                        approvalCell = `
                            <span class="row-badge-green d-inline-flex align-items-center btn-reset-decision-trigger" data-message-id="${msg.id}" style="height: 24px !important; font-size: 0.72rem !important; padding: 0 0.8rem !important; border-radius: 50px !important; border: 1px solid rgba(16, 185, 129, 0.3) !important; cursor: pointer;" data-custom-tooltip="Click to change decision" data-tooltip-placement="top">
                                Approved
                            </span>
                        `;
                    } else if (msg.approval_status === 'rejected') {
                        approvalCell = `
                            <span class="row-badge-red d-inline-flex align-items-center btn-reset-decision-trigger" data-message-id="${msg.id}" style="height: 24px !important; font-size: 0.72rem !important; padding: 0 0.8rem !important; border-radius: 50px !important; border: 1px solid rgba(239, 68, 68, 0.3) !important; cursor: pointer;" data-custom-tooltip="Click to change decision" data-tooltip-placement="top">
                                Rejected
                            </span>
                        `;
                    }

                    let breakExceededIndicator = '';
                    const lowerText = (msg.message_text || '').toLowerCase().trim();
                    const isBreakOrBack = /^(break|brb|back)\b/i.test(lowerText);
                    if (msg.break_exceeded && isBreakOrBack) {
                        const isCheckIn = lowerText.includes('back');
                        const tooltipText = isCheckIn
                            ? 'Resumed session after an unpaid break exceeding daily balance.'
                            : 'Exceeded daily break balance (unpaid after balance exhausted).';
                        breakExceededIndicator = `
                            <span class="ms-2 text-warning cursor-pointer" data-custom-tooltip="${tooltipText}" data-tooltip-placement="top">
                                <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 0.85rem;"></i>
                            </span>
                        `;
                    }

                    return `
                        <tr data-message-id="${msg.id}" class="${highlightClass} ${autoAppendedClass}">
                            <td class="ps-4 fw-semibold text-white text-break">
                                <span>${msg.message_text}</span>
                                ${editBadge}
                                ${breakExceededIndicator}
                            </td>
                            <td>${userCell}</td>
                            <td><code class="text-white opacity-75 px-3 py-1 rounded-pill bg-dark border border-secondary border-opacity-25" style="border-radius: 50px !important; font-size: 0.8rem; display: inline-block;">${msg.channel_id}</code></td>
                            <td class="text-muted">${msg.formatted_time}</td>
                            <td class="text-center">${approvalCell}</td>
                        </tr>
                    `;
                }

                function renderDateHeaderHtml(dateStr) {
                    return `
                        <tr class="date-header-row" data-date="${dateStr}">
                            <td colspan="5" class="py-3 px-4" style="background-color: rgba(255, 255, 255, 0.01); border-bottom: 1px solid rgba(255, 255, 255, 0.03);">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1" style="border-top: 1px solid rgba(255, 255, 255, 0.08);"></div>
                                    <span class="mx-3 text-white opacity-75 small fw-semibold text-uppercase px-3 py-1 rounded-pill" style="letter-spacing: 0.08em; font-size: 0.72rem; background-color: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.15);">${dateStr}</span>
                                    <div class="flex-grow-1" style="border-top: 1px solid rgba(255, 255, 255, 0.08);"></div>
                                </div>
                            </td>
                        </tr>
                    `;
                }

                function renderMessagesTable(data, append = false) {
                    const tbody = $('#message-table-body');

                    if (!append && data.length === 0) {
                        tbody.html(`
                            <tr id="empty-row">
                                <td colspan="5" class="text-center text-white opacity-50 py-5">
                                    <div class="mb-2"><i class="bi bi-chat-left-dots fs-3"></i></div>
                                    No messages found matching the filters.
                                </td>
                            </tr>
                        `);
                        return;
                    }

                    if (!append) {
                        tbody.empty();
                    }

                    let html = '';
                    let lastDate = append ? tbody.find('tr[data-message-id]:last').prevAll('.date-header-row:first').data('date') : null;
                    if (append && !lastDate) {
                        const lastMsgId = tbody.find('tr[data-message-id]:last').data('message-id');
                        const lastMsg = slackMessagesCache.find(m => m.id == lastMsgId);
                        if (lastMsg) lastDate = lastMsg.formatted_date;
                    }

                    data.forEach(function(msg) {
                        const currentDate = msg.formatted_date;
                        if (lastDate !== currentDate) {
                            html += renderDateHeaderHtml(currentDate);
                            lastDate = currentDate;
                        }
                        html += renderRowHtml(msg);
                    });

                    if (append) {
                        tbody.append(html);
                    } else {
                        tbody.html(html);
                    }

                    let hasMapped = slackMessagesCache.some(msg => msg.display_name !== msg.slack_user_id);
                    $('#user-column-header').text(hasMapped ? 'Employee' : 'Slack User ID');
                }

                function getFilterParams() {
                    return {
                        employee: $('#filter-employee').val(),
                        period: $('#filter-date-period').val(),
                        start_date: $('#filter-start-date').val(),
                        end_date: $('#filter-end-date').val(),
                        approval_required: $('#filter-approval-required').is(':checked') ? 1 : 0
                    };
                }

                function loadFilteredMessages() {
                    isFilterFetching = true;
                    const tbody = $('#message-table-body');
                    tbody.html(`
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="spinner-border text-primary spinner-border-sm me-2" role="status"></div>
                                <span class="text-white opacity-50 small">Loading messages...</span>
                            </td>
                        </tr>
                    `);

                    const params = getFilterParams();

                    $.ajax({
                        url: "{{ route('super-admin.session-management.api.messages') }}",
                        method: "GET",
                        data: params,
                        success: function(response) {
                            slackMessagesCache = response.messages;
                            nextCursor = response.next_cursor;
                            hasMore = response.has_more;
                            maxSeenId = Math.max(maxSeenId, response.max_id || 0);

                            renderMessagesTable(slackMessagesCache, false);
                            toggleScrollLoadingIndicator(false);
                        },
                        error: function(err) {
                            console.error('Error fetching filtered messages:', err);
                        },
                        complete: function() {
                            isFilterFetching = false;
                        }
                    });
                }

                function fetchMoreMessages() {
                    if (!hasMore || isLoadingMore || !nextCursor) return;
                    isLoadingMore = true;
                    toggleScrollLoadingIndicator(true);

                    const params = getFilterParams();
                    params.cursor = nextCursor;

                    $.ajax({
                        url: "{{ route('super-admin.session-management.api.messages') }}",
                        method: "GET",
                        data: params,
                        success: function(response) {
                            if (response.messages && response.messages.length > 0) {
                                slackMessagesCache = slackMessagesCache.concat(response.messages);
                                renderMessagesTable(response.messages, true);
                            }
                            nextCursor = response.next_cursor;
                            hasMore = response.has_more;
                        },
                        error: function(err) {
                            console.error('Error fetching more messages:', err);
                        },
                        complete: function() {
                            isLoadingMore = false;
                            toggleScrollLoadingIndicator(false);
                        }
                    });
                }

                function toggleScrollLoadingIndicator(show) {
                    let loader = $('#scroll-loader-row');
                    if (show) {
                        if (loader.length === 0) {
                            $('#message-table-body').append(`
                                <tr id="scroll-loader-row">
                                    <td colspan="5" class="text-center py-3">
                                        <div class="spinner-border spinner-border-sm text-secondary me-2" role="status"></div>
                                        <span class="text-white opacity-50 small">Loading older messages...</span>
                                    </td>
                                </tr>
                            `);
                        }
                    } else {
                        loader.remove();
                    }
                }

                function pollLiveStream() {
                    if (isFilterFetching) return;

                    const params = getFilterParams();
                    params.since_id = maxSeenId;

                    const pendingIds = [];
                    $('#message-table-body tr[data-message-id]').each(function() {
                        const id = $(this).data('message-id');
                        const msg = slackMessagesCache.find(m => m.id == id);
                        if (msg && (msg.approval_status === 'pending' || msg.approval_status === 'pending_edit')) {
                            pendingIds.push(id);
                        }
                    });
                    if (pendingIds.length > 0) {
                        params.visible_ids = pendingIds.join(',');
                    }

                    $.ajax({
                        url: "{{ route('super-admin.session-management.api.messages') }}",
                        method: "GET",
                        data: params,
                        success: function(response) {
                            $('#last-update').text('Last synced: ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));

                            let needsReRender = false;
                            let newAddedIds = new Set();

                            // 1. Process deleted message IDs
                            if (response.deleted_ids && response.deleted_ids.length > 0) {
                                response.deleted_ids.forEach(function(deletedId) {
                                    const hadMsg = slackMessagesCache.some(m => m.id == deletedId);
                                    if (hadMsg) {
                                        slackMessagesCache = slackMessagesCache.filter(m => m.id != deletedId);
                                        needsReRender = true;
                                    }
                                });
                            }

                            // 2. Process newly created messages
                            if (response.new_messages && response.new_messages.length > 0) {
                                response.new_messages.forEach(function(msg) {
                                    if (!slackMessagesCache.some(m => m.id === msg.id)) {
                                        slackMessagesCache.push(msg);
                                        newAddedIds.add(msg.id);
                                        needsReRender = true;
                                    }
                                });
                                maxSeenId = Math.max(maxSeenId, response.max_id || 0);
                            }

                            // 3. Process status updates on existing messages
                            if (response.updated_messages && response.updated_messages.length > 0) {
                                response.updated_messages.forEach(function(updatedMsg) {
                                    const idx = slackMessagesCache.findIndex(m => m.id === updatedMsg.id);
                                    if (idx !== -1) {
                                        if (slackMessagesCache[idx].approval_status !== updatedMsg.approval_status) {
                                            slackMessagesCache[idx] = updatedMsg;
                                            const existingRow = $(`#message-table-body tr[data-message-id="${updatedMsg.id}"]`);
                                            if (existingRow.length > 0) {
                                                existingRow.replaceWith(renderRowHtml(updatedMsg));
                                            }
                                        }
                                    }
                                });
                            }

                            // 4. If messages were added or deleted, sort and re-render cleanly
                            if (needsReRender) {
                                // Sort by date/time descending, then ID descending
                                slackMessagesCache.sort(function(a, b) {
                                    const timeA = new Date(a.message_at_raw || a.formatted_date).getTime();
                                    const timeB = new Date(b.message_at_raw || b.formatted_date).getTime();
                                    if (timeB !== timeA) return timeB - timeA;
                                    return b.id - a.id;
                                });

                                renderMessagesTable(slackMessagesCache, false);

                                // Apply smooth highlight animation to newly added rows
                                newAddedIds.forEach(function(id) {
                                    const row = $(`#message-table-body tr[data-message-id="${id}"]`);
                                    row.addClass('new-row-highlight');
                                });
                            }
                        },
                        error: function(err) {
                            console.error('Error polling live stream:', err);
                        }
                    });
                }

                $('.scrollable-table-container').on('scroll', function() {
                    const container = $(this);
                    const scrollPosition = container.scrollTop() + container.innerHeight();
                    const scrollThreshold = container[0].scrollHeight - 150;

                    if (scrollPosition >= scrollThreshold) {
                        fetchMoreMessages();
                    }
                });

                $('#filter-employee, #filter-approval-required').on('change', function() {
                    loadFilteredMessages();
                });

                $('#filter-start-date, #filter-end-date').on('input change', function() {
                    if ($(this).val()) {
                        $('#filter-date-period').val('all');
                    }
                    loadFilteredMessages();
                });

                $('#filter-date-period').on('change', function() {
                    if ($(this).val() !== 'all') {
                        $('#filter-start-date').val('');
                        $('#filter-end-date').val('');
                    }
                    loadFilteredMessages();
                });

                $('#clear-filters-btn').on('click', function() {
                    $('#filter-employee').val('');
                    $('#filter-date-period').val('all');
                    $('#filter-start-date').val('');
                    $('#filter-end-date').val('');
                    $('#filter-approval-required').prop('checked', false);
                    loadFilteredMessages();
                });

                // Handle click on "(edited)" trigger to display the edit history timeline modal
                $(document).on('click', '.edit-history-trigger', function() {
                    const messageId = $(this).data('message-id');
                    const msg = slackMessagesCache.find(m => m.id == messageId);
                    
                    if (!msg || !msg.edits || msg.edits.length === 0) return;
                    
                    const chronologicalEdits = [...msg.edits].reverse();
                    let html = '';
                    
                    const originalText = chronologicalEdits[0].previous_text;
                    html += `
                        <div class="mb-4 position-relative" style="padding-left: 20px;">
                            <span class="position-absolute rounded-circle bg-success" style="width: 12px; height: 12px; border: 2px solid #181818; top: 5px; left: -6px; z-index: 10; box-shadow: 0 0 6px rgba(40, 167, 69, 0.4);"></span>
                            <div class="small text-white opacity-50 mb-1 fw-semibold" style="font-size: 0.78rem;">Original Message &bull; ${msg.formatted_original_time}</div>
                            <div class="text-white p-2 rounded border border-secondary border-opacity-25 font-monospace" style="background-color: #121212; border-color: rgba(255,255,255,0.08) !important; font-size: 0.88rem;">${originalText}</div>
                        </div>
                    `;
                    
                    chronologicalEdits.forEach((edit, index) => {
                        const isLatest = (index === chronologicalEdits.length - 1);
                        const markerColor = isLatest ? 'bg-warning' : 'bg-secondary';
                        const titleLabel = isLatest ? 'Current Message (Latest Edit)' : `Edit #${index + 1}`;
                        const titleClass = isLatest ? 'text-warning' : 'text-white opacity-50';
                        const shadowColor = isLatest ? 'rgba(255, 193, 7, 0.4)' : 'rgba(108, 117, 125, 0.4)';

                        html += `
                            <div class="mb-4 position-relative" style="padding-left: 20px;">
                                <span class="position-absolute rounded-circle ${markerColor}" style="width: 12px; height: 12px; border: 2px solid #181818; top: 5px; left: -6px; z-index: 10; box-shadow: 0 0 6px ${shadowColor};"></span>
                                <div class="small ${titleClass} mb-1 fw-semibold" style="font-size: 0.78rem;">${titleLabel} &bull; ${edit.formatted_edited_time}</div>
                                <div class="text-white p-2 rounded border border-secondary border-opacity-25 font-monospace" style="background-color: #121212; border-color: rgba(255,255,255,0.08) !important; font-size: 0.88rem;">${edit.new_text}</div>
                            </div>
                        `;
                    });
                    
                    $('#edit-timeline').html(html);
                    
                    const modalEl = document.getElementById('editHistoryModal');
                    document.body.appendChild(modalEl);
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                });

                function showToast(message, isError = false) {
                    if (window.showToast) {
                        window.showToast(message, isError ? 'danger' : 'success');
                    } else {
                        alert(message);
                    }
                }

                $(document).on('click', '.btn-approve-override', function() {
                    const btn = $(this);
                    const messageId = btn.data('message-id');
                    
                    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');

                    $.ajax({
                        url: `/super-admin/session-management/messages/${messageId}/approve`,
                        method: "POST",
                        data: {
                            _token: "{{ csrf_token() }}"
                        },
                        success: function(response) {
                            showToast(response.message);
                            pollLiveStream();
                        },
                        error: function(xhr) {
                            btn.prop('disabled', false).text('Approve');
                            const errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Error approving request.';
                            showToast(errorMsg, true);
                        }
                    });
                });

                $(document).on('click', '.btn-reject-override', function() {
                    const btn = $(this);
                    const messageId = btn.data('message-id');
                    
                    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');

                    $.ajax({
                        url: `/super-admin/session-management/messages/${messageId}/reject`,
                        method: "POST",
                        data: {
                            _token: "{{ csrf_token() }}"
                        },
                        success: function(response) {
                            showToast(response.message);
                            pollLiveStream();
                        },
                        error: function(xhr) {
                            btn.prop('disabled', false).text('Reject');
                            const errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Error rejecting request.';
                            showToast(errorMsg, true);
                        }
                    });
                });

                let resetMessageId = null;

                $(document).on('click', '.btn-reset-decision-trigger', function() {
                    resetMessageId = $(this).data('message-id');
                    const modalEl = document.getElementById('confirmResetModal');
                    document.body.appendChild(modalEl);
                    const confirmModal = new bootstrap.Modal(modalEl);
                    confirmModal.show();
                });

                $('#btnConfirmReset').on('click', function() {
                    if (!resetMessageId) return;

                    const btn = $(this);
                    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>');

                    $.ajax({
                        url: `/super-admin/session-management/messages/${resetMessageId}/reset`,
                        method: "POST",
                        data: {
                            _token: "{{ csrf_token() }}"
                        },
                        success: function(response) {
                            showToast(response.message);
                            const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmResetModal'));
                            if (confirmModal) confirmModal.hide();
                            pollLiveStream();
                        },
                        error: function(xhr) {
                            const errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Error resetting decision.';
                            showToast(errorMsg, true);
                        },
                        complete: function() {
                            btn.prop('disabled', false).text('Yes, Proceed');
                        }
                    });
                });

                // Initial preselection if employee query parameter exists
                const urlParams = new URLSearchParams(window.location.search);
                const preselectEmployee = urlParams.get('employee_id');
                if (preselectEmployee && $('#filter-employee option[value="' + preselectEmployee + '"]').length > 0) {
                    $('#filter-employee').val(preselectEmployee);
                }

                setInterval(pollLiveStream, 5000);
            });
        </script>
    @endpush
</x-app-layout>
