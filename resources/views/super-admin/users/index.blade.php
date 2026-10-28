<x-app-layout>
    <style>
        .card-footer nav p, 
        .card-footer nav .small.text-muted, 
        .card-footer nav p.text-sm,
        .card-footer nav > div:first-child {
            display: none !important;
        }
        .card-footer nav {
            justify-content: flex-end !important;
        }
        .table-responsive {
            border-radius: 8px;
        }
        .spotify-card table {
            min-width: 980px;
        }
        .department-dropdown .dropdown-menu {
            max-height: 200px !important;
            overflow-y: auto !important;
            width: 215px !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            background-color: #121212 !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5) !important;
        }
        .department-dropdown .dropdown-item {
            white-space: normal !important;
            word-wrap: break-word !important;
            color: #ffffff !important;
            font-size: 0.8rem !important;
        }
        .department-dropdown .dropdown-item:hover,
        .department-dropdown .dropdown-item.active {
            background-color: rgba(255, 255, 255, 0.1) !important;
            color: #ffffff !important;
        }
    </style>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <span>User Management</span>
            <a href="{{ route('super-admin.users.approvals') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" style="font-size: 0.85rem; height: 36px; padding: 6px 16px;">
                <i class="bi bi-person-check"></i> Pending Approvals
            </a>
        </div>
    </x-slot>

    <!-- Top Action Tabs -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex gap-2">
            <a href="{{ route('super-admin.users.index') }}" class="btn {{ !$showDeleted ? 'btn-primary' : 'btn-outline-secondary' }} py-2 px-4 d-inline-flex align-items-center" style="height: 36px;">Active Users</a>
            <a href="{{ route('super-admin.users.index', ['show' => 'deleted']) }}" class="btn {{ $showDeleted ? 'btn-deleted-tab active' : 'btn-deleted-tab' }} py-2 px-4 d-inline-flex align-items-center" style="height: 36px;">Deleted Users</a>
        </div>
        <!-- Oval Search field -->
        <form method="GET" action="{{ route('super-admin.users.index') }}" style="width: 250px;">
            @if($showDeleted)
                <input type="hidden" name="show" value="deleted">
            @endif
            <div class="position-relative">
                <i class="bi bi-search position-absolute text-white-50" style="left: 14px; top: 50%; transform: translateY(-50%); font-size: 0.9rem;"></i>
                <input type="text" name="search" class="form-control text-white" placeholder="Search by name..." value="{{ request('search') }}"
                       style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; font-size: 0.9rem; padding-left: 42px !important; @if(request('search')) padding-right: 40px !important; @endif height: 36px; border-radius: 50px !important;">
                @if(request('search'))
                    <a href="{{ route('super-admin.users.index', $showDeleted ? ['show' => 'deleted'] : []) }}" class="position-absolute text-white-50 hover-text-white d-flex align-items-center justify-content-center" style="right: 14px; top: 50%; transform: translateY(-50%); z-index: 5; text-decoration: none;">
                        <i class="bi bi-x-circle-fill" style="font-size: 1rem;"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Main Table Card -->
    <div class="spotify-card mb-4">
        <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Name</th>
                        <th>Email</th>
                        <th>Line Manager</th>
                        <th>Designation</th>
                        <th>Choose Department</th>
                        @if($showDeleted)<th>Deleted At</th>@endif
                        <th class="pe-4 text-end" style="width: 200px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <!-- User Name -->
                            <td class="ps-4 fw-semibold text-white">
                                {{ $user->name }}
                            </td>
                            
                            <!-- Email -->
                            <td>{{ $user->email }}</td>

                            <!-- Line Manager Column -->
                            <td>
                                @if($user->isSuperAdmin())
                                    <span class="text-white opacity-50">{{ $user->manager?->name ?? '—' }}</span>
                                @elseif($showDeleted)
                                    <span class="text-white opacity-75">{{ $user->manager?->name ?? '—' }}</span>
                                @else
                                    <select name="manager_id" form="update-form-{{ $user->id }}" class="form-select form-select-sm searchable-select" style="min-width: 160px; background-color: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.15); color: #fff; font-size: 0.8rem;">
                                        <option value="">No Line Manager</option>
                                        @foreach($allUsers->where('id', '!=', $user->id) as $boss)
                                            <option value="{{ $boss->id }}" @selected($user->manager_id == $boss->id)>{{ $boss->name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </td>

                            <!-- Designation Column -->
                            <td>
                                @if($user->isSuperAdmin())
                                    @if($user->special_order === 1)
                                        <span class="badge bg-primary text-white" style="font-size: 0.75rem; padding: 4px 10px; border-radius: 50px;">CEO</span>
                                    @elseif($user->special_order === 2)
                                        <span class="badge bg-info text-dark" style="font-size: 0.75rem; padding: 4px 10px; border-radius: 50px;">CTO</span>
                                    @elseif($user->special_order === 3)
                                        <span class="badge bg-warning text-dark" style="font-size: 0.75rem; padding: 4px 10px; border-radius: 50px;">HR Department</span>
                                    @else
                                        <span class="badge bg-secondary text-white" style="font-size: 0.75rem; padding: 4px 10px; border-radius: 50px;">Super Admin</span>
                                    @endif
                                @elseif($showDeleted)
                                    <span class="text-white opacity-75">{{ $user->designation?->name ?? ($user->isGuest() ? 'Guest Supervisor' : 'Employee') }}</span>
                                @else
                                    <input type="text" 
                                           name="designation_name" 
                                           form="update-form-{{ $user->id }}" 
                                           value="{{ $user->designation?->name ?? ($user->isGuest() ? 'Guest Supervisor' : 'Employee') }}" 
                                           maxlength="40" 
                                           placeholder="{{ $user->isGuest() ? 'Guest Supervisor' : 'Employee' }}" 
                                           class="form-control text-white" 
                                           style="height: 31px; font-size: 0.8rem; min-width: 130px; background-color: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.15); border-radius: 4px; padding: 0 8px;">
                                @endif
                            </td>
                            
                            <!-- Choose Department Column -->
                            <td>
                                @if($user->isSuperAdmin())
                                    <span class="badge bg-white text-dark fw-semibold" style="font-size: 0.75rem; padding: 4px 10px; border-radius: 50px; letter-spacing: 0.3px;">Executive</span>
                                @elseif($user->isGuest())
                                    <span class="badge bg-white text-dark fw-semibold" style="font-size: 0.75rem; padding: 4px 10px; border-radius: 50px; letter-spacing: 0.3px;">Guest User</span>
                                @elseif($showDeleted)
                                    <span class="text-white fw-medium">{{ $user->department?->name ?? '—' }}</span>
                                @else
                                    <div class="dropdown department-dropdown" style="width: 215px;">
                                        <button class="form-select form-select-sm text-start text-white w-100" 
                                                type="button" 
                                                id="deptDropdown-{{ $user->id }}" 
                                                data-bs-toggle="dropdown" 
                                                aria-expanded="false"
                                                style="border-color: rgba(255, 255, 255, 0.15); background-color: rgba(255, 255, 255, 0.03); border-radius: 4px; height: 31px; font-size: 0.85rem; padding-right: 2rem; color: #ffffff !important;">
                                            <span class="text-truncate d-inline-block w-100" style="max-width: 165px;">{{ $user->department?->name ?? 'Select Department' }}</span>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-dark p-0" aria-labelledby="deptDropdown-{{ $user->id }}">
                                            @foreach($departments as $dept)
                                                <li>
                                                    <a class="dropdown-item py-2 text-white @if($user->department_id == $dept->id) active @endif" 
                                                       href="#" 
                                                       data-value="{{ $dept->id }}"
                                                       onclick="selectDept(event, '{{ $user->id }}', '{{ $dept->id }}', '{{ $dept->name }}')">{{ $dept->name }}</a>
                                                </li>
                                            @endforeach
                                        </ul>
                                        <input type="hidden" name="department_id" id="department-input-{{ $user->id }}" form="update-form-{{ $user->id }}" value="{{ $user->department_id }}">
                                    </div>
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
                                    <!-- Hidden form for inline table saves -->
                                    <form method="POST" id="update-form-{{ $user->id }}" action="{{ route('super-admin.users.update', $user) }}" class="d-none">
                                        @csrf
                                        @method('PATCH')
                                    </form>
                                    
                                    <div class="d-inline-flex gap-2 align-items-center">
                                        <!-- Gray Profile Icon Edit Button -->
                                        <a href="{{ route('super-admin.users.edit', $user) }}" class="btn-circle-gray d-inline-flex align-items-center justify-content-center" 
                                            data-custom-tooltip="Edit profile" 
                                            data-tooltip-placement="top">
                                            <i class="bi bi-person-fill"></i>
                                        </a>

                                        <!-- Blue Outline Save Button -->
                                        <button type="submit" form="update-form-{{ $user->id }}" class="row-btn-outline-blue">Save</button>
                                        
                                        <!-- Red Outline Delete Button -->
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
        
        <!-- Pagination -->
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
    <!-- Sleek Custom Delete Confirmation Modal -->
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
        <script>
            window.selectDept = function(event, userId, deptId, deptName) {
                event.preventDefault();
                const input = document.getElementById('department-input-' + userId);
                if (input) {
                    input.value = deptId;
                }
                
                const button = document.getElementById('deptDropdown-' + userId);
                if (button) {
                    button.querySelector('span').textContent = deptName ? deptName : '— Department —';
                }
                
                const menu = button ? button.nextElementSibling : null;
                if (menu) {
                    $(menu).find('.dropdown-item').removeClass('active');
                    $(menu).find('.dropdown-item').each(function() {
                        if ($(this).attr('data-value') === String(deptId)) {
                            $(this).addClass('active');
                        }
                    });
                }
            };

            $(function() {
                let formToSubmit = null;

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
