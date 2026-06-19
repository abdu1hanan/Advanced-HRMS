<x-app-layout>
    <x-slot name="header">Beetle Sync</x-slot>

    <!-- Main Table Card -->
    <div class="spotify-card mb-4 mt-3">
        <div class="card-header-area px-4 pt-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-2">
                    <h6 class="text-uppercase text-white fw-semibold small mb-0 opacity-75" style="letter-spacing: 0.05em;">Incoming Message Feed</h6>
                    <i class="bi bi-info-circle text-white opacity-75 cursor-pointer" style="font-size: 0.85rem;" data-custom-tooltip="You can change your decision by clicking the badge again" data-tooltip-placement="top"></i>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span id="last-update" class="row-badge-warning d-inline-flex align-items-center" style="height: 28px !important; font-size: 0.72rem !important; padding: 0 0.8rem !important; border-radius: 50px !important; border: 1px solid rgba(245, 158, 11, 0.3) !important;">
                        Last synced: {{ now()->timezone('Asia/Karachi')->format('h:i A') }}
                    </span>
                    <span class="row-badge-green d-inline-flex align-items-center" style="height: 28px !important; font-size: 0.72rem !important; padding: 0 0.8rem !important; border-radius: 50px !important; border: 1px solid rgba(16, 185, 129, 0.3) !important; overflow: hidden;">
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
                    @forelse($messages as $msg)
                        @php
                            $currentDate = $msg->message_at->timezone('Asia/Karachi')->format('F d, Y');
                        @endphp
                        @if($lastDate !== $currentDate)
                            <tr>
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
                        <tr data-message-id="{{ $msg->id }}" class="{{ $msg->is_auto_appended ? 'auto-appended-row' : '' }}">
                            <td class="ps-4 fw-semibold text-white text-break">
                                <span>{{ $msg->approval_status === 'pending_edit' ? $msg->pending_message_text : $msg->message_text }}</span>
                                @if($msg->is_edited)
                                    <span class="text-white opacity-75 small ms-1 edit-history-trigger" style="font-size: 0.75rem; cursor: pointer; text-decoration: underline;" data-message-id="{{ $msg->id }}">(edited)</span>
                                @endif
                                @if($msg->break_exceeded)
                                    @php
                                        $lowerText = strtolower($msg->message_text);
                                        $isCheckIn = str_contains($lowerText, 'back');
                                        $tooltipText = $isCheckIn 
                                            ? 'This break exceeded 10 minutes and was considered as a sign in.' 
                                            : 'This break exceeded 10 minutes and was considered as a sign out.';
                                    @endphp
                                    <span class="ms-2 text-warning cursor-pointer" data-custom-tooltip="{{ $tooltipText }}" data-tooltip-placement="top">
                                        <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 0.85rem;"></i>
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($msg->mapping && ($msg->mapping->slack_username || $msg->mapping->user))
                                    <span class="fw-semibold text-white">
                                        {{ $msg->mapping->slack_username ? $msg->mapping->slack_username : $msg->mapping->user->name }}
                                    </span>
                                @else
                                    <code class="text-white opacity-75 px-3 py-1 rounded-pill bg-dark border border-secondary border-opacity-25" style="border-radius: 50px !important; font-size: 0.8rem; display: inline-block;">{{ $msg->slack_user_id }}</code>
                                @endif
                            </td>
                            <td><code class="text-white opacity-75 px-3 py-1 rounded-pill bg-dark border border-secondary border-opacity-25" style="border-radius: 50px !important; font-size: 0.8rem; display: inline-block;">{{ $msg->channel_id }}</code></td>
                            <td class="text-muted">{{ $msg->message_at->timezone('Asia/Karachi')->format('M d, Y h:i A') }}</td>
                            <td class="text-center">
                                @if($msg->channel_id === 'manual_override')
                                    @if($msg->mapping && $msg->mapping->user_id)
                                        @php
                                            $localTime = $msg->message_at->timezone('Asia/Karachi');
                                            $fixUrl = route('super-admin.session-management.track-record', [
                                                'user_id' => $msg->mapping->user_id,
                                                'period' => 'month',
                                                'month' => $localTime->month,
                                                'year' => $localTime->year,
                                                'edit' => 'true'
                                            ]);
                                        @endphp
                                        <a href="{{ $fixUrl }}" class="row-btn-blue d-inline-flex align-items-center justify-content-center" style="height: 24px !important; font-size: 0.72rem !important; padding: 0 0.8rem !important; border-radius: 50px !important; text-decoration: none;">
                                            Fix
                                        </a>
                                    @else
                                        <span class="text-white opacity-50 small">—</span>
                                    @endif
                                @elseif($msg->approval_status === 'pending' || $msg->approval_status === 'pending_edit')
                                    <div class="d-inline-flex gap-2 align-items-center approval-cell-container justify-content-center">
                                        <button type="button" class="btn-circle-blue btn-approve-override" data-message-id="{{ $msg->id }}" data-custom-tooltip="Approve" data-tooltip-placement="top">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                        <button type="button" class="btn-circle-red-filled btn-reject-override" data-message-id="{{ $msg->id }}" data-custom-tooltip="Reject" data-tooltip-placement="top">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                @elseif($msg->approval_status === 'approved')
                                    <span class="row-badge-green d-inline-flex align-items-center btn-reset-decision-trigger" data-message-id="{{ $msg->id }}" style="height: 24px !important; font-size: 0.72rem !important; padding: 0 0.8rem !important; border-radius: 50px !important; border: 1px solid rgba(16, 185, 129, 0.3) !important; cursor: pointer;" data-custom-tooltip="Click to change decision" data-tooltip-placement="top">
                                        Approved
                                    </span>
                                @elseif($msg->approval_status === 'rejected')
                                    <span class="row-badge-red d-inline-flex align-items-center btn-reset-decision-trigger" data-message-id="{{ $msg->id }}" style="height: 24px !important; font-size: 0.72rem !important; padding: 0 0.8rem !important; border-radius: 50px !important; border: 1px solid rgba(239, 68, 68, 0.3) !important; cursor: pointer;" data-custom-tooltip="Click to change decision" data-tooltip-placement="top">
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
                let isFirstLoad = true;
                
                // Initialize the messages cache from PHP
                window.slackMessagesCache = @json($initialMessages);
                
                // Polling function to keep page updated live
                function fetchMessages() {

                    $.ajax({
                        url: "{{ route('super-admin.session-management.api.messages') }}",
                        method: "GET",
                        success: function(data) {
                            $('#last-update').text('Last synced: ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));
                            
                            // Save the polled messages into global cache
                            window.slackMessagesCache = data;

                            if (data.length === 0) {
                                return;
                            }

                            // If there was an empty row, clear it
                            $('#empty-row').remove();

                            const tbody = $('#message-table-body');
                            
                            // Track existing IDs on screen to identify new ones
                            let existingIds = [];
                            tbody.find('tr').each(function() {
                                const id = $(this).data('message-id');
                                if (id) existingIds.push(parseInt(id));
                            });

                            // Re-build table rows with highlighting on new ones
                            let html = '';
                            let hasMapped = false;
                            let lastDate = null;
                            
                            data.forEach(function(msg) {
                                const isNew = !isFirstLoad && !existingIds.includes(msg.id);
                                const highlightClass = isNew ? 'new-row-highlight' : '';
                                
                                const currentDate = msg.formatted_date;
                                if (lastDate !== currentDate) {
                                    html += `
                                        <tr>
                                            <td colspan="5" class="py-3 px-4" style="background-color: rgba(255, 255, 255, 0.01); border-bottom: 1px solid rgba(255, 255, 255, 0.03);">
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-grow-1" style="border-top: 1px solid rgba(255, 255, 255, 0.08);"></div>
                                                    <span class="mx-3 text-white opacity-75 small fw-semibold text-uppercase px-3 py-1 rounded-pill" style="letter-spacing: 0.08em; font-size: 0.72rem; background-color: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.15);">${currentDate}</span>
                                                    <div class="flex-grow-1" style="border-top: 1px solid rgba(255, 255, 255, 0.08);"></div>
                                                </div>
                                            </td>
                                        </tr>
                                    `;
                                    lastDate = currentDate;
                                }
                                
                                let editBadge = msg.is_edited 
                                    ? `<span class="text-white opacity-75 small ms-1 edit-history-trigger" style="font-size: 0.75rem; cursor: pointer; text-decoration: underline;" data-message-id="${msg.id}">(edited)</span>` 
                                    : '';

                                if (msg.display_name !== msg.slack_user_id) {
                                    hasMapped = true;
                                }

                                let userCell = '';
                                if (msg.display_name !== msg.slack_user_id) {
                                    userCell = `<span class="fw-semibold text-white">${msg.display_name}</span>`;
                                } else {
                                    userCell = `<code class="text-white opacity-75 px-3 py-1 rounded-pill bg-dark border border-secondary border-opacity-25" style="border-radius: 50px !important; font-size: 0.8rem; display: inline-block;">${msg.slack_user_id}</code>`;
                                }

                                let approvalCell = '';
                                if (msg.channel_id === 'manual_override') {
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
                                } else {
                                    approvalCell = '';
                                }

                                let breakExceededIndicator = '';
                                if (msg.break_exceeded) {
                                    const lowerText = msg.message_text.toLowerCase();
                                    const isCheckIn = lowerText.includes('back');
                                    const tooltipText = isCheckIn 
                                        ? 'This break exceeded 10 minutes and was considered as a sign in.' 
                                        : 'This break exceeded 10 minutes and was considered as a sign out.';
                                    breakExceededIndicator = `
                                        <span class="ms-2 text-warning cursor-pointer" data-custom-tooltip="${tooltipText}" data-tooltip-placement="top">
                                            <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 0.85rem;"></i>
                                        </span>
                                    `;
                                }

                                const autoAppendedClass = msg.is_auto_appended ? 'auto-appended-row' : '';
                                html += `
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
                            });

                            // Dynamically update the column header
                            $('#user-column-header').text(hasMapped ? 'Employee' : 'Slack User ID');

                            tbody.html(html);
                            isFirstLoad = false;
                        },
                        error: function(err) {
                            console.error('Error fetching Slack stream data:', err);
                        }
                    });
                }

                // Handle click on "(edited)" trigger to display the edit history timeline modal
                $(document).on('click', '.edit-history-trigger', function() {
                    const messageId = $(this).data('message-id');
                    const msg = window.slackMessagesCache.find(m => m.id == messageId);
                    
                    if (!msg || !msg.edits || msg.edits.length === 0) return;
                    
                    // Clone and reverse the edits array to display them chronologically (oldest first)
                    const chronologicalEdits = [...msg.edits].reverse();
                    
                    let html = '';
                    
                    // 1. Render original message state
                    const originalText = chronologicalEdits[0].previous_text;
                    html += `
                        <div class="mb-4 position-relative" style="padding-left: 20px;">
                            <span class="position-absolute rounded-circle bg-success" style="width: 12px; height: 12px; border: 2px solid #181818; top: 5px; left: -6px; z-index: 10; box-shadow: 0 0 6px rgba(40, 167, 69, 0.4);"></span>
                            <div class="small text-white opacity-50 mb-1 fw-semibold" style="font-size: 0.78rem;">Original Message &bull; ${msg.formatted_time}</div>
                            <div class="text-white p-2 rounded border border-secondary border-opacity-25 font-monospace" style="background-color: #121212; border-color: rgba(255,255,255,0.08) !important; font-size: 0.88rem;">${originalText}</div>
                        </div>
                    `;
                    
                    // 2. Render each consecutive edit transition
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
                    
                    // Display the modal securely by appending it to body first to fix z-index stacking bugs
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

                // Handle approve override button click
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
                            fetchMessages();
                        },
                        error: function(xhr) {
                            btn.prop('disabled', false).text('Approve');
                            const errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Error approving request.';
                            showToast(errorMsg, true);
                        }
                    });
                });

                // Handle reject override button click
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
                            fetchMessages();
                        },
                        error: function(xhr) {
                            btn.prop('disabled', false).text('Reject');
                            const errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Error rejecting request.';
                            showToast(errorMsg, true);
                        }
                    });
                });

                // Initialize Bootstrap Tooltips
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });

                let resetMessageId = null;

                // Handle click on decision badge to open custom confirmation modal
                $(document).on('click', '.btn-reset-decision-trigger', function() {
                    resetMessageId = $(this).data('message-id');
                    const modalEl = document.getElementById('confirmResetModal');
                    document.body.appendChild(modalEl);
                    const confirmModal = new bootstrap.Modal(modalEl);
                    confirmModal.show();
                });

                // Handle confirm reset button click
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
                            fetchMessages();
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

                // Initial fetch and schedule every 4 seconds
                fetchMessages();
                setInterval(fetchMessages, 4000);
            });
        </script>
    @endpush
</x-app-layout>
