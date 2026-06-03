<x-app-layout>
    <x-slot name="header">Departments</x-slot>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex gap-2">
            <a href="{{ route('super-admin.departments.index') }}" class="btn {{ !$showDeleted ? 'btn-primary' : 'btn-outline-secondary' }} py-2 px-4 d-inline-flex align-items-center" style="height: 36px; text-decoration: none;">Active Departments</a>
            <a href="{{ route('super-admin.departments.index', ['show' => 'deleted']) }}" class="btn {{ $showDeleted ? 'btn-deleted-tab active' : 'btn-deleted-tab' }} py-2 px-4 d-inline-flex align-items-center" style="height: 36px; text-decoration: none;">Deleted Departments</a>
        </div>
    </div>

    @if(!$showDeleted)
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="spotify-card h-100">
                <div class="card-header-area px-4 pt-4">
                    <h6 class="text-uppercase text-white fw-semibold small mb-2 opacity-75" style="letter-spacing: 0.05em;">Create Department</h6>
                    <div class="grid-separator"></div>
                </div>
                <div class="px-4 pb-4 pt-2">
                    <form method="POST" action="{{ route('super-admin.departments.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label text-white opacity-75 small">Name</label>
                            <input type="text" name="name" class="form-control bg-dark border-secondary text-white" required value="{{ old('name') }}">
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-white opacity-75 small">Code</label>
                            <input type="text" name="code" class="form-control bg-dark border-secondary text-white" required value="{{ old('code') }}" placeholder="e.g. DEV">
                        </div>
                        <button class="btn btn-primary w-100 py-2">Create</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="spotify-card h-100">
                <div class="card-header-area d-none">
                    <h6 class="text-uppercase text-white fw-semibold small mb-2 opacity-75" style="letter-spacing: 0.05em;">Departments</h6>
                    <div class="grid-separator"></div>
                </div>
                <div class="table-responsive h-100">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th class="ps-4">Name</th>
                                <th>Code</th>
                                <th>Head</th>
                                <th>Assign head</th>
                                <th class="pe-4 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($departments as $department)
                                <tr>
                                    <td class="ps-4 fw-semibold text-white">{{ $department->name }}</td>
                                    <td><span class="text-white opacity-75">{{ $department->code }}</span></td>
                                    <td>
                                        @if($department->head)
                                            <span class="row-badge-green"><i class="bi bi-person-fill me-1"></i> {{ $department->head->name }}</span>
                                        @else
                                            <span class="text-white opacity-50 small">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <form method="POST" action="{{ route('super-admin.departments.assign-head', $department) }}" id="assign-form-{{ $department->id }}" class="assign-head-form" data-department-id="{{ $department->id }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="confirm_head_replace" value="0" class="confirm-head-replace-input">
                                            <select name="head_user_id" class="form-select form-select-sm bg-dark border-secondary text-white" style="min-width: 140px; height: 32px;">
                                                <option value="">No head</option>
                                                @foreach($admins as $admin)
                                                    <option value="{{ $admin->id }}" @selected($department->head_user_id == $admin->id)>{{ $admin->name }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <div class="d-inline-flex gap-2">
                                            <button type="submit" form="assign-form-{{ $department->id }}" class="row-btn-outline-blue btn-assign-head d-flex align-items-center gap-1" style="height: 32px;">
                                                <i class="bi bi-check2"></i> Update
                                            </button>
                                            <form method="POST" action="{{ route('super-admin.departments.destroy', $department) }}" class="d-inline delete-department-form" data-has-users="{{ $department->users_count > 0 ? 'true' : 'false' }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="button" class="row-btn-outline-red btn-delete-dept">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @else
    <div class="spotify-card mb-4">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Name</th>
                        <th>Code</th>
                        <th>Deleted At</th>
                        <th class="pe-4 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($departments as $department)
                        <tr>
                            <td class="ps-4 fw-semibold text-white">{{ $department->name }}</td>
                            <td class="text-white opacity-75">{{ $department->code }}</td>
                            <td class="text-white opacity-50">{{ $department->deleted_at?->format('M d, Y') }}</td>
                            <td class="pe-4 text-end">
                                <form method="POST" action="{{ route('super-admin.departments.restore', $department->id) }}">
                                    @csrf
                                    <button type="submit" class="row-btn-outline-blue">Restore</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-white opacity-50 py-5">No deleted departments found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @push('modals')
    <div class="modal fade" id="replaceHeadModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-white fs-6"><i class="bi bi-question-circle-fill me-2"></i> Replace line manager?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="text-white fs-6">Line manager already exists: <strong id="replace-head-name" class="text-white"></strong>.</p>
                    <p class="mb-0 small text-white opacity-50">The previous head will report to the new line manager if they are in the same department.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 145px;">Cancel</button>
                    <button type="button" class="row-btn-blue" id="confirm-replace-head" style="width: 145px;">Replace Manager</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteDeptModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-white fs-6" id="deleteDeptModalTitle"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> Delete Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="text-white fs-6" id="deleteDeptModalText"></p>
                </div>
                <div class="modal-footer">
                    <div id="deleteDeptBtnGroup1" class="d-flex gap-2">
                        <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 145px;">Cancel</button>
                        <button type="button" class="row-btn-red" id="deleteDeptBtnConfirm" style="width: 145px;">Confirm Delete</button>
                    </div>
                    <div id="deleteDeptBtnGroup2" class="d-none">
                        <button type="button" class="row-btn-blue" data-bs-dismiss="modal" style="width: 145px;">OK</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endpush

    @push('scripts')
        <script src="{{ asset('js/hierarchy.js') }}"></script>
        <script>
            let formToSubmit = null;

            $(document).on('click', '.btn-delete-dept', function() {
                formToSubmit = $(this).closest('form');
                const hasUsers = formToSubmit.data('has-users') === true || formToSubmit.data('has-users') === 'true';

                const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteDeptModal'));
                const title = document.getElementById('deleteDeptModalTitle');
                const text = document.getElementById('deleteDeptModalText');

                if (hasUsers) {
                    title.textContent = 'Cannot delete department';
                    text.textContent = 'This department has active users and cannot be deleted.';
                    document.getElementById('deleteDeptBtnGroup1').classList.add('d-none');
                    document.getElementById('deleteDeptBtnGroup2').classList.remove('d-none');
                } else {
                    title.textContent = 'Delete Department?';
                    text.textContent = 'No active users are in this department. Do you want to delete this department?';
                    document.getElementById('deleteDeptBtnGroup1').classList.remove('d-none');
                    document.getElementById('deleteDeptBtnGroup2').classList.add('d-none');
                }

                modal.show();
            });

            $('#deleteDeptBtnConfirm').on('click', function() {
                if (formToSubmit) {
                    formToSubmit.submit();
                }
            });
        </script>
    @endpush
</x-app-layout>
