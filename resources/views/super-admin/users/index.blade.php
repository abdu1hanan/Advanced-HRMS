<x-app-layout>
    <x-slot name="header">User Management</x-slot>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="btn-group" role="group">
            <a href="{{ route('super-admin.users.index') }}" class="btn btn-sm {{ !$showDeleted ? 'btn-primary' : 'btn-outline-primary' }}">Active</a>
            <a href="{{ route('super-admin.users.index', ['show' => 'deleted']) }}" class="btn btn-sm {{ $showDeleted ? 'btn-primary' : 'btn-outline-primary' }}">Deleted</a>
        </div>
    </div>

    @if(session('head_replace_prompt'))
        <div class="alert alert-warning">
            Line manager already exists: <strong>{{ session('head_replace_prompt')['previous_head_name'] }}</strong>.
            Save again with confirmation below or use the modal on next assign.
        </div>
    @endif

    <div class="alert alert-info small mb-3">
        Set role to <strong>Admin</strong> and choose a <strong>Department</strong>, then Save — user becomes line manager for that department.
        Super Admin cannot be assigned from this page.
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Line Manager</th>
                        @if($showDeleted)<th>Deleted</th>@endif
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->role?->label ?? '—' }}</td>
                            <td>
                                @if($user->department_id && $user->department)
                                    {{ $user->department->name }}
                                @elseif($user->department_id)
                                    <span class="text-muted">No department (deleted)</span>
                                @else
                                    <span class="text-muted">No department</span>
                                @endif
                            </td>
                            <td>
                                @foreach($user->headedDepartments as $headed)
                                    <span class="badge bg-success me-1">Head of {{ $headed->name }}</span>
                                @endforeach
                                @if($user->headedDepartments->isEmpty())
                                    {{ $user->manager?->name ?? '—' }}
                                @endif
                            </td>
                            @if($showDeleted)
                                <td class="small text-muted">{{ $user->deleted_at?->format('M d, Y') }}</td>
                            @endif
                            <td>
                                @if($showDeleted)
                                    <form method="POST" action="{{ route('super-admin.users.restore', $user->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success">Restore</button>
                                    </form>
                                @elseif(!$user->isSuperAdmin())
                                    <form method="POST" action="{{ route('super-admin.users.update', $user) }}" class="row g-2 align-items-center user-update-form" data-user-id="{{ $user->id }}" data-department-id="{{ $user->department_id }}">
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
                                                <option value="">—</option>
                                                @foreach($departments as $dept)
                                                    <option value="{{ $dept->id }}" @selected($user->department_id == $dept->id)>{{ $dept->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-auto">
                                            <button type="submit" class="btn btn-sm btn-primary btn-save-user">Save</button>
                                        </div>
                                    </form>
                                    <form method="POST" action="{{ route('super-admin.users.destroy', $user) }}" class="d-inline ms-1" onsubmit="return confirm('This will remove the user profile from HRMS. They can be restored later. Continue?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                @else
                                    <span class="badge bg-dark">System Administrator</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $users->links() }}</div>
    </div>

    <div class="modal fade" id="replaceHeadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Replace line manager?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Line manager already exists: <strong id="replace-head-name"></strong>.</p>
                    <p class="mb-0 small text-muted">The previous head will report to the new line manager if they are in the same department. All employees will be linked to the new head.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirm-replace-head">Replace</button>
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
                    // Auto-open not needed; user clicks save again with confirm via modal on next attempt
                });
            </script>
        @endif
    @endpush
</x-app-layout>
