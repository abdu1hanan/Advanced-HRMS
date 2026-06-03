<x-app-layout>
    <x-slot name="header">Leave Types</x-slot>
    
    <!-- Full Width Add/Edit Form -->
    <div class="spotify-card mb-4">
        <div class="card-header-area px-4 pt-4">
            <h6 class="text-uppercase text-white fw-semibold small mb-2 opacity-75" style="letter-spacing: 0.05em;" id="formCardHeader">Add Leave Type</h6>
            <div class="grid-separator"></div>
        </div>
        <div class="px-4 pb-4 pt-2">
            <form method="POST" action="{{ route('super-admin.leave-types.store') }}" id="leaveTypeForm">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="PATCH" disabled>
                
                <div class="row g-4 mb-3">
                    <div class="col-md-3">
                        <label class="form-label text-white opacity-75 small">Name <i class="bi bi-info-circle ms-1 text-white opacity-50" data-bs-toggle="tooltip" title="The name of the leave type (e.g. Annual Leave, Sick Leave)"></i></label>
                        <input type="text" name="name" id="lt_name" class="form-control bg-dark border-secondary text-white" placeholder="e.g. Annual Leave" required>
                        <div class="form-text text-white opacity-50 small mt-1" style="font-size: 0.7rem;">Identifier for this leave type.</div>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label text-white opacity-75 small">Max days/year <i class="bi bi-info-circle ms-1 text-white opacity-50" data-bs-toggle="tooltip" title="The absolute hard limit of days any employee can take in a single year for this leave."></i></label>
                        <input type="number" name="max_days_per_year" id="lt_max_days" class="form-control bg-dark border-secondary text-white" placeholder="e.g. 30" required min="0">
                        <div class="form-text text-white opacity-50 small mt-1" style="font-size: 0.7rem;">Ceiling limit for taken leaves per year.</div>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label text-white opacity-75 small">Default entitled <i class="bi bi-info-circle ms-1 text-white opacity-50" data-bs-toggle="tooltip" title="The standard starting balance given to employees at the beginning of the year."></i></label>
                        <input type="number" name="default_entitled" id="lt_default" class="form-control bg-dark border-secondary text-white" placeholder="e.g. 20" required min="0">
                        <div class="form-text text-white opacity-50 small mt-1" style="font-size: 0.7rem;">Default starting balance for new cycles.</div>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label text-white opacity-75 small">Carry Forward <i class="bi bi-info-circle ms-1 text-white opacity-50" data-bs-toggle="tooltip" title="Enable rolling over unused balances to the next year."></i></label>
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input bg-dark border-secondary" type="checkbox" name="allow_carry_forward" value="1" id="lt_allow_cf">
                                <label class="form-check-label text-white opacity-75 small" for="lt_allow_cf">Allow</label>
                            </div>
                            <input type="number" name="max_carry_forward_days" id="lt_max_cf" class="form-control form-control-sm bg-dark border-secondary text-white w-50" placeholder="Max (e.g. 5)" value="0" disabled>
                        </div>
                        <div class="form-text text-white opacity-50 small mt-1" style="font-size: 0.7rem;">Enable rolling over unused balances.</div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-end gap-2 mt-4 pt-2 border-top border-secondary border-opacity-25">
                    <button type="button" class="row-btn d-none" id="formCancelBtn" onclick="resetForm()">Cancel Edit</button>
                    <button type="submit" class="row-btn-blue" id="formSubmitBtn">Add Leave Type</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- List of Leave Types -->
    <div class="spotify-card mb-4">
        <div class="card-header-area px-4 pt-4 d-none">
            <h6 class="text-uppercase text-white fw-semibold small mb-2 opacity-75" style="letter-spacing: 0.05em;">Existing Leave Types</h6>
            <div class="grid-separator"></div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th class="ps-4">Name</th>
                        <th>Max/yr</th>
                        <th>Default</th>
                        <th>Carry Forward</th>
                        <th>Status</th>
                        <th class="pe-4 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leaveTypes as $type)
                        <tr>
                            <td class="ps-4 fw-semibold text-white">{{ $type->name }}</td>
                            <td><span class="text-white opacity-75">{{ $type->max_days_per_year }}</span></td>
                            <td><span class="text-white opacity-75">{{ $type->settings->first()?->default_entitled ?? $type->max_days_per_year }}</span></td>
                            <td>
                                @if($type->allow_carry_forward)
                                    <span class="row-badge-blue">Yes (Max: {{ $type->max_carry_forward_days }})</span>
                                @else
                                    <span class="text-white opacity-50 small">—</span>
                                @endif
                            </td>
                            <td>
                                @if($type->is_active)
                                    <span class="row-badge-green"><i class="bi bi-circle-fill text-success" style="font-size: 0.5rem; margin-right: 4px;"></i> Active</span>
                                @else
                                    <span class="row-badge" style="background: rgba(255, 255, 255, 0.1); color: rgba(255, 255, 255, 0.5); border-color: transparent;">Disabled</span>
                                @endif
                            </td>
                            <td class="pe-4 text-end">
                                <div class="d-inline-flex gap-2">
                                    <button type="button" class="row-btn-outline-blue" 
                                        onclick="editLeaveType({{ $type->id }}, '{{ addslashes($type->name) }}', {{ $type->max_days_per_year }}, {{ $type->settings->first()?->default_entitled ?? $type->max_days_per_year }}, {{ $type->allow_carry_forward ? 1 : 0 }}, {{ $type->max_carry_forward_days ?? 0 }})">
                                        Edit
                                    </button>
                                    
                                    <form method="POST" action="{{ route('super-admin.leave-types.toggle-status', $type) }}" class="d-inline toggle-status-form" data-pending="{{ $type->pending_requests_count }}" data-is-active="{{ $type->is_active ? 'true' : 'false' }}">
                                        @csrf
                                        @if($type->is_active)
                                            <button type="button" class="row-btn-outline-warning btn-toggle-status">Disable</button>
                                        @else
                                            <button type="button" class="row-btn-outline-success btn-toggle-status">Enable</button>
                                        @endif
                                    </form>

                                    <form method="POST" action="{{ route('super-admin.leave-types.destroy', $type) }}" class="d-inline delete-lt-form" data-pending="{{ $type->pending_requests_count }}">
                                        @csrf @method('DELETE')
                                        <button type="button" class="row-btn-outline-red btn-delete-lt">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5 text-white opacity-50">No leave types configured yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @push('modals')
    <!-- Alert Modal (Cannot Delete) -->
    <div class="modal fade" id="alertModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-white fs-6"><i class="bi bi-exclamation-circle-fill me-2 text-warning"></i> Cannot Delete Leave Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="text-white fs-6">There are pending leave requests for this type.</p>
                    <p class="mb-0 text-white opacity-50 small">You cannot delete a leave type that is currently in use. You can disable it instead to prevent new requests.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="row-btn-blue" data-bs-dismiss="modal" style="width: 145px;">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Disable Confirmation Modal -->
    <div class="modal fade" id="disableLtModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-white fs-6"><i class="bi bi-pause-circle-fill me-2 text-warning"></i> Disable Leave Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="text-white fs-6">Are you sure you want to disable this leave type?</p>
                    <p class="mb-0 text-white opacity-50 small"><i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> All pending leave requests of this type will be automatically rejected. Should we proceed?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 145px;">Cancel</button>
                    <button type="button" class="row-btn-outline-warning" id="confirmDisableLt" style="width: 145px;">Yes, Proceed</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteLtModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-white fs-6"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> Delete Leave Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-4">
                    <p class="text-white fs-6">Are you sure you want to delete this leave type?</p>
                    <p class="mb-0 text-white opacity-50 small"><strong>Warning:</strong> This will reflect changes on each user profile. Their leave balances and requests for this type will be deleted, and the records will not be maintained!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 145px;">Cancel</button>
                    <button type="button" class="row-btn-red" id="confirmDeleteLt" style="width: 145px;">Confirm Delete</button>
                </div>
            </div>
        </div>
    </div>
    @endpush

    @push('scripts')
        <script>
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Carry Forward Logic
            const cfCheckbox = document.getElementById('lt_allow_cf');
            const cfMaxInput = document.getElementById('lt_max_cf');

            function toggleCfInput() {
                if (cfCheckbox.checked) {
                    cfMaxInput.disabled = false;
                } else {
                    cfMaxInput.disabled = true;
                    cfMaxInput.value = '0';
                }
            }
            cfCheckbox.addEventListener('change', toggleCfInput);

            // Edit Logic
            const form = document.getElementById('leaveTypeForm');
            const formMethod = document.getElementById('formMethod');
            const formHeader = document.getElementById('formCardHeader');
            const formSubmitBtn = document.getElementById('formSubmitBtn');
            const formCancelBtn = document.getElementById('formCancelBtn');
            const originalAction = "{{ route('super-admin.leave-types.store') }}";

            window.editLeaveType = function(id, name, maxDays, defaultEntitled, allowCf, maxCf) {
                form.action = `/super-admin/leave-types/${id}`;
                formMethod.disabled = false; // Enables the PATCH method
                
                document.getElementById('lt_name').value = name;
                document.getElementById('lt_max_days').value = maxDays;
                document.getElementById('lt_default').value = defaultEntitled;
                
                cfCheckbox.checked = (allowCf == 1);
                toggleCfInput();
                cfMaxInput.value = maxCf;

                formHeader.textContent = 'Edit Leave Type';
                formHeader.classList.add('text-primary');
                formSubmitBtn.textContent = 'Update Leave Type';
                formCancelBtn.classList.remove('d-none');
                
                window.scrollTo({ top: 0, behavior: 'smooth' });
            };

            window.resetForm = function() {
                form.action = originalAction;
                formMethod.disabled = true;
                form.reset();
                toggleCfInput();
                
                formHeader.textContent = 'Add Leave Type';
                formHeader.classList.remove('text-primary');
                formSubmitBtn.textContent = 'Add Leave Type';
                formCancelBtn.classList.add('d-none');
            };

            // Status Toggle Logic
            let statusFormToSubmit = null;
            
            $(document).on('click', '.btn-toggle-status', function() {
                statusFormToSubmit = $(this).closest('form');
                const isActive = statusFormToSubmit.data('is-active') === true || statusFormToSubmit.data('is-active') === 'true';
                const pending = parseInt(statusFormToSubmit.data('pending')) || 0;

                if (isActive) {
                    // Show confirmation modal for disabling
                    const disableModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('disableLtModal'));
                    disableModal.show();
                } else {
                    // Enabling: just submit
                    statusFormToSubmit.submit();
                }
            });

            document.getElementById('confirmDisableLt').addEventListener('click', function() {
                if (statusFormToSubmit) {
                    statusFormToSubmit.submit();
                }
            });

            // Delete Logic
            let ltFormToSubmit = null;
            
            $(document).on('click', '.btn-delete-lt', function() {
                ltFormToSubmit = $(this).closest('form');
                const pending = parseInt(ltFormToSubmit.data('pending')) || 0;
                
                if (pending > 0) {
                    const alertModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('alertModal'));
                    alertModal.show();
                } else {
                    const ltModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteLtModal'));
                    ltModal.show();
                }
            });

            document.getElementById('confirmDeleteLt').addEventListener('click', function() {
                if (ltFormToSubmit) {
                    ltFormToSubmit.submit();
                }
            });
        </script>
    @endpush
</x-app-layout>
