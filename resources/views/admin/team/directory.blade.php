<x-app-layout>
    <x-slot name="header">Team Directory</x-slot>

    <!-- Searchable Team Directory -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pt-4 pb-3 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
            <h6 class="text-uppercase text-muted fw-bold small mb-0"><i class="bi bi-people-fill me-1"></i> Subordinate Directory</h6>
            <form method="GET" class="d-flex gap-2">
                <div class="input-group input-group-sm" style="max-width: 250px;">
                    <input type="text" name="search" class="form-control" placeholder="Search team..." value="{{ request('search') }}">
                    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                </div>
                @if(request('search'))
                    <a href="{{ route('admin.team.directory') }}" class="btn btn-sm btn-light border"><i class="bi bi-x"></i></a>
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
                                <div class="fw-semibold text-dark">{{ $member->name }}</div>
                                <div class="text-muted small" style="font-size: 0.75rem;">{{ $member->email }}</div>
                            </td>
                            <td><span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $member->employee_code ?? '—' }}</span></td>
                            <td>{{ $member->department?->name ?? '—' }}</td>
                            <td><small class="text-muted">{{ $member->join_date ? $member->join_date->format('M d, Y') : '—' }}</small></td>
                            <td>
                                <div class="d-flex flex-wrap gap-1.5">
                                    @forelse($member->leaveBalances->where('year', $year) as $balance)
                                        @php
                                            $left = max(0, $balance->entitled + $balance->carried_forward - $balance->used);
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
</x-app-layout>
