<x-app-layout>
    <x-slot name="header">User Management</x-slot>

    <!-- Top Action Row -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="btn-group" role="group">
            <a href="{{ route('super-admin.users.index') }}" class="btn btn-sm {{ !$showDeleted ? 'btn-primary' : 'btn-outline-secondary' }}">Active Users</a>
            <a href="{{ route('super-admin.users.index', ['show' => 'deleted']) }}" class="btn btn-sm {{ $showDeleted ? 'btn-primary' : 'btn-outline-secondary' }}">Deleted Users</a>
        </div>
    </div>

    @if(session('head_replace_prompt'))
        <div class="alert alert-warning border-0 bg-warning text-black mb-4 py-3 px-4 rounded-3 small">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            Line manager already exists: <strong>{{ session('head_replace_prompt')['previous_head_name'] }}</strong>.
            Save again with confirmation below or use the modal on next assign.
        </div>
    @endif

    <div class="alert alert-info border-0 bg-dark text-white opacity-75 mb-4 py-3 px-4 rounded-3 small" style="border: 1px solid rgba(255, 255, 255, 0.08) !important;">
        <i class="bi bi-info-circle-fill me-2 text-white"></i>
        Set role to <strong>Admin</strong> and choose a <strong>Department</strong>, then Save — user becomes line manager for that department.
        Super Admin cannot be assigned from this page.
    </div>

    <!-- Main Table Card -->
    <div class="spotify-card mb-4">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Line Manager / Status</th>
                        @if($showDeleted)<th>Deleted At</th>@endif
                        <th class="pe-4 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td class="ps-4 fw-semibold text-white">{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>
                                @if($user->isSuperAdmin())
                                    <span class="row-badge">Super Admin</span>
                                @else
                                    <span class="text-white opacity-75">{{ $user->role?->label ?? '—' }}</span>
                                @endif
                            </td>
                            <td>
                                @if($user->department_id && $user->department)
                                    <span class="text-white fw-medium">{{ $user->department->name }}</span>
                                @elseif($user->department_id)
                                    <span class="text-muted">No department (deleted)</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @foreach($user->headedDepartments as $headed)
                                    <span class="row-badge me-1" style="background-color: #ffffff !important; color: #000000 !important;">Head of {{ $headed->name }}</span>
                                @endforeach
                                @if($user->headedDepartments->isEmpty())
                                    <span class="text-white opacity-75">{{ $user->manager?->name ?? '—' }}</span>
                                @endif
                            </td>
                            @if($showDeleted)
                                <td class="small text-muted">{{ $user->deleted_at?->format('M d, Y') }}</td>
                            @endif
                            <td class="pe-4 text-end">
                                @if($showDeleted)
                                    <form method="POST" action="{{ route('super-admin.users.restore', $user->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-primary py-1 px-3">Restore</button>
                                    </form>
                                @elseif(!$user->isSuperAdmin())
                                    <form method="POST" action="{{ route('super-admin.users.update', $user) }}" class="row g-2 align-items-center justify-content-end user-update-form d-inline-flex" data-user-id="{{ $user->id }}" data-department-id="{{ $user->department_id }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="confirm_head_replace" value="0" class="confirm-head-replace-input">
                                        
                                        <div class="col-auto">
                                            <select name="role_id" class="form-select form-select-sm">
                                                @foreach($roles as $role)
                                                    <option value="{{ $role->id }}" @selected($user->role_id == $role->id)>{{ $role->label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-auto">
                                            <select name="department_id" class="form-select form-select-sm department-select">
                                                <option value="">— Department —</option>
                                                @foreach($departments as $dept)
                                                    <option value="{{ $dept->id }}" @selected($user->department_id == $dept->id)>{{ $dept->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-auto">
                                            <button type="submit" class="btn btn-sm btn-primary py-1 px-3 btn-save-user">Save</button>
                                        </div>
                                    </form>
                                    
                                    <form method="POST" action="{{ route('super-admin.users.destroy', $user) }}" class="d-inline ms-2" onsubmit="return confirm('This will remove the user profile from HRMS. They can be restored later. Continue?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="row-btn" style="border-color: rgba(220, 53, 69, 0.4) !important; color: #ff6b6b !important;">Delete</button>
                                    </form>
                                @else
                                    <span class="row-badge">System Administrator</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-white opacity-50 py-5">
                                <div class="mb-2"><i class="bi bi-people fs-3"></i></div>
                                No users found in this directory.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Beautiful Dark styled Pagination footer -->
        @if($users->hasPages())
            <div class="card-footer d-flex justify-content-between align-items-center">
                <div class="small text-white opacity-50">
                    Showing {{ $users->firstItem() }} to {{ $users->lastItem() }} of {{ $users->total() }} records
                </div>
                <div>
                    {{ $users->links() }}
                </div>
            </div>
        @endif
    </div>

    <!-- Hierarchy Replacement Confirmation Modal -->
    <div class="modal fade" id="replaceHeadModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-white fs-6"><i class="bi bi-question-circle-fill me-2"></i> Replace Line Manager?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="text-white fs-6">Line manager already exists: <strong id="replace-head-name" class="text-white"></strong>.</p>
                    <p class="mb-0 small text-white opacity-50">The previous department head will automatically report to the new line manager if they share the same department. All other employees will be linked directly to the new head.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="row-btn" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary px-4" id="confirm-replace-head">Replace Manager</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="{{ asset('js/hierarchy.js') }}"></script>
        @if(session('head_replace_prompt') && session('pending_user_id'))
            <script>
                $(function() {
                    const form = $('.user-update-form').closest('tr').find('form.user-update-form').first();
                });
            </script>
        @endif
    @endpush
</x-app-layout>
