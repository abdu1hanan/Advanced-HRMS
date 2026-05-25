<x-app-layout>
    <x-slot name="header">Line Manager Dashboard</x-slot>

    <!-- Stat Cards Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-gradient h-100" style="background: linear-gradient(135deg, #4f46e5, #6366f1);">
                <div class="card-body d-flex align-items-center justify-content-between p-4">
                    <div>
                        <h6 class="text-uppercase fw-bold small mb-1">My Subordinates</h6>
                        <h3 class="fw-bold mb-0">{{ $teamCount }}</h3>
                    </div>
                    <div class="bg-white-10 rounded p-3" style="background: rgba(255, 255, 255, 0.15);">
                        <i class="bi bi-people fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm bg-gradient h-100" style="background: linear-gradient(135deg, #e11d48, #f43f5e);">
                <div class="card-body d-flex align-items-center justify-content-between p-4">
                    <div>
                        <h6 class="text-uppercase fw-bold small mb-1">Pending Requests</h6>
                        <h3 class="fw-bold mb-0">{{ $pendingLeaves }}</h3>
                    </div>
                    <div class="bg-white-10 rounded p-3" style="background: rgba(255, 255, 255, 0.15);">
                        <i class="bi bi-inbox fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 bg-light-subtle d-flex flex-column justify-content-center p-3 border">
                <a href="{{ route('admin.team.leaves') }}" class="btn btn-primary btn-lg shadow-sm w-100"><i class="bi bi-check-square me-2"></i>Review Requests</a>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Who's Out Today Widget -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h6 class="text-uppercase text-muted fw-bold small mb-0"><i class="bi bi-calendar2-x me-1"></i> Who's Out Today</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column gap-3 overflow-auto" style="max-height: 380px;">
                        @forelse($outToday as $req)
                            <div class="d-flex align-items-center justify-content-between p-2 rounded bg-light border-start border-3 border-danger">
                                <div>
                                    <div class="fw-semibold small">{{ $req->user->name }}</div>
                                    <div class="text-muted small" style="font-size: 0.75rem;">{{ $req->user->department?->name ?? 'N/A' }}</div>
                                </div>
                                <span class="badge bg-danger-subtle text-danger-emphasis small">{{ $req->leaveType->name }}</span>
                            </div>
                        @empty
                            <div class="text-center py-5 text-muted small">
                                <i class="bi bi-sun fs-3 d-block mb-2 text-warning"></i>
                                Everyone is present today!
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <!-- Searchable Team Directory -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pt-4 pb-3 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
                    <h6 class="text-uppercase text-muted fw-bold small mb-0"><i class="bi bi-person-badge me-1"></i> Team Directory</h6>
                    <form method="GET" class="d-flex gap-2">
                        <div class="input-group input-group-sm" style="max-width: 250px;">
                            <input type="text" name="search" class="form-control" placeholder="Search team..." value="{{ request('search') }}">
                            <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                        </div>
                        @if(request('search'))
                            <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-light border"><i class="bi bi-x"></i></a>
                        @endif
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Employee</th>
                                <th>Code</th>
                                <th>Department</th>
                                <th>Join Date</th>
                                <th>Current Leave Balances</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($team as $member)
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-semibold">{{ $member->name }}</div>
                                        <div class="text-muted small" style="font-size: 0.75rem;">{{ $member->email }}</div>
                                    </td>
                                    <td><span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $member->employee_code ?? '—' }}</span></td>
                                    <td>{{ $member->department?->name ?? '—' }}</td>
                                    <td><small class="text-muted">{{ $member->join_date ? $member->join_date->format('M d, Y') : '—' }}</small></td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            @forelse($member->leaveBalances->where('year', now()->year) as $balance)
                                                @php
                                                    $left = $balance->entitled - $balance->used;
                                                    $pillClass = $left > 0 ? 'bg-success-subtle text-success-emphasis border-success-subtle' : 'bg-danger-subtle text-danger-emphasis border-danger-subtle';
                                                @endphp
                                                <span class="badge border {{ $pillClass }}" style="font-size: 0.7rem;">
                                                    {{ $balance->leaveType->name }}: {{ $left }} left
                                                </span>
                                            @empty
                                                <span class="text-muted small fst-italic">No active balances.</span>
                                            @endforelse
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted small">
                                        <i class="bi bi-people fs-3 d-block mb-2"></i>
                                        No team members found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($team->hasPages())
                    <div class="card-footer bg-white border-0 pt-3">{{ $team->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
