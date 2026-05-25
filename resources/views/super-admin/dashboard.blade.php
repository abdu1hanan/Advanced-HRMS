<x-app-layout>
    <x-slot name="header">Super Admin Dashboard</x-slot>

    <!-- Top Row: Minimal glass layout side-by-side -->
    <div class="row g-4 mb-4 align-items-stretch">
        <!-- Profile Card (Left) -->
        <div class="col-lg-5">
            <div class="spotify-card dashboard-top-card p-4 d-flex flex-column justify-content-center">
                <div class="d-flex align-items-center grid-separator">
                    <div class="bg-white text-dark rounded-circle p-3 me-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 48px; height: 48px;">
                        <i class="bi bi-shield-lock-fill fs-4"></i>
                    </div>
                    <div>
                        <!-- Less bold username -->
                        <h4 class="fw-semibold mb-0 text-white" style="letter-spacing: -0.03em; line-height: 1.1;">{{ auth()->user()->name }}</h4>
                        <span class="text-white fw-medium small opacity-75">System Administrator</span>
                    </div>
                </div>
                <div class="row g-2 text-start mt-1">
                    <div class="col-6">
                        <label class="text-white small opacity-50 d-block mb-0">Role Hierarchy</label>
                        <strong class="text-white fw-semibold">Super Admin</strong>
                    </div>
                    <div class="col-6">
                        <label class="text-white small opacity-50 d-block mb-0">Security Level</label>
                        <strong class="text-white fw-semibold">System Level 5</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dynamic Statistics Widget (Right) -->
        <div class="col-lg-7">
            <div class="row g-3 h-100">
                <div class="col-sm-4">
                    <div class="spotify-card dashboard-top-card p-4 d-flex flex-column justify-content-between">
                        <div>
                            <span class="text-white small text-uppercase fw-semibold opacity-75" style="letter-spacing: 0.05em;">Active Users</span>
                            <h2 class="display-6 fw-bold text-white mt-1 mb-0">{{ $stats['users'] }}</h2>
                        </div>
                        <div class="grid-separator"></div>
                        <div class="d-flex align-items-center text-white small opacity-75">
                            <i class="bi bi-people-fill me-2 fs-5"></i>
                            <span class="fw-medium">Portal accounts</span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="spotify-card dashboard-top-card p-4 d-flex flex-column justify-content-between">
                        <div>
                            <span class="text-white small text-uppercase fw-semibold opacity-75" style="letter-spacing: 0.05em;">Departments</span>
                            <h2 class="display-6 fw-bold text-white mt-1 mb-0">{{ $stats['departments'] }}</h2>
                        </div>
                        <div class="grid-separator"></div>
                        <div class="d-flex align-items-center text-white small opacity-75">
                            <i class="bi bi-diagram-3-fill me-2 fs-5"></i>
                            <span class="fw-medium">Operations units</span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="spotify-card dashboard-top-card p-4 d-flex flex-column justify-content-between">
                        <div>
                            <span class="text-white small text-uppercase fw-semibold opacity-75" style="letter-spacing: 0.05em;">Pending Leaves</span>
                            <h2 class="display-6 fw-bold text-white mt-1 mb-0">{{ $stats['pending_leaves'] }}</h2>
                        </div>
                        <div class="grid-separator"></div>
                        <div class="d-flex align-items-center text-white small opacity-75">
                            <i class="bi bi-clock-history me-2 fs-5"></i>
                            <span class="fw-medium">Requires action</span>
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
                <i class="bi bi-people"></i> User Directory
            </a>
            <a href="{{ route('super-admin.departments.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-diagram-3"></i> Operations Departments
            </a>
            <a href="{{ route('super-admin.leave-requests.index', ['status' => 'pending']) }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-clock"></i> View Pending Requests
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
        
        <div class="list-group list-group-flush">
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
                            <span class="row-badge"><i class="bi bi-check me-1"></i> Manager Approved</span>
                        @elseif($req->status === 'rejected_by_manager')
                            <span class="row-badge"><i class="bi bi-x me-1"></i> Manager Rejected</span>
                        @else
                            <span class="row-badge"><i class="bi bi-clock-fill me-1"></i> Pending Manager</span>
                        @endif

                        <a href="{{ route('super-admin.leave-requests.index', ['status' => 'pending']) }}" class="row-btn">
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
