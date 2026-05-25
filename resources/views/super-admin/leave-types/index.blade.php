<x-app-layout>
    <x-slot name="header">Leave Types</x-slot>
    
    <div class="row g-4">
        <!-- Add / Edit Form -->
        <div class="col-lg-4">
            <div class="card shadow-sm position-sticky" style="top: 20px;">
                <div class="card-header fw-bold" id="formCardHeader">Add Leave Type</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('super-admin.leave-types.store') }}" id="leaveTypeForm">
                        @csrf
                        <input type="hidden" name="_method" id="formMethod" value="PATCH" disabled>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Name <i class="bi bi-info-circle ms-1 text-muted" data-bs-toggle="tooltip" title="The name of the leave type (e.g. Annual Leave, Sick Leave)"></i></label>
                            <input type="text" name="name" id="lt_name" class="form-control" placeholder="e.g. Annual Leave" required>
                            <div class="form-text small">Identifier for this leave type.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Max days/year <i class="bi bi-info-circle ms-1 text-muted" data-bs-toggle="tooltip" title="The absolute hard limit of days any employee can take in a single year for this leave."></i></label>
                            <input type="number" name="max_days_per_year" id="lt_max_days" class="form-control" placeholder="e.g. 30" required min="0">
                            <div class="form-text small">Ceiling limit for taken leaves per year.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Default entitled <i class="bi bi-info-circle ms-1 text-muted" data-bs-toggle="tooltip" title="The standard starting balance given to employees at the beginning of the year."></i></label>
                            <input type="number" name="default_entitled" id="lt_default" class="form-control" placeholder="e.g. 20" required min="0">
                            <div class="form-text small">Default starting balance for new cycles.</div>
                        </div>
                        
                        <div class="mb-3 p-3 border rounded bg-light">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="allow_carry_forward" value="1" id="lt_allow_cf">
                                <label class="form-check-label fw-semibold" for="lt_allow_cf">
                                    Allow carry forward <i class="bi bi-info-circle ms-1 text-muted" data-bs-toggle="tooltip" title="If checked, employees can roll over unused days to the next year."></i>
                                </label>
                            </div>
                            <div class="form-text small mb-2">Enable rolling over unused balances.</div>
                            
                            <label class="form-label fw-semibold small">Max carry forward <i class="bi bi-info-circle ms-1 text-muted" data-bs-toggle="tooltip" title="Maximum number of unused days that can be rolled over."></i></label>
                            <input type="number" name="max_carry_forward_days" id="lt_max_cf" class="form-control form-control-sm" placeholder="e.g. 5" value="0" disabled>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100" id="formSubmitBtn">Add Leave Type</button>
                        <button type="button" class="btn btn-outline-secondary w-100 mt-2 d-none" id="formCancelBtn" onclick="resetForm()">Cancel Edit</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- List of Leave Types -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header">Existing Leave Types</div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Max/yr</th>
                                <th>Default</th>
                                <th>Carry Forward</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($leaveTypes as $type)
                                <tr>
                                    <td class="fw-medium">{{ $type->name }}</td>
                                    <td>{{ $type->max_days_per_year }}</td>
                                    <td>{{ $type->settings->first()?->default_entitled ?? $type->max_days_per_year }}</td>
                                    <td>
                                        @if($type->allow_carry_forward)
                                            <span class="badge bg-info text-dark">Yes (Max: {{ $type->max_carry_forward_days }})</span>
                                        @else
                                            <span class="text-muted small">No</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($type->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Disabled</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary me-1" 
                                            onclick="editLeaveType({{ $type->id }}, '{{ addslashes($type->name) }}', {{ $type->max_days_per_year }}, {{ $type->settings->first()?->default_entitled ?? $type->max_days_per_year }}, {{ $type->allow_carry_forward ? 1 : 0 }}, {{ $type->max_carry_forward_days ?? 0 }})">
                                            Edit
                                        </button>
                                        
                                        <form method="POST" action="{{ route('super-admin.leave-types.toggle-status', $type) }}" class="d-inline toggle-status-form" data-pending="{{ $type->pending_requests_count }}" data-is-active="{{ $type->is_active ? 'true' : 'false' }}">
                                            @csrf
                                            @if($type->is_active)
                                                <button type="button" class="btn btn-sm btn-outline-warning btn-toggle-status me-1">Disable</button>
                                            @else
                                                <button type="button" class="btn btn-sm btn-outline-success btn-toggle-status me-1">Enable</button>
                                            @endif
                                        </form>

                                        <form method="POST" action="{{ route('super-admin.leave-types.destroy', $type) }}" class="d-inline delete-lt-form" data-pending="{{ $type->pending_requests_count }}">
                                            @csrf @method('DELETE')
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete-lt">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No leave types configured yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Modal (Cannot Delete) -->
    <div class="modal fade" id="alertModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cannot Delete Leave Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>There are pending leave requests for this type.</p>
                    <p class="mb-0 text-muted small">You cannot delete a leave type that is currently in use. You can disable it instead to prevent new requests.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Disable Confirmation Modal -->
    <div class="modal fade" id="disableLtModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-warning">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">Disable Leave Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="fs-5">Are you sure you want to disable this leave type?</p>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> All pending leave requests of this type will be automatically rejected. Should we proceed?
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="confirmDisableLt">Yes, Proceed</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteLtModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-danger">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Delete Leave Type</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="fs-5">Are you sure you want to delete this leave type?</p>
                    <div class="alert alert-danger mb-0">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>Warning:</strong> This will reflect changes on each user profile. Their leave balances and requests for this type will be deleted, and the records will not be maintained!
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteLt">Yes, Delete Permanently</button>
                </div>
            </div>
        </div>
    </div>

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
