<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <span>Edit User Profile</span>
            <a href="{{ route('super-admin.users.index') }}" class="row-btn text-decoration-none d-inline-flex align-items-center gap-2" style="font-size: 0.85rem; font-weight: normal; text-transform: none; line-height: 24px; padding: 6px 16px;">
                <i class="bi bi-arrow-left"></i> Back to User Directory
            </a>
        </div>
    </x-slot>

    <!-- Custom CSS styles to make the header span 100% width and style components -->
    <style>
        .main-content-area h1.h3 {
            width: 100%;
        }
        .profile-field-row {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
            padding-top: 10px;
            padding-bottom: 10px;
        }
        .profile-field-row:last-child {
            border-bottom: none !important;
        }
    </style>

    <!-- Wrap entire page structure inside form to enable bottom-most submit button -->
    <form id="edit-user-form" method="POST" action="{{ route('super-admin.users.update', $user) }}" class="user-update-form" data-user-id="{{ $user->id }}" data-department-id="{{ $user->department_id }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="confirm_head_replace" value="0" class="confirm-head-replace-input">
        <input type="hidden" name="redirect_to" id="redirect_to_input" value="">

        <!-- Main Grid Row -->
        <div class="row g-4 align-items-stretch">
            <!-- Left Side: User Profile Card (Stretched to match right column height) -->
            <div class="col-lg-4 d-flex flex-column">
                <div class="spotify-card p-4 flex-grow-1 d-flex flex-column justify-content-between">
                    <div class="d-flex flex-column justify-content-between flex-grow-1">
                        <!-- Avatar Circle -->
                        <div class="d-flex flex-column align-items-center text-center mb-3">
                            <div class="d-flex justify-content-center mb-2">
                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" 
                                     style="width: 90px; height: 90px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); font-size: 2rem; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.25);">
                                    {{ collect(explode(' ', $user->name))->map(fn($n) => mb_substr($n, 0, 1))->take(2)->join('') }}
                                </div>
                            </div>

                            <h4 id="card-header-name" class="fw-bold text-white mb-1">{{ $user->name }}</h4>
                            <span id="card-header-role-badge" class="badge px-3 py-1.5 rounded-pill small" 
                                  style="background-color: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.12); color: #ffffff;">
                                {{ $user->role->label }}
                            </span>
                        </div>

                        <!-- Complete list of fields (distributed evenly) -->
                        <div class="border-top border-secondary pt-2 text-start d-flex flex-column justify-content-between flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center profile-field-row">
                                <span class="text-white opacity-50 small">Full Name</span>
                                <span id="card-name" class="text-white fw-semibold small text-end">{{ $user->name }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center profile-field-row">
                                <span class="text-white opacity-50 small">Email Address</span>
                                <span id="card-email" class="text-white fw-semibold small text-break text-end" style="max-width: 65%;">{{ $user->email }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center profile-field-row">
                                <span class="text-white opacity-50 small">Phone Number</span>
                                <span id="card-phone" class="text-white fw-semibold small text-end">{{ $user->phone ?? '—' }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center profile-field-row">
                                <span class="text-white opacity-50 small">Department</span>
                                <span id="card-department" class="text-white fw-semibold small text-end">
                                    {{ $user->department?->name ?? 'None' }}
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center profile-field-row">
                                <span class="text-white opacity-50 small">System Role</span>
                                <span id="card-role" class="text-white fw-semibold small text-end">{{ $user->role->label }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center profile-field-row">
                                <span class="text-white opacity-50 small">Joined On</span>
                                <span id="card-join-date" class="text-white fw-semibold small text-end">
                                    {{ $user->join_date ? \Carbon\Carbon::parse($user->join_date)->format('M d, Y') : '—' }}
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center profile-field-row">
                                <span class="text-white opacity-50 small">Expected Salary</span>
                                <span id="card-salary" class="text-white fw-semibold small text-end">
                                    {{ $user->expected_salary_amount ? 'PKR ' . number_format($user->expected_salary_amount, 2) : '—' }}
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center profile-field-row">
                                <span class="text-white opacity-50 small">Hours Per Day</span>
                                <span id="card-hours-day" class="text-white fw-semibold small text-end">
                                    {{ $user->expected_hour_per_day ? $user->expected_hour_per_day . ' hrs' : '—' }}
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center profile-field-row">
                                <span class="text-white opacity-50 small">Hours Per Week</span>
                                <span id="card-hours-week" class="text-white fw-semibold small text-end">
                                    {{ $user->expected_hours_per_week ? $user->expected_hours_per_week . ' hrs' : '—' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Edit Form Fields in separate cards -->
            <div class="col-lg-8 d-flex flex-column justify-content-between">
                <div>
                    <!-- Card 1: Personal Details (Read-only) -->
                    <div class="spotify-card p-4 mb-4">
                        <h5 class="fw-bold text-white mb-3 pb-2 border-bottom border-secondary" style="font-size: 1.05rem;">
                            <i class="bi bi-person me-2 text-primary"></i> Personal Details
                        </h5>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small text-white opacity-50">Full Name</label>
                                <div class="input-group gap-2">
                                    <span class="input-group-text border-0 rounded" style="background-color: rgba(255,255,255,0.02); color: rgba(255,255,255,0.3); min-width: 46px; justify-content: center;"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control border-0 rounded" value="{{ $user->name }}" disabled style="background-color: rgba(255, 255, 255, 0.02) !important; color: rgba(255,255,255,0.45);">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-white opacity-50">Email Address</label>
                                <div class="input-group gap-2">
                                    <span class="input-group-text border-0 rounded" style="background-color: rgba(255,255,255,0.02); color: rgba(255,255,255,0.3); min-width: 46px; justify-content: center;"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control border-0 rounded" value="{{ $user->email }}" disabled style="background-color: rgba(255, 255, 255, 0.02) !important; color: rgba(255,255,255,0.45);">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small text-white opacity-50">Phone Number</label>
                                <div class="input-group gap-2">
                                    <span class="input-group-text border-0 rounded" style="background-color: rgba(255,255,255,0.02); color: rgba(255,255,255,0.3); min-width: 46px; justify-content: center;"><i class="bi bi-telephone"></i></span>
                                    <input type="text" class="form-control border-0 rounded" value="{{ $user->phone ?? '—' }}" disabled style="background-color: rgba(255, 255, 255, 0.02) !important; color: rgba(255,255,255,0.45);">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 2: Employment Information & Compensation -->
                    <div class="spotify-card p-4">
                        <!-- Subsection: Employment Details -->
                        <h5 class="fw-bold text-white mb-3 pb-2 border-bottom border-secondary" style="font-size: 1.05rem;">
                            <i class="bi bi-briefcase me-2 text-primary"></i> Employment Information
                        </h5>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="join_date" class="form-label small text-white opacity-75">Join Date</label>
                                <input type="date" name="join_date" id="join_date" class="form-control @error('join_date') is-invalid @enderror" value="{{ old('join_date', $user->join_date ? \Carbon\Carbon::parse($user->join_date)->format('Y-m-d') : '') }}" style="background-color: #121212 !important; border: 1px solid rgba(255, 255, 255, 0.12); color: #ffffff;">
                                @error('join_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="role_id" class="form-label small text-white opacity-75">Assign System Role</label>
                                <select name="role_id" id="role_id" class="form-select @error('role_id') is-invalid @enderror" required style="background-color: #121212 !important; border: 1px solid rgba(255, 255, 255, 0.12); color: #ffffff;">
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}" {{ old('role_id', $user->role_id) == $role->id ? 'selected' : '' }}>{{ $role->label }}</option>
                                    @endforeach
                                </select>
                                @error('role_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-12">
                                <label for="department_id" class="form-label small text-white opacity-75">Assign Department</label>
                                <select name="department_id" id="department_id" class="form-select @error('department_id') is-invalid @enderror" style="background-color: #121212 !important; border: 1px solid rgba(255, 255, 255, 0.12); color: #ffffff;">
                                    <option value="">— Department —</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->id }}" {{ old('department_id', $user->department_id) == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                                    @endforeach
                                </select>
                                @error('department_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Subsection: Compensation & Hours -->
                        <h5 class="fw-bold text-white mb-3 pb-2 border-bottom border-secondary" style="font-size: 1.05rem;">
                            <i class="bi bi-clock me-2 text-primary"></i> Compensation & Schedule
                        </h5>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="expected_salary_amount" class="form-label small text-white opacity-75">Expected Salary Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text" style="background-color: #1a1a1a; border: 1px solid rgba(255, 255, 255, 0.12); color: rgba(255, 255, 255, 0.5); font-size: 0.8rem;">PKR</span>
                                    <input type="number" step="0.01" name="expected_salary_amount" id="expected_salary_amount" class="form-control @error('expected_salary_amount') is-invalid @enderror" value="{{ old('expected_salary_amount', $user->expected_salary_amount) }}" placeholder="e.g. 50000.00" style="background-color: #121212 !important; border: 1px solid rgba(255, 255, 255, 0.12); color: #ffffff; border-left: none;">
                                </div>
                                @error('expected_salary_amount')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="expected_hour_per_day" class="form-label small text-white opacity-75">Expected Hours Per Day</label>
                                <input type="number" step="0.1" max="24" name="expected_hour_per_day" id="expected_hour_per_day" class="form-control @error('expected_hour_per_day') is-invalid @enderror" value="{{ old('expected_hour_per_day', $user->expected_hour_per_day) }}" placeholder="e.g. 8.0" style="background-color: #121212 !important; border: 1px solid rgba(255, 255, 255, 0.12); color: #ffffff;">
                                @error('expected_hour_per_day')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="expected_hours_per_week" class="form-label small text-white opacity-75">Expected Hours Per Week</label>
                                <input type="number" step="0.1" name="expected_hours_per_week" id="expected_hours_per_week" class="form-control @error('expected_hours_per_week') is-invalid @enderror" value="{{ old('expected_hours_per_week', $user->expected_hours_per_week) }}" placeholder="Auto calculated" readonly style="background-color: #1a1a1a !important; border: 1px solid rgba(255, 255, 255, 0.12); color: rgba(255, 255, 255, 0.65);">
                                <div class="form-text text-white opacity-40 small mt-1">Calculated as: Hours Per Day × 5</div>
                                @error('expected_hours_per_week')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Area: Personal Leave Ledger (Takes 100% full width of screen) -->
        <div class="row mt-4">
            <div class="col-12">
                <!-- Spotify-Inspired Leave Tracklist -->
                <div class="spotify-card flush-list-card">
                    <div class="card-header-area px-4 pt-4 d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase text-white fw-semibold small mb-0 opacity-75" style="letter-spacing: 0.05em;">
                                <i class="bi bi-pie-chart-fill me-2 text-primary"></i> Leave Balance Ledger
                            </h6>
                            <p class="text-white opacity-50 small mt-1 mb-2">Dynamic leave balance tracking. Click individual leave types to expand details.</p>
                        </div>
                        <div>
                            <a href="{{ route('super-admin.leave-settings.overrides', ['filter_user_id' => $user->id]) }}" class="row-btn-outline-warning text-decoration-none py-1.5 px-3 d-inline-flex align-items-center gap-2 override-leave-btn">
                                <i class="bi bi-sliders me-1"></i> Override Leave Settings
                            </a>
                        </div>
                    </div>
                    <div class="grid-separator mx-4 my-2"></div>

                    <div class="list-group list-group-flush mt-2">
                        @foreach($balances as $index => $balance)
                            @php
                                $allowed = $balance->entitled + $balance->carried_forward;
                                $used = $balance->used;
                                $remaining = $balance->remaining;
                                $pending = $balance->pending;
                                $percentUsed = $allowed > 0 ? min(100, round(($used / $allowed) * 100)) : 0;
                                $percentPending = ($allowed > 0 && $pending > 0) ? min(100, round(($pending / $allowed) * 100)) : 0;
                            @endphp
                            
                            <!-- Leave Track Item -->
                            <div class="list-group-item bg-transparent d-flex align-items-center justify-content-between py-3 px-4 border-0 hover-highlight cursor-pointer" 
                                 style="transition: background-color 0.2s ease-in-out; border-bottom: 1px solid rgba(255,255,255,0.03) !important;"
                                 data-bs-toggle="collapse" 
                                 data-bs-target="#trackCollapseStats-{{ $index }}">
                                
                                <!-- Index & Name -->
                                <div class="d-flex align-items-center" style="min-width: 180px;">
                                    <span class="text-white opacity-25 me-3 small font-monospace">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                                    <div>
                                        <div class="fw-semibold text-white fs-6" style="letter-spacing: -0.02em;">{{ $balance->leaveType->name }}</div>
                                        <small class="text-white opacity-50" style="font-size: 0.72rem;">{{ $year }} cycle</small>
                                    </div>
                                </div>
                                
                                <!-- Spotify Visual Duration Progress Bar -->
                                <div class="flex-grow-1 mx-4 d-none d-md-block">
                                    <div class="progress bg-dark position-relative" style="height: 6px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.06); overflow: visible;">
                                        <!-- Used indicator segment (stark white) -->
                                        <div class="progress-bar bg-white" style="width: {{ $percentUsed }}%; border-radius: 50px;"></div>
                                        <!-- Pending indicator segment -->
                                        @if($percentPending > 0)
                                            <div class="progress-bar bg-white" style="width: {{ $percentPending }}%; border-radius: 50px;"></div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Numeric Track Balance -->
                                <div class="d-flex align-items-center gap-4">
                                    <div class="text-end" style="min-width: 130px;">
                                        <span class="fw-bold text-white fs-6">{{ $remaining }} Left</span>
                                        <div class="text-white opacity-50 small" style="font-size: 0.7rem; text-transform: uppercase;">of {{ $allowed }} Allowed</div>
                                    </div>
                                    <i class="bi bi-chevron-down text-white opacity-25 small"></i>
                                </div>
                            </div>

                            <!-- Carried Forward and Extra Detail collapse list inside the ledger -->
                            <div class="collapse" id="trackCollapseStats-{{ $index }}" style="background-color: rgba(255,255,255,0.015);">
                                <div class="py-3 px-5 border-bottom" style="border-color: rgba(255,255,255,0.04) !important;">
                                    <div class="row text-start py-2 g-3">
                                        <div class="col-sm-3">
                                            <label class="text-white small opacity-50 d-block mb-1" style="font-size: 0.68rem; text-transform: uppercase;">Base Entitled</label>
                                            <strong class="text-white">{{ $balance->entitled }} days</strong>
                                        </div>
                                        <div class="col-sm-3">
                                            <label class="text-white small opacity-50 d-block mb-1" style="font-size: 0.68rem; text-transform: uppercase;">Carried Over</label>
                                            <strong class="{{ $balance->carried_forward > 0 ? 'text-success' : 'text-white' }}">+{{ $balance->carried_forward }} days</strong>
                                        </div>
                                        <div class="col-sm-3">
                                            <label class="text-white small opacity-50 d-block mb-1" style="font-size: 0.68rem; text-transform: uppercase;">Pending Approval</label>
                                            <strong class="text-warning">{{ $pending }} days</strong>
                                        </div>
                                        <div class="col-sm-3">
                                            <label class="text-white small opacity-50 d-block mb-1" style="font-size: 0.68rem; text-transform: uppercase;">Utilized</label>
                                            <strong class="text-white">{{ $used }} days</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Actions at the very bottom of the page (below leave ledger) -->
        <div class="d-flex justify-content-end gap-2 mt-4 pb-4">
            <a href="{{ route('super-admin.users.index') }}" class="row-btn text-center" style="width: 145px; line-height: 24px; display: inline-block; text-decoration: none;">Cancel</a>
            <button type="submit" class="row-btn-blue" style="width: 175px;">Save Profile Changes</button>
        </div>
    </form>

    @push('modals')
    <!-- Hierarchy Replacement Confirmation Modal (Pop-up standard for entire system) -->
    <div class="modal fade" id="replaceHeadModal" tabindex="-1" style="z-index: 1070;">
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

    <!-- Unsaved Changes Modal Popup -->
    <div class="modal fade" id="unsavedChangesModal" tabindex="-1" style="z-index: 1070;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow position-relative" style="background-color: #212529 !important;">
                <!-- Close Button on Top Right -->
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close" style="z-index: 1080 !important;"></button>
                
                <div class="modal-body p-4 text-center mt-3">
                    <div class="mb-3 text-warning">
                        <i class="bi bi-exclamation-triangle-fill" style="font-size: 3.5rem;"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-white">Unsaved Changes</h5>
                    <p class="text-white opacity-50 small mb-4">
                        You have unsaved changes on this page. Would you like to save them before redirecting to override leave settings page?
                    </p>
                    <div class="d-flex justify-content-center gap-2 mt-4">
                        <button type="button" class="row-btn" id="btn-skip" style="width: 145px;">Skip</button>
                        <button type="button" class="row-btn-blue" id="btn-save-and-proceed" style="width: 150px;">Save & Proceed</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endpush

    @push('scripts')
        <script src="{{ asset('js/hierarchy.js') }}"></script>
        <script>
            $(function() {
                // Store original values of all tracked fields
                const originalValues = {};
                $('#edit-user-form input, #edit-user-form select').each(function() {
                    const id = $(this).attr('id') || $(this).attr('name');
                    if (id && id !== 'redirect_to_input' && !$(this).hasClass('confirm-head-replace-input') && $(this).attr('name') !== '_token' && $(this).attr('name') !== '_method') {
                        originalValues[id] = $(this).val();
                    }
                });

                // Function to check if any field has actual changes compared to load time
                function checkIsFormDirty() {
                    let dirty = false;
                    $('#edit-user-form input, #edit-user-form select').each(function() {
                        const id = $(this).attr('id') || $(this).attr('name');
                        if (id && originalValues.hasOwnProperty(id)) {
                            if ($(this).val() !== originalValues[id]) {
                                dirty = true;
                                return false; // break loop
                            }
                        }
                    });
                    return dirty;
                }

                // Intercept Override Leave Settings click
                $('.override-leave-btn').on('click', function(e) {
                    const targetUrl = $(this).attr('href');
                    const isDirty = checkIsFormDirty();
                    if (isDirty) {
                        e.preventDefault();

                        // Open modal using getOrCreateInstance to prevent multiple backdrop bugs
                        const modalElement = document.getElementById('unsavedChangesModal');
                        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
                        modal.show();

                        // Skip (Proceed without saving) handler
                        $('#btn-skip').off('click').on('click', function() {
                            // Revert input values to original to bypass dirty checks
                            $('#edit-user-form input, #edit-user-form select').each(function() {
                                const id = $(this).attr('id') || $(this).attr('name');
                                if (id && originalValues.hasOwnProperty(id)) {
                                    $(this).val(originalValues[id]);
                                }
                            });
                            modal.hide();
                            window.location.href = targetUrl;
                        });

                        // Save & Proceed handler
                        $('#btn-save-and-proceed').off('click').on('click', function() {
                            $('#redirect_to_input').val(targetUrl);
                            modal.hide();
                            $('#edit-user-form').submit();
                        });
                    }
                });

                // Auto calculate hours per week based on hours per day (Hours * 5)
                $('#expected_hour_per_day').on('input change', function() {
                    const val = parseFloat($(this).val());
                    if (!isNaN(val) && val >= 0) {
                        $('#expected_hours_per_week').val((val * 5).toFixed(1));
                    } else {
                        $('#expected_hours_per_week').val('');
                    }
                });

                // Trigger initial calculation if values exist
                const initialVal = parseFloat($('#expected_hour_per_day').val());
                if (!isNaN(initialVal) && initialVal >= 0) {
                    $('#expected_hours_per_week').val((initialVal * 5).toFixed(1));
                }

                // Live updates for card on left from inputs on right
                // Department updates on left card
                $('#department_id').on('change', function() {
                    const text = $(this).find('option:selected').val() ? $(this).find('option:selected').text() : 'None';
                    $('#card-department').text(text);
                });

                // Role updates on left card
                $('#role_id').on('change', function() {
                    const text = $(this).find('option:selected').text();
                    $('#card-role').text(text);
                    $('#card-header-role-badge').text(text);
                });

                // Join date updates on left card
                $('#join_date').on('change input', function() {
                    const val = $(this).val();
                    if (val) {
                        const date = new Date(val);
                        const options = { year: 'numeric', month: 'short', day: '2-digit', timeZone: 'UTC' };
                        $('#card-join-date').text(date.toLocaleDateString('en-US', options));
                    } else {
                        $('#card-join-date').text('—');
                    }
                });

                // Salary updates on left card
                $('#expected_salary_amount').on('input change', function() {
                    const val = parseFloat($(this).val());
                    if (!isNaN(val) && val >= 0) {
                        $('#card-salary').text('PKR ' + val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                    } else {
                        $('#card-salary').text('—');
                    }
                });

                // Hours per day / week updates on left card
                $('#expected_hour_per_day').on('input change', function() {
                    const val = parseFloat($(this).val());
                    if (!isNaN(val) && val >= 0) {
                        $('#card-hours-day').text(val + ' hrs');
                        const weekVal = (val * 5).toFixed(1);
                        $('#card-hours-week').text(weekVal + ' hrs');
                    } else {
                        $('#card-hours-day').text('—');
                        $('#card-hours-week').text('—');
                    }
                });
            });
        </script>
    @endpush
</x-app-layout>
