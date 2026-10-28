<tr id="leave-row-{{ $req->id }}" class="hover-highlight text-white" style="border-bottom: 1px solid rgba(255, 255, 255, 0.03) !important;">
    <td class="ps-4 fw-medium">{{ $req->leaveType->name }}</td>
    <td><span class="text-white opacity-75">{{ $req->start_date->format('M d') }} - {{ $req->end_date->format('M d, Y') }}</span></td>
    <td><span class="text-white opacity-75">{{ $req->created_at->format('M d, Y') }}</span></td>
    <td><span class="row-badge" style="background: rgba(255, 255, 255, 0.1); color: rgba(255, 255, 255, 0.75); border: transparent;">{{ $req->days_count }}</span></td>
    <td>
        <div>
            @php
                $progress = (int)$req->approval_chain_progress;
                $target   = (int)$req->approval_chain_target;
                $rejLevel = $req->rejectionLevel();
            @endphp
            @if($req->hasPendingCancellation())
                <span class="row-badge-gray">
                    <i class="bi bi-clock-history me-1"></i> Pending Cancellation
                </span>
            @elseif($req->status === 'approved' && $req->is_admin_marked && $req->hasOutstandingChangeRequest() && !$req->hasPendingCancellation())
                {{-- Admin-marked leave with pending change request: replace green badge with purple --}}
                <span class="row-badge-purple">
                    <i class="bi bi-clock-history me-1"></i> Pending Changes
                </span>
            @elseif($req->status === 'approved_by_manager')
                <span class="row-badge-blue">
                    <i class="bi bi-clock-history me-1"></i>
                    @if($progress > 0 && $target > 0)
                        Level {{ $progress }} of {{ $target }} Approved &mdash; Pending CEO
                    @else
                        Manager Approved (Pending CEO Approval)
                    @endif
                </span>
            @elseif($req->status === 'approved')
                <span class="row-badge-green">
                    <i class="bi bi-check-circle-fill me-1"></i> Fully Approved
                </span>
            @elseif($req->status === 'rejected')
                <span class="row-badge-red">
                    <i class="bi bi-x-circle-fill me-1"></i> Rejected
                </span>
            @elseif($req->status === 'rejected_by_manager')
                <span class="row-badge-red">
                    <i class="bi bi-x-circle-fill me-1"></i>
                    @if($rejLevel > 0 && $target > 0)
                        Rejected at Level {{ $rejLevel }} of {{ $target }}
                    @else
                        Rejected by Line Manager
                    @endif
                </span>
            @elseif($req->status === 'cancelled')
                <span class="row-badge-gray">
                    <i class="bi bi-x-circle me-1"></i> Cancelled
                </span>
            @else
                @if($progress > 0 && $target > 0)
                    <span class="row-badge-blue">
                        <i class="bi bi-clock-history me-1"></i> Level {{ $progress }} of {{ $target }} Approved &mdash; Pending Manager
                    </span>
                @else
                    <span class="row-badge-warning">
                        <i class="bi bi-clock-fill me-1"></i>
                        @if($target > 0)
                            Pending Level 1 of {{ $target }} Approval
                        @else
                            Pending Manager Approval
                        @endif
                    </span>
                @endif
            @endif
        </div>
        {{-- Secondary purple change badge: only for non-admin-marked (admin-marked already shows purple as primary) --}}
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
            <span class="badge" style="background: rgba(13, 110, 253, 0.1); color: #3b82f6; border: 1px solid rgba(13, 110, 253, 0.2);" title="Has conversation">
                <i class="bi bi-chat-text-fill me-1"></i> {{ $req->comments->count() }}
            </span>
        @else
            <span class="text-white opacity-25 small">No messages</span>
        @endif
    </td>
    <td class="text-end pe-4">
        <div class="d-flex gap-2 justify-content-end align-items-center">
            <button type="button" class="row-btn-outline-blue px-3 py-1" onclick="openRequestModal({{ $req->id }})" style="font-size: 0.78rem;">
                <i class="bi bi-chat-dots me-1"></i> Chat
            </button>
            @php
                $canCancel = !in_array($req->status, ['cancelled', 'rejected']) 
                    && !($req->change_request_status === 'pending' && !empty($req->change_request['request_cancellation']));
                // Allow change requests on all non-cancelled/rejected leaves (including approved — both admin-marked and normal)
                $canEdit = !in_array($req->status, ['cancelled', 'rejected'])
                    && !$req->hasOutstandingChangeRequest()
                    && !$req->hasPendingCancellation();
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
    <span class="d-dates">{{ $req->start_date->format('M d, Y') }} – {{ $req->end_date->format('M d, Y') }} ({{ $req->formatted_days_count }})</span>
    <span class="d-cur-start d-none">{{ $req->start_date->format('Y-m-d') }}</span>
    <span class="d-cur-end d-none">{{ $req->end_date->format('Y-m-d') }}</span>
    <span class="d-reason">{{ $req->reason }}</span>
    @php
        $orig = $req->change_request['original'] ?? null;
    @endphp
    @if($orig)
        @php
            $origDays = (float)($orig['days_count'] ?? 0);
            $origDaysFormatted = ($origDays == (int)$origDays) ? (int)$origDays : $origDays;
            $origUnit = ($origDaysFormatted === 1) ? 'day' : 'days';
        @endphp
        <span class="d-has-orig-snapshot">1</span>
        <span class="d-orig-type">{{ $orig['leave_type_name'] ?? 'N/A' }}</span>
        <span class="d-orig-dates">{{ \Carbon\Carbon::parse($orig['start_date'])->format('M d, Y') }} – {{ \Carbon\Carbon::parse($orig['end_date'])->format('M d, Y') }} ({{ $origDaysFormatted }} {{ $origUnit }})</span>
        <span class="d-orig-reason">{{ $orig['reason'] ?? 'N/A' }}</span>
        <span class="d-orig-start d-none">{{ $orig['start_date'] ?? '' }}</span>
        <span class="d-orig-end d-none">{{ $orig['end_date'] ?? '' }}</span>
    @else
        <span class="d-has-orig-snapshot">0</span>
    @endif
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
    <span class="d-status-raw">{{ $req->status }}</span>
    <span class="d-has-change">{{ $req->hasOutstandingChangeRequest() ? '1' : '0' }}</span>
    <span class="d-change-status">{{ $req->change_request_status }}</span>
    <span class="d-change-badge-text">{{ $req->changeRequestBadgeText() }}</span>
    <span class="d-is-admin-marked">{{ $req->is_admin_marked ? '1' : '0' }}</span>
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
                  data-action="{{ $entryAct }}"
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
                
                $bgColor = 'bg-secondary bg-opacity-10 text-white border border-secondary border-opacity-25';
                $badgeText = '';
                $badgeClass = '';
                if ($isSA) {
                    $bgColor = 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25';
                    $badgeText = 'CEO / Super Admin';
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
                        <small class="text-white opacity-50" style="font-size: 0.7rem;">{{ $comment->formatted_created_at }}</small>
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
