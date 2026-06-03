<x-app-layout>
    <x-slot name="header">User Management</x-slot>

    <!-- Top Action Tabs -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex gap-2">
            <a href="{{ route('super-admin.users.index') }}" class="btn {{ !$showDeleted ? 'btn-primary' : 'btn-outline-secondary' }} py-2 px-4 d-inline-flex align-items-center" style="height: 36px;">Active Users</a>
            <a href="{{ route('super-admin.users.index', ['show' => 'deleted']) }}" class="btn {{ $showDeleted ? 'btn-deleted-tab active' : 'btn-deleted-tab' }} py-2 px-4 d-inline-flex align-items-center" style="height: 36px;">Deleted Users</a>
        </div>
    </div>

    <!-- Main Table Card -->
    <div class="spotify-card mb-4">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Name</th>
                        <th>Email</th>
                        <th>Line Manager / Status</th>
                        <th>Choose Role</th>
                        <th>Choose Department</th>
                        @if($showDeleted)<th>Deleted At</th>@endif
                        <th class="pe-4 text-end" style="width: 220px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <!-- User Name -->
                            <td class="ps-4 fw-semibold text-white">{{ $user->name }}</td>
                            
                            <!-- Email -->
                            <td>{{ $user->email }}</td>

                            <!-- Line Manager / Status Column (Stacked vertically to prevent horizontal stretching) -->
                            <td>
                                <div class="d-flex flex-column align-items-start gap-1">
                                    @foreach($user->headedDepartments as $headed)
                                        <span class="row-badge-green">Head of {{ $headed->name }}</span>
                                    @endforeach
                                    @if($user->headedDepartments->isEmpty())
                                        <span class="text-white opacity-75">{{ $user->manager?->name ?? '—' }}</span>
                                    @endif
                                </div>
                            </td>
                            
                            <!-- Choose Role Column -->
                            <td>
                                @if($user->isSuperAdmin())
                                    <span class="row-badge">Super Admin</span>
                                @elseif($showDeleted)
                                    <span class="text-white opacity-75">{{ $user->role?->label ?? '—' }}</span>
                                @else
                                    <select name="role_id" form="update-form-{{ $user->id }}" class="form-select form-select-sm role-select-width">
                                        @foreach($roles as $role)
                                            <option value="{{ $role->id }}" @selected($user->role_id == $role->id)>{{ $role->label }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </td>
                            
                            <!-- Choose Department Column -->
                            <td>
                                @if($user->isSuperAdmin())
                                    <span class="text-muted">—</span>
                                @elseif($showDeleted)
                                    <span class="text-white fw-medium">{{ $user->department?->name ?? '—' }}</span>
                                @else
                                    <select name="department_id" form="update-form-{{ $user->id }}" class="form-select form-select-sm dept-select-width">
                                        <option value="">— Department —</option>
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept->id }}" @selected($user->department_id == $dept->id)>{{ $dept->name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </td>
                            
                            <!-- Deleted At Column -->
                            @if($showDeleted)
                                <td class="small text-muted">{{ $user->deleted_at?->format('M d, Y') }}</td>
                            @endif
                            
                            <!-- Actions Column -->
                            <td class="pe-4 text-end">
                                @if($showDeleted)
                                    <form method="POST" action="{{ route('super-admin.users.restore', $user->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="row-btn-outline-blue">Restore</button>
                                    </form>
                                @elseif(!$user->isSuperAdmin())
                                    <!-- Primary Form wrapper (uses HTML5 form attribute bindings to align fields into columns) -->
                                    <form method="POST" id="update-form-{{ $user->id }}" action="{{ route('super-admin.users.update', $user) }}" class="user-update-form d-none" data-user-id="{{ $user->id }}" data-department-id="{{ $user->department_id }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="confirm_head_replace" value="0" class="confirm-head-replace-input">
                                    </form>
                                    
                                    <div class="d-inline-flex gap-2">
                                        <!-- Blue Outline Save Button -->
                                        <button type="submit" form="update-form-{{ $user->id }}" class="row-btn-outline-blue btn-save-user">Save</button>
                                        
                                        <!-- Red Outline Delete Button with Custom Modal Confirm Trigger -->
                                        <form method="POST" action="{{ route('super-admin.users.destroy', $user) }}" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="row-btn-outline-red btn-trigger-delete" data-user-name="{{ $user->name }}">Delete</button>
                                        </form>
                                    </div>
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

    @push('modals')
    <!-- Hierarchy Replacement Confirmation Modal (Pop-up standard for entire system) -->
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
                    <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 145px;">Cancel</button>
                    <button type="button" class="row-btn-blue" id="confirm-replace-head" style="width: 145px;">Replace Manager</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Sleek Custom Delete Confirmation Modal (Standard for system delete actions) -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-white fs-6"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> Remove User Profile?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="text-white fs-6">Are you sure you want to remove <strong id="delete-user-name" class="text-white"></strong> from HRMS?</p>
                    <p class="mb-0 small text-white opacity-50">Their profile information and active status will be archived. You can fully restore this user profile later from the Deleted tab.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 145px;">Cancel</button>
                    <button type="button" class="row-btn-red" id="confirm-delete-user-btn" style="width: 145px;">Confirm Delete</button>
                </div>
            </div>
        </div>
    </div>

    @endpush

    @push('scripts')
        <script src="{{ asset('js/hierarchy.js') }}"></script>
        <script>
            $(function() {
                let formToSubmit = null;

                // Handle sleek custom delete confirmation popup
                $('.btn-trigger-delete').on('click', function(e) {
                    e.preventDefault();
                    const button = $(this);
                    const userName = button.data('user-name');
                    formToSubmit = button.closest('form');

                    $('#delete-user-name').text(userName);
                    $('#deleteConfirmModal').modal('show');
                });

                $('#confirm-delete-user-btn').on('click', function() {
                    if (formToSubmit) {
                        formToSubmit.submit();
                    }
                });
            });
        </script>
    @endpush
</x-app-layout>
