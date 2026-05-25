<x-app-layout>
    <x-slot name="header">User Management</x-slot>

    <!-- Top Action Tabs -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex gap-2">
            <a href="{{ route('super-admin.users.index') }}" class="{{ !$showDeleted ? 'row-btn-blue' : 'btn btn-outline-secondary' }} py-2 px-4 d-inline-flex align-items-center" style="height: 36px;">Active Users</a>
            <a href="{{ route('super-admin.users.index', ['show' => 'deleted']) }}" class="{{ $showDeleted ? 'row-btn-red' : 'btn btn-outline-secondary' }} py-2 px-4 d-inline-flex align-items-center" style="height: 36px;">Deleted Users</a>
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
                        <th>Choose Role</th>
                        <th>Choose Department</th>
                        <th>Line Manager / Status</th>
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
                            
                            <!-- Line Manager / Status Column -->
                            <td>
                                @foreach($user->headedDepartments as $headed)
                                    <span class="row-badge-green me-1">Head of {{ $headed->name }}</span>
                                @endforeach
                                @if($user->headedDepartments->isEmpty())
                                    <span class="text-white opacity-75">{{ $user->manager?->name ?? '—' }}</span>
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
                                        <button type="submit" class="row-btn-blue">Restore</button>
                                    </form>
                                @elseif(!$user->isSuperAdmin())
                                    <!-- Primary Form wrapper (uses HTML5 form attribute bindings to align fields into columns) -->
                                    <form method="POST" id="update-form-{{ $user->id }}" action="{{ route('super-admin.users.update', $user) }}" class="user-update-form d-none" data-user-id="{{ $user->id }}" data-department-id="{{ $user->department_id }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="confirm_head_replace" value="0" class="confirm-head-replace-input">
                                    </form>
                                    
                                    <div class="d-inline-flex gap-2">
                                        <!-- Blue Save Button (Exactly matched size) -->
                                        <button type="submit" form="update-form-{{ $user->id }}" class="row-btn-blue btn-save-user">Save</button>
                                        
                                        <!-- Red Outline Delete Button (Restored) -->
                                        <form method="POST" action="{{ route('super-admin.users.destroy', $user) }}" class="d-inline" onsubmit="return confirm('This will remove the user profile from HRMS. They can be restored later. Continue?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="row-btn-outline-red">Delete</button>
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
                    <button type="button" class="row-btn" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="row-btn-blue px-4" id="confirm-replace-head">Replace Manager</button>
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
