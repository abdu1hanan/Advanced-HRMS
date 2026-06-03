<x-app-layout>
    <x-slot name="header">Team Directory</x-slot>

    <!-- Searchable Team Directory -->
    <div class="spotify-card mb-4">
        <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
            <h6 class="text-uppercase text-white fw-bold small mb-0 opacity-75" style="letter-spacing: 0.05em;">
                <i class="bi bi-people-fill me-1 text-primary"></i> Subordinate Directory
            </h6>
            <form method="GET" class="d-flex gap-2">
                <div class="input-group input-group-sm" style="max-width: 250px;">
                    <input type="text" name="search" class="form-control" placeholder="Search team..." value="{{ request('search') }}"
                           style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #ffffff !important;">
                    <button class="btn btn-outline-secondary d-flex align-items-center text-white" type="submit"
                            style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
        </div>
        <div class="px-4">
            <div class="grid-separator mt-3"></div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4 text-white opacity-50">Employee</th>
                        <th class="text-white opacity-50">Department</th>
                        <th class="text-white opacity-50">Join Date</th>
                        <th class="text-white opacity-50">Current Leave Balances</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($team as $member)
                        <tr class="hover-highlight">
                            <td class="ps-4">
                                <div class="fw-semibold text-white">{{ $member->name }}</div>
                                <div class="text-white opacity-50 small" style="font-size: 0.75rem;">{{ $member->email }}</div>
                            </td>
                            <td><span class="text-white opacity-75">{{ $member->department?->name ?? '—' }}</span></td>
                            <td><small class="text-white opacity-50">{{ $member->join_date ? $member->join_date->format('M d, Y') : '—' }}</small></td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    @forelse($member->leaveBalances->where('year', $year) as $balance)
                                        @php
                                            $left = max(0, $balance->entitled + $balance->carried_forward - $balance->used);
                                            $badgeClass = $left > 0 ? 'row-badge-green' : 'row-badge-red';
                                        @endphp
                                        <span class="{{ $badgeClass }}" style="font-size: 0.7rem;">
                                            {{ $balance->leaveType->name }}: {{ $left }} left
                                        </span>
                                    @empty
                                        <span class="text-white opacity-50 small fst-italic">No active balances.</span>
                                    @endforelse
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-5 text-white opacity-50 small">
                                <i class="bi bi-people fs-3 d-block mb-2"></i>
                                No team members found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($team->hasPages())
            <div class="px-4 py-3 border-top border-secondary border-opacity-25">{{ $team->links() }}</div>
        @endif
    </div>
</x-app-layout>
