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
                    <h6 class="text-uppercase text-white fw-semibold small mb-0 opacity-75" style="letter-spacing: 0.05em;">Create Department</h6>
                </div>
                <div class="px-4 pb-4 pt-2">
                    <div class="grid-separator mb-3"></div>
                    <form method="POST" action="{{ route('super-admin.departments.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label text-white opacity-75 small">Name *</label>
                            <input type="text" name="name" class="form-control bg-dark border-secondary text-white @error('name') is-invalid @enderror" required value="{{ old('name') }}" maxlength="30">
                            @error('name')
                                <span class="text-danger d-block mt-1 small" style="font-size: 0.75rem;">{{ $message }}</span>
                            @else
                                <small class="text-white opacity-50 d-block mt-1">Maximum 30 characters</small>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white opacity-75 small">Code *</label>
                            <input type="text" name="code" class="form-control bg-dark border-secondary text-white @error('code') is-invalid @enderror" required value="{{ old('code') }}" placeholder="e.g. DEV" maxlength="5">
                            @error('code')
                                <span class="text-danger d-block mt-1 small" style="font-size: 0.75rem;">{{ $message }}</span>
                            @else
                                <small class="text-white opacity-50 d-block mt-1">Maximum 5 characters</small>
                            @enderror
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-white opacity-75 small">Department Head (Optional)</label>
                            <select name="head_id" class="form-select form-select-sm searchable-select" style="min-width: 160px; background-color: rgba(255,255,255,0.03) !important; border: 1px solid rgba(255,255,255,0.15) !important; color: #fff; font-size: 0.8rem;">
                                <option value="" class="bg-dark text-white">No Head</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}" class="bg-dark text-white" {{ old('head_id') == $u->id ? 'selected' : '' }}>
                                        {{ $u->name }} {{ $u->designation ? '('.$u->designation->name.')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-white opacity-50 d-block mt-1">Select employee to lead this department</small>
                        </div>
                        <button class="row-btn-green w-100" style="height: 48px; border: none; font-size: 0.85rem !important;">Create</button>
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
                                <th class="ps-4" style="width: 32%; min-width: 170px;">Name</th>
                                <th style="width: 15%; min-width: 80px;">Code</th>
                                <th style="width: 27%; min-width: 180px;">Department Head</th>
                                <th style="width: 12%; min-width: 90px;">Employees</th>
                                <th class="pe-4 text-end" style="width: 14%; min-width: 120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($departments as $department)
                                <tr>
                                    <td class="ps-4 fw-semibold text-white" style="word-break: break-word; white-space: normal;">{{ $department->name }}</td>
                                    <td><span class="text-white opacity-75">{{ $department->code }}</span></td>
                                    <td>
                                        <select name="head_id" form="update-dept-form-{{ $department->id }}" class="form-select form-select-sm searchable-select" style="min-width: 160px; background-color: rgba(255,255,255,0.03) !important; border: 1px solid rgba(255,255,255,0.15) !important; color: #fff; font-size: 0.8rem;">
                                            <option value="" class="bg-dark text-white">No Head</option>
                                            @foreach($users as $u)
                                                <option value="{{ $u->id }}" class="bg-dark text-white" @selected($department->head_id == $u->id)>
                                                    {{ $u->name }} {{ $u->designation ? '('.$u->designation->name.')' : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><span class="row-badge" style="background: rgba(255, 255, 255, 0.1); color: #ffffff; border: transparent; font-weight: 600; padding: 3px 8px; border-radius: 4px;">{{ $department->users_count }}</span></td>
                                    <td class="pe-4 text-end">
                                        <div class="d-inline-flex gap-2 align-items-center">
                                            <form id="update-dept-form-{{ $department->id }}" method="POST" action="{{ route('super-admin.departments.update', $department) }}" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="name" value="{{ $department->name }}">
                                                <input type="hidden" name="code" value="{{ $department->code }}">
                                                <button type="submit" class="row-btn-outline-blue">Save</button>
                                            </form>
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
                        <th class="ps-4" style="width: 400px; min-width: 200px;">Name</th>
                        <th>Code</th>
                        <th>Deleted At</th>
                        <th class="pe-4 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($departments as $department)
                        <tr>
                            <td class="ps-4 fw-semibold text-white" style="max-width: 300px; word-break: break-word; white-space: normal;">{{ $department->name }}</td>
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
    <div class="modal fade" id="deleteDeptModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-white fs-6" id="deleteDeptModalTitle">Delete Department?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="text-white fs-6 mb-0" id="deleteDeptModalText">No active users are in this department. Do you want to delete this department?</p>
                </div>
                <div class="modal-footer">
                    <div id="deleteDeptBtnGroup1" class="d-flex gap-2 w-100 justify-content-end">
                        <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 145px;">Cancel</button>
                        <button type="button" class="row-btn-red" id="deleteDeptBtnConfirm" style="width: 145px;">Delete</button>
                    </div>
                    <div id="deleteDeptBtnGroup2" class="d-none w-100 text-end">
                        <button type="button" class="row-btn-blue" data-bs-dismiss="modal" style="width: 145px;">OK</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endpush

    @push('scripts')
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
