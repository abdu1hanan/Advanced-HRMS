<x-app-layout>
    <x-slot name="header">Super Admin Dashboard</x-slot>

    <!-- Top Row: Minimal carbon glass layout side-by-side -->
    <div class="row g-4 mb-4 align-items-stretch">
        <!-- Redesigned Profile Card (Left) -->
        <div class="col-lg-5">
            <div class="spotify-card dashboard-top-card p-4 d-flex flex-column justify-content-center">
                <div class="d-flex align-items-center grid-separator">
                    <div class="rounded-circle border d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px; border-color: rgba(255, 255, 255, 0.15) !important;">
                        <i class="bi bi-shield-lock-fill fs-5 text-white"></i>
                    </div>
                    <div>
                        <h4 class="fw-semibold mb-0 text-white" style="letter-spacing: -0.02em; line-height: 1.1; font-size: 1.2rem;">{{ auth()->user()->name }}</h4>
                        <span class="text-white fw-medium small opacity-50" style="font-size: 0.78rem;">System Administrator</span>
                    </div>
                </div>
                <div class="row g-2 text-start mt-2">
                    <div class="col-6">
                        <label class="text-white small opacity-50 d-block mb-0" style="font-size: 0.7rem;">Role Hierarchy</label>
                        <strong class="text-white fw-semibold" style="font-size: 0.85rem;">Super Admin</strong>
                    </div>
                    <div class="col-6">
                        <label class="text-white small opacity-50 d-block mb-0" style="font-size: 0.7rem;">Security Level</label>
                        <strong class="text-white fw-semibold" style="font-size: 0.85rem;">System Level 5</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Redesigned Minimalist Statistics Widgets (Right) -->
        <div class="col-lg-7">
            <div class="row g-3 h-100">
                <div class="col-sm-4">
                    <div class="spotify-card dashboard-top-card p-4 d-flex flex-column justify-content-between">
                        <div>
                            <span class="text-uppercase fw-bold opacity-50" style="font-size: 0.65rem; letter-spacing: 0.08em; color: rgba(255, 255, 255, 0.7);">Active Users</span>
                            <h2 class="fw-bold text-white mt-2 mb-0" style="font-size: 2.2rem; letter-spacing: -0.04em;">{{ $stats['users'] }}</h2>
                        </div>
                        <div class="d-flex align-items-center small">
                            <div class="d-flex align-items-center justify-content-center rounded-circle me-2" style="width: 24px; height: 24px; background-color: rgba(59, 130, 246, 0.15);">
                                <i class="bi bi-people-fill" style="color: #3b82f6; font-size: 0.85rem;"></i>
                            </div>
                            <span class="fw-medium text-white opacity-50" style="font-size: 0.75rem;">Portal accounts</span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="spotify-card dashboard-top-card p-4 d-flex flex-column justify-content-between">
                        <div>
                            <span class="text-uppercase fw-bold opacity-50" style="font-size: 0.65rem; letter-spacing: 0.08em; color: rgba(255, 255, 255, 0.7);">Departments</span>
                            <h2 class="fw-bold text-white mt-2 mb-0" style="font-size: 2.2rem; letter-spacing: -0.04em;">{{ $stats['departments'] }}</h2>
                        </div>
                        <div class="d-flex align-items-center small">
                            <div class="d-flex align-items-center justify-content-center rounded-circle me-2" style="width: 24px; height: 24px; background-color: rgba(16, 185, 129, 0.15);">
                                <i class="bi bi-diagram-3-fill" style="color: #10b981; font-size: 0.85rem;"></i>
                            </div>
                            <span class="fw-medium text-white opacity-50" style="font-size: 0.75rem;">Operations units</span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="spotify-card dashboard-top-card p-4 d-flex flex-column justify-content-between">
                        <div>
                            <span class="text-uppercase fw-bold opacity-50" style="font-size: 0.65rem; letter-spacing: 0.08em; color: rgba(255, 255, 255, 0.7);">Pending Leaves</span>
                            <h2 class="fw-bold text-white mt-2 mb-0" style="font-size: 2.2rem; letter-spacing: -0.04em;">{{ $stats['pending_leaves'] }}</h2>
                        </div>
                        <div class="d-flex align-items-center small">
                            <div class="d-flex align-items-center justify-content-center rounded-circle me-2" style="width: 24px; height: 24px; background-color: rgba(225, 29, 72, 0.15);">
                                <i class="bi bi-clock-history" style="color: #f43f5e; font-size: 0.85rem;"></i>
                            </div>
                            <span class="fw-medium text-white opacity-50" style="font-size: 0.75rem;">Requires action</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Second Row: Premium Quick Shortcuts & Actions -->
    <div class="spotify-card p-4 mb-4">
        <h6 class="text-uppercase text-white fw-semibold small mb-2 opacity-75" style="letter-spacing: 0.05em;"><i class="bi bi-grid-fill me-2"></i> Quick Actions & Directory Shortcuts</h6>
        <div class="grid-separator"></div>
        <div class="d-flex flex-wrap gap-3 mt-3">
            <a href="{{ route('super-admin.users.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-people" style="color: #3b82f6;"></i> User Directory
            </a>
            <a href="{{ route('super-admin.departments.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-diagram-3" style="color: #10b981;"></i> Operations Departments
            </a>
            <a href="{{ route('super-admin.leave-requests.index', ['status' => 'pending']) }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-clock" style="color: #f43f5e;"></i> View Pending Requests
            </a>
            <a href="{{ route('super-admin.users.index') }}" class="btn btn-primary d-flex align-items-center gap-2">
                <i class="bi bi-person-plus-fill"></i> New Hire Registration
            </a>
        </div>
    </div>

    <!-- Third Row: Active Activity Logs & Pending Streams (Zero margin flush design for edge-to-edge hovers) -->
    <div class="spotify-card flush-list-card mb-4">
        <div class="card-header-area">
            <h6 class="text-uppercase text-white fw-semibold small mb-2 opacity-75" style="letter-spacing: 0.05em;"><i class="bi bi-activity me-2"></i> Pending Leave Request Activity stream</h6>
            <div class="grid-separator"></div>
        </div>
        
        <div class="list-group list-group-flush scrollable-stream">
            @forelse($recentRequests as $req)
                <div class="list-group-item bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-dark text-white rounded-circle p-2.5 d-flex align-items-center justify-content-center border border-secondary" style="width: 40px; height: 40px;">
                            <i class="bi bi-person-fill fs-5"></i>
                        </div>
                        <div>
                            <div class="fw-semibold text-white fs-6" style="letter-spacing: -0.02em; line-height: 1.1;">{{ $req->user->name }}</div>
                            <small class="text-white opacity-75" style="line-height: 1.1;">{{ $req->user->department?->name ?? 'No Department' }} &bull; {{ $req->leaveType->name }}</small>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center gap-4">
                        <div class="text-end">
                            <div class="text-white fw-semibold small" style="letter-spacing: -0.02em; line-height: 1.1;">{{ $req->start_date->format('M d') }} – {{ $req->end_date->format('M d, Y') }}</div>
                            <small class="text-white opacity-50" style="line-height: 1.1;">{{ $req->days_count }} days total</small>
                        </div>
                        
                        @if($req->status === 'approved_by_manager')
                            <span class="row-badge-blue"><i class="bi bi-check me-1"></i> Manager Approved</span>
                        @elseif($req->status === 'rejected_by_manager')
                            <span class="row-badge-red"><i class="bi bi-x me-1"></i> Manager Rejected</span>
                        @else
                            <span class="row-badge-warning"><i class="bi bi-clock-fill me-1"></i> Pending Manager</span>
                        @endif

                        <a href="{{ route('super-admin.leave-requests.index', ['status' => 'pending']) }}" class="row-btn-outline-blue text-decoration-none">
                            Review Request
                        </a>
                    </div>
                </div>
            @empty
                <div class="text-center py-4 text-white opacity-75 small px-3">
                    <div class="mb-2"><i class="bi bi-emoji-smile fs-3"></i></div>
                    All clear! There are no pending leave requests requiring review.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
