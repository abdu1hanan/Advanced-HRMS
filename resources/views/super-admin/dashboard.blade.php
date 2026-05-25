<x-app-layout>
    <x-slot name="header">Super Admin Dashboard</x-slot>

    <!-- Top Row: Profile details and numerical stats side-by-side -->
    <div class="row g-4 mb-4 align-items-stretch">
        <!-- Profile Card (Left) -->
        <div class="col-lg-5">
            <div class="spotify-card h-100 p-4 d-flex flex-column justify-content-center">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-white text-dark rounded-circle p-3 me-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 55px; height: 55px;">
                        <i class="bi bi-shield-lock-fill fs-3"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold mb-0 text-white">{{ auth()->user()->name }}</h4>
                        <span class="text-muted small">System Administrator</span>
                    </div>
                </div>
                <div class="border-top border-secondary pt-3 mt-2 row g-3 text-start">
                    <div class="col-6">
                        <label class="text-muted small d-block mb-0.5">Role Hierarchy</label>
                        <strong class="text-white">Super Admin</strong>
                    </div>
                    <div class="col-6">
                        <label class="text-muted small d-block mb-0.5">Security Level</label>
                        <strong class="text-white">System Level 5</strong>
                    </div>
                    <div class="col-12">
                        <label class="text-muted small d-block mb-0.5">Assigned Domain</label>
                        <span class="badge bg-dark text-white border border-secondary">beetleops.com</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dynamic Statistics Widget (Right) -->
        <div class="col-lg-7">
            <div class="row g-3 h-100">
                <div class="col-sm-4">
                    <div class="spotify-card h-100 p-4 d-flex flex-column justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold letter-spacing-1">Active Users</span>
                            <h2 class="display-5 fw-bold text-white mt-2 mb-0">{{ $stats['users'] }}</h2>
                        </div>
                        <div class="d-flex align-items-center text-success small mt-3">
                            <i class="bi bi-people-fill me-2 fs-5"></i>
                            <span>Portal accounts</span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="spotify-card h-100 p-4 d-flex flex-column justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold letter-spacing-1">Departments</span>
                            <h2 class="display-5 fw-bold text-white mt-2 mb-0">{{ $stats['departments'] }}</h2>
                        </div>
                        <div class="d-flex align-items-center text-info small mt-3">
                            <i class="bi bi-diagram-3-fill me-2 fs-5"></i>
                            <span>Operations units</span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="spotify-card h-100 p-4 d-flex flex-column justify-content-between">
                        <div>
                            <span class="text-muted small text-uppercase fw-bold letter-spacing-1">Pending Leaves</span>
                            <h2 class="display-5 fw-bold text-white mt-2 mb-0">{{ $stats['pending_leaves'] }}</h2>
                        </div>
                        <div class="d-flex align-items-center text-warning small mt-3">
                            <i class="bi bi-clock-history me-2 fs-5"></i>
                            <span>Requires action</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Second Row: Premium Quick Shortcuts & Actions -->
    <div class="spotify-card p-4 mb-4">
        <h6 class="text-uppercase text-muted fw-bold small mb-3"><i class="bi bi-grid-fill me-2"></i> Quick Actions & Directory Shortcuts</h6>
        <div class="d-flex flex-wrap gap-3">
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

    <!-- Third Row: Active Activity Logs & Pending Streams -->
    <div class="spotify-card p-4 mb-4">
        <h6 class="text-uppercase text-muted fw-bold small mb-3"><i class="bi bi-activity me-2"></i> Pending Leave Request Activity stream</h6>
        
        <div class="list-group list-group-flush mt-2">
            @forelse($recentRequests as $req)
                <div class="list-group-item bg-transparent border-bottom border-secondary py-3 px-0 d-flex justify-content-between align-items-center flex-wrap gap-2 transition-all hover-bg-transparent">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-dark text-white rounded-circle p-2.5 d-flex align-items-center justify-content-center border border-secondary" style="width: 42px; height: 42px;">
                            <i class="bi bi-person-fill fs-5"></i>
                        </div>
                        <div>
                            <div class="fw-semibold text-white">{{ $req->user->name }}</div>
                            <small class="text-muted">{{ $req->user->department?->name ?? 'No Department' }} &bull; {{ $req->leaveType->name }}</small>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center gap-4">
                        <div class="text-end">
                            <div class="text-white fw-medium small">{{ $req->start_date->format('M d') }} – {{ $req->end_date->format('M d, Y') }}</div>
                            <small class="text-muted">{{ $req->days_count }} days total</small>
                        </div>
                        
                        @if($req->status === 'approved_by_manager')
                            <span class="badge bg-info text-dark fw-bold px-2.5 py-1.5"><i class="bi bi-check me-1"></i> Manager Approved</span>
                        @elseif($req->status === 'rejected_by_manager')
                            <span class="badge bg-danger text-white fw-bold px-2.5 py-1.5"><i class="bi bi-x me-1"></i> Manager Rejected</span>
                        @else
                            <span class="badge bg-warning text-dark fw-bold px-2.5 py-1.5"><i class="bi bi-clock-fill me-1"></i> Pending Manager</span>
                        @endif

                        <a href="{{ route('super-admin.leave-requests.index', ['status' => 'pending']) }}" class="btn btn-outline-secondary btn-sm rounded-pill py-1 px-3 fs-7">
                            Review Request
                        </a>
                    </div>
                </div>
            @empty
                <div class="text-center py-4 text-muted small">
                    <div class="mb-2 text-success"><i class="bi bi-emoji-smile fs-3"></i></div>
                    All clear! There are no pending leave requests requiring review.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
