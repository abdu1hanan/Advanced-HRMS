<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <span>Pending Approvals</span>
            <a href="{{ route('super-admin.users.index') }}" class="row-btn text-decoration-none d-inline-flex align-items-center gap-2" style="font-size: 0.85rem; font-weight: normal; text-transform: none; line-height: 24px; padding: 6px 16px;">
                <i class="bi bi-arrow-left"></i> Back to User Management
            </a>
        </div>
    </x-slot>

    <style>
        .card-footer nav p, 
        .card-footer nav .small.text-muted, 
        .card-footer nav p.text-sm,
        .card-footer nav > div:first-child {
            display: none !important;
        }
        .card-footer nav {
            justify-content: flex-end !important;
        }
        .table-responsive {
            border-radius: 8px;
        }
        .spotify-card table {
            min-width: 980px;
        }
        .btn-unresolved-tab {
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            color: #ffffff !important;
            background-color: transparent !important;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1) !important;
        }
        .btn-unresolved-tab:hover, .btn-unresolved-tab.active {
            border-color: #f59e0b !important;
            background-color: #f59e0b !important;
            color: #000000 !important;
            font-weight: 600 !important;
        }
        .btn-resolved-tab {
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            color: #ffffff !important;
            background-color: transparent !important;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1) !important;
        }
        .btn-resolved-tab:hover, .btn-resolved-tab.active {
            border-color: #e11d48 !important;
            background-color: #e11d48 !important;
            color: #ffffff !important;
            font-weight: 600 !important;
        }
    </style>

    <!-- Filter Tabs (Unresolved / Resolved) & Search -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex gap-2">
            <a href="{{ route('super-admin.users.approvals') }}" class="btn {{ !$isResolved ? 'btn-unresolved-tab active' : 'btn-unresolved-tab' }} py-2 px-4 d-inline-flex align-items-center" style="height: 36px;">
                Unresolved
            </a>
            <a href="{{ route('super-admin.users.approvals', ['status' => 'resolved']) }}" class="btn {{ $isResolved ? 'btn-resolved-tab active' : 'btn-resolved-tab' }} py-2 px-4 d-inline-flex align-items-center" style="height: 36px;">
                Resolved
            </a>
        </div>

        <!-- Oval Search field -->
        <form method="GET" action="{{ route('super-admin.users.approvals') }}" style="width: 250px;">
            @if($isResolved)
                <input type="hidden" name="status" value="resolved">
            @endif
            <div class="position-relative">
                <i class="bi bi-search position-absolute text-white-50" style="left: 14px; top: 50%; transform: translateY(-50%); font-size: 0.9rem;"></i>
                <input type="text" name="search" class="form-control text-white" placeholder="Search by name or email..." value="{{ request('search') }}"
                       style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; font-size: 0.9rem; padding-left: 42px !important; @if(request('search')) padding-right: 40px !important; @endif height: 36px; border-radius: 50px !important;">
                @if(request('search'))
                    <a href="{{ route('super-admin.users.approvals', $isResolved ? ['status' => 'resolved'] : []) }}" class="position-absolute text-white-50 hover-text-white d-flex align-items-center justify-content-center" style="right: 14px; top: 50%; transform: translateY(-50%); z-index: 5; text-decoration: none;">
                        <i class="bi bi-x-circle-fill" style="font-size: 1rem;"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Approvals Table Card -->
    <div class="spotify-card mb-4">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-4" style="width: 22%;">Name</th>
                        <th style="width: 20%;">Email</th>
                        <th style="width: 14%;">Contact</th>
                        <th style="width: 10%;">Role</th>
                        <th style="width: 12%;">Department</th>
                        <th style="width: 14%;">Submitted</th>
                        <th style="width: 12%;">Status</th>
                        <th class="pe-4 text-center" style="width: 10%;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                        <tr>
                            <!-- Name -->
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="rounded-circle border d-flex align-items-center justify-content-center text-white flex-shrink-0"
                                         style="width: 36px; height: 36px; border-color: rgba(255, 255, 255, 0.15) !important; background-color: rgba(255, 255, 255, 0.04); margin-right: 14px !important;">
                                        <span class="fw-semibold small font-monospace">{{ strtoupper(substr($req->user?->name ?? 'U', 0, 1)) }}</span>
                                    </div>
                                    <div class="fw-semibold text-white fs-6" style="line-height: 1.2;">{{ $req->user?->name ?? 'Deleted User' }}</div>
                                </div>
                            </td>

                            <!-- Email -->
                            <td>
                                <span class="text-white opacity-75 small">{{ $req->user?->email ?? '—' }}</span>
                            </td>

                            <!-- Contact (Only show first contact; no emergency number) -->
                            <td>
                                <span class="text-white opacity-75 small">{{ $req->user?->phone ?? '—' }}</span>
                            </td>

                            <!-- Role -->
                            <td>
                                @if($req->user?->isGuest())
                                    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 px-2.5 py-1" style="font-size: 0.72rem; border-radius: 50px;">Guest</span>
                                @else
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2.5 py-1" style="font-size: 0.72rem; border-radius: 50px;">Employee</span>
                                @endif
                            </td>

                            <!-- Department -->
                            <td>
                                <span class="text-white opacity-75 small">{{ $req->user?->department?->name ?? ($req->user?->isGuest() ? 'External' : '—') }}</span>
                            </td>

                            <!-- Submitted (Date only) -->
                            <td>
                                <span class="text-white opacity-90 small">{{ $req->created_at->timezone('Asia/Karachi')->format('M d, Y') }}</span>
                            </td>

                            <!-- Approval Status -->
                            <td>
                                @if($req->isPending())
                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2.5 py-1" style="font-size: 0.72rem; border-radius: 50px;">
                                        Pending
                                    </span>
                                @elseif($req->isApproved())
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2.5 py-1" style="font-size: 0.72rem; border-radius: 50px;">
                                        Approved
                                    </span>
                                @elseif($req->isRejected())
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2.5 py-1" style="font-size: 0.72rem; border-radius: 50px;">
                                        Rejected
                                    </span>
                                @endif
                            </td>

                            <!-- Review Action -->
                            <td class="pe-4 text-center">
                                @if($req->isPending())
                                    <div class="d-inline-flex gap-2 align-items-center justify-content-center">
                                        <form method="POST" action="{{ route('super-admin.users.approvals.approve', $req) }}" class="m-0">
                                            @csrf
                                            <button type="submit" class="btn-circle-blue" data-custom-tooltip="Approve" data-tooltip-placement="top">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                        <button type="button" class="btn-circle-red btn-reject-trigger"
                                                data-action-url="{{ route('super-admin.users.approvals.reject', $req) }}"
                                                data-user-name="{{ $req->user?->name ?? 'User' }}"
                                                data-custom-tooltip="Reject"
                                                data-tooltip-placement="top">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                @else
                                    <span class="text-white opacity-40 small fw-medium">Resolved</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-white opacity-50">
                                @if(!$isResolved)
                                    <i class="bi bi-check2-circle fs-3 text-success d-block mb-2"></i>
                                    <div class="fw-semibold">No Pending Account Approvals</div>
                                    <small>All account requests have been reviewed.</small>
                                @else
                                    <i class="bi bi-clock-history fs-3 text-white opacity-50 d-block mb-2"></i>
                                    <div class="fw-semibold">No Resolved Requests Found</div>
                                    <small>Past approval and rejection records will appear here.</small>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($requests->hasPages())
            <div class="card-footer bg-transparent border-top border-secondary border-opacity-25 py-3">
                {{ $requests->links() }}
            </div>
        @endif
    </div>

    @push('modals')
    <!-- Custom Modal for Rejection Reconfirmation -->
    <div class="modal fade" id="rejectConfirmModal" tabindex="-1" aria-hidden="true" style="z-index: 2060 !important;">
        <div class="modal-dialog modal-dialog-centered" style="z-index: 2061 !important;">
            <div class="modal-content border-0 shadow" style="background-color: #0c0c0c !important; border: 1px solid rgba(255, 255, 255, 0.08) !important;">
                <div class="modal-body p-4 text-center">
                    <div class="mb-3 text-danger">
                        <i class="bi bi-x-circle-fill" style="font-size: 3.5rem;"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-white" style="line-height: 1.3;">
                        Reject Account Request?
                    </h5>
                    <p class="text-white opacity-50 small mb-4">
                        Are you sure you want to reject the account request for <strong class="text-white" id="rejectModalUserName">User</strong>? They will be notified in-app and can submit a new activation request if needed.
                    </p>
                    <div class="d-flex justify-content-center gap-2 mt-4">
                        <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 145px;">Cancel</button>
                        <form id="rejectModalForm" method="POST" action="" class="m-0">
                            @csrf
                            <button type="submit" class="row-btn-red" style="width: 175px;">Reject Request</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endpush

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const rejectModalEl = document.getElementById('rejectConfirmModal');
            if (rejectModalEl) {
                document.body.appendChild(rejectModalEl);
                const rejectModal = new bootstrap.Modal(rejectModalEl);
                const rejectForm = document.getElementById('rejectModalForm');
                const rejectUserName = document.getElementById('rejectModalUserName');

                document.querySelectorAll('.btn-reject-trigger').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const actionUrl = this.getAttribute('data-action-url');
                        const userName = this.getAttribute('data-user-name');
                        if (rejectUserName) {
                            rejectUserName.textContent = userName;
                        }
                        if (rejectForm) {
                            rejectForm.action = actionUrl;
                        }
                        rejectModal.show();
                    });
                });
            }
        });
    </script>
    @endpush
</x-app-layout>
