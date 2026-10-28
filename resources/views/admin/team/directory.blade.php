<x-app-layout>
    <x-slot name="header">Team Directory</x-slot>

    <!-- Searchable Team Directory -->
    <div class="spotify-card mb-4">
        <div class="card-header bg-transparent border-0 pt-4 pb-0 px-4 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
            <h6 class="text-uppercase text-white fw-bold small mb-0 opacity-75" style="letter-spacing: 0.05em;">
                Subordinate Directory
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
                        <th class="text-white opacity-50 text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($team as $member)
                        <tr class="hover-highlight">
                            <td class="ps-4">
                                <div class="fw-semibold text-white">{{ $member->name }}</div>
                                <div class="text-white opacity-50 small" style="font-size: 0.75rem;">{{ $member->email }}</div>
                            </td>
                            <td>
                                <span class="text-white opacity-75">{{ $member->department?->name ?? '—' }}</span>
                                @if($member->isDepartmentHead())
                                    <div class="text-white opacity-50 small mt-0.5" style="font-size: 0.72rem;">Head of {{ $member->department->name }}</div>
                                @endif
                            </td>
                            <td><small class="text-white opacity-50">{{ $member->join_date ? $member->join_date->format('M d, Y') : '—' }}</small></td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex align-items-center gap-2">
                                    <!-- View Leave Ledger Button -->
                                    <button type="button" class="row-btn-outline-blue btn-sm px-2 btn-view-ledger" 
                                            data-name="{{ $member->name }}" 
                                            data-balances="{{ json_encode($member->leaveBalances->where('year', $year)->filter(fn($b) => $b->leaveType && $b->leaveType->is_active)->sortBy(function($b) {
                                                if ($b->leaveType->slug === 'unpaid') return 0;
                                                if ($b->leaveType->slug === 'absent') return 1;
                                                return 2;
                                            })->map(fn($b) => [
                                                'name' => $b->leaveType->name,
                                                'slug' => $b->leaveType->slug,
                                                'entitled' => $b->entitled,
                                                'carried_forward' => $b->carried_forward,
                                                'used' => $b->used,
                                                'pending' => $b->pending,
                                                'left' => max(0, $b->entitled + $b->carried_forward - $b->used)
                                            ])->values()->all()) }}"
                                            data-custom-tooltip="View Leave Ledger"
                                            data-tooltip-placement="top"
                                            style="height: 28px !important; width: 28px !important; display: inline-flex; align-items: center; justify-content: center; padding: 0 !important; border-radius: 50px !important;">
                                        <i class="bi bi-pie-chart-fill" style="font-size: 0.85rem;"></i>
                                    </button>
                                    
                                    <!-- View Attendance History Logs Link -->
                                    <a href="{{ route('admin.team.attendance-logs', ['user_id' => $member->id]) }}" class="row-btn-outline-warning btn-sm px-2" 
                                            data-custom-tooltip="View Attendance Logs"
                                            data-tooltip-placement="top"
                                            style="height: 28px !important; width: 28px !important; display: inline-flex; align-items: center; justify-content: center; padding: 0 !important; border-radius: 50px !important; text-decoration: none;">
                                        <i class="bi bi-clock-history" style="font-size: 0.85rem;"></i>
                                    </a>

                                    <!-- View Track Record Link -->
                                    <a href="{{ route('admin.team.track-record', ['user_id' => $member->id]) }}" class="row-btn-outline-success btn-sm px-2" 
                                            data-custom-tooltip="View Track Record"
                                            data-tooltip-placement="top"
                                            style="height: 28px !important; width: 28px !important; display: inline-flex; align-items: center; justify-content: center; padding: 0 !important; border-radius: 50px !important; text-decoration: none;">
                                        <i class="bi bi-calendar-check" style="font-size: 0.85rem;"></i>
                                    </a>
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

    @push('modals')
    <!-- Leave Ledger Modal -->
    <div class="modal fade" id="leaveLedgerModal" tabindex="-1" aria-labelledby="leaveLedgerModalLabel" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="background-color: #0c0c0c !important; border: 1px solid rgba(255, 255, 255, 0.08) !important; border-radius: 12px !important;">
                <div class="modal-header border-0 py-3">
                    <h5 class="modal-title fw-bold text-white fs-6 d-flex align-items-center" id="leaveLedgerModalLabel">
                        Leave Ledger
                        <span id="ledgerEmployeeName" class="row-badge-blue ms-2 text-uppercase font-monospace" style="font-size: 0.72rem; padding: 0.2rem 0.6rem; border-radius: 50px !important;"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="list-group list-group-flush" id="ledgerTrackList">
                        <!-- Populated dynamically via JS -->
                    </div>
                </div>
                <div class="modal-footer border-0 py-2">
                    <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 120px;">Close</button>
                </div>
            </div>
        </div>
    </div>
    @endpush

    @push('scripts')
    <script>
        $(function() {
            // View Leave Ledger Modal
            $('.btn-view-ledger').on('click', function() {
                const name = $(this).data('name');
                const balances = $(this).data('balances');
                
                $('#ledgerEmployeeName').text(name);
                
                let html = '';
                if (!balances || balances.length === 0) {
                    html = '<div class="text-center text-white opacity-50 py-4">No active leave balances found for this employee.</div>';
                } else {
                    balances.forEach(function(b, index) {
                        const isUnpaid = b.slug === 'unpaid';
                        const isAbsent = b.slug === 'absent';
                        const entitled = parseFloat(b.entitled) || 0;
                        const carried_forward = parseFloat(b.carried_forward) || 0;
                        const used = parseFloat(b.used) || 0;
                        const allowed = (isUnpaid || isAbsent) ? used : (entitled + carried_forward);
                        const remaining = (isUnpaid || isAbsent) ? used : (parseFloat(b.left) || 0);
                        const pending = parseFloat(b.pending) || 0;
                        const percentUsed = allowed > 0 ? Math.min(100, Math.round((used / allowed) * 100)) : 0;
                        const percentPending = (allowed > 0 && pending > 0) ? Math.min(100, Math.round((pending / allowed) * 100)) : 0;
                        const indexStr = String(index + 1).padStart(2, '0');
                        
                        html += `
                        <div class="list-group-item bg-transparent d-flex align-items-center justify-content-between py-3 px-4 border-0 cursor-pointer" 
                             style="border-bottom: 1px solid rgba(255,255,255,0.03) !important;"
                             data-bs-toggle="collapse" 
                             data-bs-target="#modalTrackCollapse-${index}">
                            <div class="d-flex align-items-center" style="min-width: 180px;">
                                <span class="text-white opacity-25 me-3 small font-monospace">${indexStr}</span>
                                <div>
                                    <div class="fw-semibold text-white fs-6" style="letter-spacing: -0.02em;">${b.name}</div>
                                </div>
                            </div>
                            <div class="flex-grow-1 mx-4 d-none d-md-block">
                                <div class="progress bg-dark position-relative" style="height: 6px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.06); overflow: visible;">
                                    <div class="progress-bar bg-white" style="width: ${percentUsed}%; border-radius: 50px;"></div>
                                    ${percentPending > 0 ? `<div class="progress-bar bg-warning opacity-75" style="width: ${percentPending}%; border-radius: 50px;"></div>` : ''}
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-4">
                                <div class="text-end" style="min-width: 130px;">
                                    <span class="fw-bold text-white fs-6">${remaining} Left</span>
                                    <div class="text-white opacity-50 small" style="font-size: 0.7rem; text-transform: uppercase;">of ${allowed} Allowed</div>
                                </div>
                                <i class="bi bi-chevron-down text-white opacity-25 small"></i>
                            </div>
                        </div>
                        <div class="collapse" id="modalTrackCollapse-${index}" style="background-color: rgba(255,255,255,0.015);">
                            <div class="py-3 px-5 border-bottom" style="border-color: rgba(255,255,255,0.04) !important;">
                                <div class="row text-start py-2 g-3">
                                    <div class="col-sm-3">
                                        <label class="text-white small opacity-50 d-block mb-1" style="font-size: 0.68rem; text-transform: uppercase;">Base Entitled</label>
                                        <strong class="text-white">${(isUnpaid || isAbsent) ? '∞' : entitled + ' days'}</strong>
                                    </div>
                                    <div class="col-sm-3">
                                        <label class="text-white small opacity-50 d-block mb-1" style="font-size: 0.68rem; text-transform: uppercase;">Carried Over</label>
                                        <strong class="${!(isUnpaid || isAbsent) && carried_forward > 0 ? 'text-success' : 'text-white'}">${(isUnpaid || isAbsent) ? '-' : '+' + carried_forward + ' days'}</strong>
                                    </div>
                                    <div class="col-sm-3">
                                        <label class="text-white small opacity-50 d-block mb-1" style="font-size: 0.68rem; text-transform: uppercase;">Pending Approval</label>
                                        <strong class="${isAbsent ? 'text-white' : 'text-warning'}">${isAbsent ? '-' : pending + ' days'}</strong>
                                    </div>
                                    <div class="col-sm-3">
                                        <label class="text-white small opacity-50 d-block mb-1" style="font-size: 0.68rem; text-transform: uppercase;">Utilized</label>
                                        <strong class="text-white">${used} days</strong>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                    });
                }
                
                $('#ledgerTrackList').html(html);
                const ledgerModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('leaveLedgerModal'));
                ledgerModal.show();
            });
        });
    </script>
    @endpush
</x-app-layout>
