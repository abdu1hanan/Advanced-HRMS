<x-app-layout>
    <x-slot name="header">Departments</x-slot>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="btn-group" role="group">
            <a href="{{ route('super-admin.departments.index') }}" class="btn btn-sm {{ !$showDeleted ? 'btn-primary' : 'btn-outline-primary' }}">Active</a>
            <a href="{{ route('super-admin.departments.index', ['show' => 'deleted']) }}" class="btn btn-sm {{ $showDeleted ? 'btn-primary' : 'btn-outline-primary' }}">Deleted</a>
        </div>
    </div>

    @if(!$showDeleted)
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header">Create Department</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('super-admin.departments.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" class="form-control" required value="{{ old('name') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Code</label>
                            <input type="text" name="code" class="form-control" required value="{{ old('code') }}" placeholder="e.g. DEV">
                        </div>
                        <button class="btn btn-primary w-100">Create</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header">Departments</div>
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead><tr><th>Name</th><th>Code</th><th>Head</th><th>Assign head</th><th></th></tr></thead>
                        <tbody>
                            @foreach($departments as $department)
                                <tr>
                                    <td>{{ $department->name }}</td>
                                    <td>{{ $department->code }}</td>
                                    <td>{{ $department->head?->name ?? '—' }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('super-admin.departments.assign-head', $department) }}" class="d-flex gap-2 assign-head-form" data-department-id="{{ $department->id }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="confirm_head_replace" value="0" class="confirm-head-replace-input">
                                            <select name="head_user_id" class="form-select form-select-sm" style="min-width: 180px;">
                                                <option value="">No head</option>
                                                @foreach($admins as $admin)
                                                    <option value="{{ $admin->id }}" @selected($department->head_user_id == $admin->id)>{{ $admin->name }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-outline-primary btn-assign-head d-flex align-items-center gap-1">
                                                <i class="bi bi-check2"></i> Update
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="POST" action="{{ route('super-admin.departments.destroy', $department) }}" class="d-inline delete-department-form" data-has-users="{{ $department->users_count > 0 ? 'true' : 'false' }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete-dept">Delete</button>
                                        </form>
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
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead><tr><th>Name</th><th>Code</th><th>Deleted</th><th></th></tr></thead>
                <tbody>
                    @forelse($departments as $department)
                        <tr>
                            <td>{{ $department->name }}</td>
                            <td>{{ $department->code }}</td>
                            <td>{{ $department->deleted_at?->format('M d, Y') }}</td>
                            <td>
                                <form method="POST" action="{{ route('super-admin.departments.restore', $department->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success">Restore</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No deleted departments.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div class="modal fade" id="replaceHeadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Replace line manager?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Line manager already exists: <strong id="replace-head-name"></strong>.</p>
                    <p class="mb-0 small text-muted">The previous head will report to the new line manager if they are in the same department.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirm-replace-head">Replace</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteDeptModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteDeptModalTitle">Delete Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="deleteDeptModalText"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="deleteDeptBtnCancel">Cancel</button>
                    <button type="button" class="btn btn-danger" id="deleteDeptBtnConfirm">Yes, Delete</button>
                    <button type="button" class="btn btn-primary d-none" data-bs-dismiss="modal" id="deleteDeptBtnOk">OK</button>
                </div>
            </div>
        </div>
    </div>

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
                const btnCancel = document.getElementById('deleteDeptBtnCancel');
                const btnConfirm = document.getElementById('deleteDeptBtnConfirm');
                const btnOk = document.getElementById('deleteDeptBtnOk');

                if (hasUsers) {
                    title.textContent = 'Cannot delete department';
                    text.textContent = 'This department has active users and cannot be deleted.';
                    btnCancel.classList.add('d-none');
                    btnConfirm.classList.add('d-none');
                    btnOk.classList.remove('d-none');
                } else {
                    title.textContent = 'Delete Department?';
                    text.textContent = 'No active users are in this department. Do you want to delete this department?';
                    btnCancel.classList.remove('d-none');
                    btnConfirm.classList.remove('d-none');
                    btnOk.classList.add('d-none');
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
