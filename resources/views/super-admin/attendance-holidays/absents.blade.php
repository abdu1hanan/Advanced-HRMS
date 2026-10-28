<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <span>Attendance Flags & Settings</span>
        </div>
    </x-slot>

    <!-- Custom styling inside page -->
    <style>
        .form-section-card {
            border: 1px solid rgba(255, 255, 255, 0.06);
            background-color: rgba(255, 255, 255, 0.01);
            border-radius: 8px;
        }
        .custom-employee-dropdown .dropdown-item:active {
            background-color: rgba(255, 255, 255, 0.08) !important;
        }
        .employee-option-row {
            transition: background-color 0.2s ease;
            cursor: pointer;
        }
        .employee-option-row:hover {
            background-color: rgba(255, 255, 255, 0.05) !important;
        }
        .custom-employee-dropdown .dropdown-item {
            display: flex;
            align-items: center;
        }
        .option-choice-card {
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            transition: all 0.2s ease-in-out;
            cursor: pointer;
        }
        .option-choice-card:hover {
            border-color: rgba(255, 255, 255, 0.25) !important;
            opacity: 1 !important;
        }
        .option-choice-card.active {
            background-color: rgba(59, 130, 246, 0.1) !important;
            border-color: #3b82f6 !important;
            opacity: 1 !important;
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.25) !important;
        }
        .row-btn-outline-yellow {
            background-color: transparent !important;
            border: 1.5px solid #f59e0b !important;
            color: #f59e0b !important;
            font-weight: 600 !important;
            transition: all 0.25s ease-in-out;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50px !important;
        }
        .row-btn-outline-yellow:hover {
            background-color: #f59e0b !important;
            color: #000000 !important;
            box-shadow: 0 0 12px rgba(245, 158, 11, 0.4) !important;
        }
        
        .conflict-scrollbar {
            max-height: 105px;
            overflow-y: scroll !important;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.15) rgba(255, 255, 255, 0.02);
        }
        .conflict-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .conflict-scrollbar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.02);
            border-radius: 4px;
        }
        .conflict-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 4px;
        }
        .conflict-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
        }
    </style>

    <div class="row g-4 align-items-stretch">
        <!-- Left Column: Marking Form -->
        <div class="col-lg-6 d-flex flex-column">
            <!-- Form Card: Mark Attendance Status -->
            <div class="spotify-card p-4 flex-grow-1 d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-uppercase text-white fw-bold mb-0 opacity-75 small">
                        Mark Daily Status Flag
                    </h6>
                    <!-- Bulk Mode Switch styled like mappings page -->
                    <div class="d-flex align-items-center gap-3 px-3 py-1" style="background: rgba(255, 255, 255, 0.05); border-radius: 50px; border: 1px solid rgba(255, 255, 255, 0.1); margin-bottom: 0;">
                        <i class="bi bi-people text-white opacity-75" style="font-size: 0.85rem;"></i>
                        <span class="text-white small opacity-75" style="user-select: none; font-size: 0.82rem;">Bulk Mode</span>
                        <div class="form-check form-switch mb-0" style="padding-left: 0;">
                            <input class="form-check-input bg-dark border-secondary cursor-pointer" type="checkbox" id="bulkMarkingToggle" name="bulk_mode" value="1" style="width: 2.2em; height: 1.1em; margin-left: 0;">
                        </div>
                    </div>
                </div>
                <hr class="border-secondary opacity-25 mb-4">
                
                <div class="d-flex gap-2 mb-4 p-1" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 50px;">
                    <button type="button" id="tabModeAbsent" class="btn btn-sm flex-grow-1 text-white py-2 fw-semibold" style="border-radius: 50px; background-color: #3b82f6; transition: all 0.2s;">
                        <i class="bi bi-person-x me-1"></i>Absent Mode
                    </button>
                    <button type="button" id="tabModeRemote" class="btn btn-sm flex-grow-1 text-white py-2" style="border-radius: 50px; background-color: transparent; transition: all 0.2s;">
                        <i class="bi bi-house-door me-1"></i>Remote Mode
                    </button>
                    <button type="button" id="tabModeLeave" class="btn btn-sm flex-grow-1 text-white py-2" style="border-radius: 50px; background-color: transparent; transition: all 0.2s;">
                        <i class="bi bi-calendar-check me-1"></i>Leave Mode
                    </button>
                </div>
                
                <form id="markAbsentForm" action="{{ route('super-admin.attendance-holidays.absents.store') }}" method="POST" class="d-flex flex-column flex-grow-1 justify-content-between">
                    @csrf

                    <div class="flex-grow-1">
                        <!-- Custom Dropdown Selector -->
                        <div class="mb-3" id="employeeSelectGroup">
                            <label class="form-label small text-white opacity-70" id="employeeSelectLabel">Select Employee</label>
                            
                            <div class="dropdown custom-employee-dropdown position-relative">
                                <i class="bi bi-search position-absolute text-white-50" style="left: 14px; top: 50%; transform: translateY(-50%); font-size: 0.9rem; z-index: 5;"></i>
                                <input type="text" id="employeeSearchInput" class="form-select text-start text-white border-0 w-100 py-2 px-3" 
                                       placeholder="-- Select Employee --" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-form-type="other" role="combobox" aria-autocomplete="list" data-bs-auto-close="outside" aria-expanded="false"
                                       style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; font-size: 0.9rem; padding-left: 42px !important;">
                                <div id="hiddenInputsContainer"></div>
                                <div class="dropdown-menu dropdown-menu-dark p-2 w-100 shadow border-0" id="employeeDropdownMenu"
                                     style="background-color: #121212; border: 1px solid rgba(255, 255, 255, 0.08) !important; max-height: 180px; overflow-y: auto;">
                                    <div id="selectAllContainer" class="dropdown-item py-1 px-2 mb-2 border-bottom border-secondary border-opacity-25 pb-2 d-none d-flex align-items-center" style="cursor: pointer;">
                                        <input type="checkbox" id="selectAllEmployees" class="me-2" style="width: 15px; height: 15px; min-width: 15px; min-height: 15px; accent-color: #3b82f6; cursor: pointer; margin: 0; vertical-align: middle;">
                                        <span class="text-white small fw-bold">Select All</span>
                                    </div>
                                    <div id="employeeOptionsList">
                                        @foreach($users as $user)
                                            <div class="dropdown-item py-1 px-2 rounded employee-option-row d-flex align-items-center" 
                                                 data-id="{{ $user->id }}" 
                                                 data-name="{{ strtolower($user->name) }}">
                                                <input type="checkbox" class="me-2 option-checkbox d-none" value="{{ $user->id }}" id="optCheck_{{ $user->id }}" style="width: 15px; height: 15px; min-width: 15px; min-height: 15px; accent-color: #3b82f6; cursor: pointer; margin: 0; vertical-align: middle;">
                                                <span class="text-white small option-label-text">{{ $user->name }} ({{ $user->employee_code ?? 'No Code' }})</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3" id="absentDateGroup">
                            <div class="row g-2 align-items-end">
                                <div class="col-12" id="absentDateCol">
                                    <label class="form-label small text-white opacity-70">Date</label>
                                    <input type="date" name="date" id="absentDate" class="form-control border-0 text-white"
                                           value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required
                                           style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; height: 38px;">
                                </div>
                                <div class="col-md-4 d-none" id="remoteStartTimeCol">
                                    <label class="form-label small text-white opacity-70">Start Time <span class="opacity-50" style="font-size: 0.72rem;">(Optional)</span></label>
                                    <input type="time" name="start_time" id="remoteStartTime" class="form-control border-0 text-white"
                                           style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; height: 38px;">
                                </div>
                                <div class="col-md-4 d-none" id="remoteEndTimeCol">
                                    <label class="form-label small text-white opacity-70">End Time <span class="opacity-50" style="font-size: 0.72rem;">(Optional)</span></label>
                                    <input type="time" name="end_time" id="remoteEndTime" class="form-control border-0 text-white"
                                           style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; height: 38px;">
                                </div>
                            </div>
                            <small class="text-white-50 mt-1 d-block" id="dateHelpText" style="font-size: 0.75rem; opacity: 0.6;">Can only mark absent for today or past dates.</small>
                        </div>

                        <div class="mb-4" id="notesInputGroup">
                            <label class="form-label small text-white opacity-70">Notes / Reason (Optional)</label>
                            <input type="text" name="notes" class="form-control text-white border-0" placeholder="e.g. Uninformed leave, sick day, etc."
                                   style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; border-radius: 50px !important; height: 38px;">
                        </div>

                        {{-- Leave Mode Fields (hidden by default) --}}
                        <div id="leaveModeFields" class="d-none">
                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label small text-white opacity-70">Leave Type</label>
                                    <select name="leave_type_id" id="leaveTypeSelect" class="form-select text-white border-0"
                                            style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; height: 38px;">
                                        <option value="" class="bg-dark">-- Select Leave Type --</option>
                                        @foreach($leaveTypes as $lt)
                                            <option value="{{ $lt->id }}" class="bg-dark">{{ $lt->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-white opacity-70">Start Date</label>
                                    <input type="date" name="leave_start_date" id="leaveStartDate" class="form-control border-0 text-white"
                                           value="{{ date('Y-m-d') }}"
                                           style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; height: 38px;">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-white opacity-70">End Date</label>
                                    <input type="date" name="leave_end_date" id="leaveEndDate" class="form-control border-0 text-white"
                                           value="{{ date('Y-m-d') }}"
                                           style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; height: 38px;">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-white opacity-70">Notes / Reason (Optional)</label>
                                <input type="text" name="leave_notes" id="leaveNotes" class="form-control text-white border-0" placeholder="e.g. Approved leave request reason, etc."
                                       style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important; border-radius: 50px !important; height: 38px;">
                            </div>
                            <p class="text-white-50 small mb-0" style="font-size: 0.75rem; opacity: 0.6;">
                                <i class="bi bi-info-circle me-1"></i>Leave will be immediately fully approved and deducted from the employee's balance.
                            </p>
                        </div>
                    </div>

                    <div class="d-flex flex-column gap-2 w-100">
                        <button type="submit" id="markAbsentBtn" class="row-btn-blue w-100" style="border-radius: 50px !important; height: 44px !important;">
                            <i class="bi bi-person-x me-1" id="btnActionIcon"></i><span id="btnActionText">Mark Absent</span>
                        </button>
                    </div>
                </form>

            </div>
        </div>

        <!-- Right Column: Settings Card -->
        <div class="col-lg-6 d-flex flex-column">
            <!-- Card: Daily Break & Session Settings -->
            <div class="spotify-card p-4 flex-grow-1 d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-uppercase text-white fw-bold mb-0 opacity-75 small">
                        Daily Break & Session Settings
                    </h6>
                </div>
                <hr class="border-secondary opacity-25 mb-4">
                
                <div class="d-flex flex-column flex-grow-1 justify-content-between">
                    <div>
                        <!-- Sleek Oval Active Model Banner -->
                        <div class="mb-4 d-flex align-items-center justify-content-between" 
                             style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.06); border-radius: 50px !important; padding: 12px 26px !important;">
                            <div>
                                <div class="small text-white opacity-50 mb-1" style="font-size: 0.76rem;">Daily Break Allowance</div>
                                <div class="fw-bold text-white small" style="font-size: 0.88rem;">
                                    <i class="bi bi-clock me-1 text-white opacity-75"></i>{{ $breakSettings['break_balance_minutes'] ?? 60 }} Minutes / Day
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="small text-white opacity-50 mb-1" style="font-size: 0.76rem;">Grace Period Buffer</div>
                                <div class="fw-bold text-white small" style="font-size: 0.88rem;">
                                    +{{ $breakSettings['break_grace_period_minutes'] ?? 15 }} Minutes
                                </div>
                            </div>
                        </div>

                        <!-- Break Setting Form -->
                        <form id="breakSettingsForm" action="{{ route('super-admin.attendance-holidays.break-settings.update') }}" method="POST">
                            @csrf
                            
                            <!-- Field 1: Official Daily Break Balance -->
                            <div class="mb-3">
                                <label class="form-label small text-white opacity-70 mb-1 fw-semibold">
                                    Official Daily Break Allowance (Minutes)
                                </label>
                                <input type="number" name="break_balance_minutes" id="break_balance_minutes" 
                                       class="form-control text-white" 
                                       value="{{ $breakSettings['break_balance_minutes'] ?? 60 }}" 
                                       min="1" max="300" required
                                       style="height: 38px !important; border-radius: 50px !important; padding: 0 1.1rem !important; font-size: 0.85rem !important; background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;">
                                <div class="text-white opacity-40 small mt-1 ps-2" style="font-size: 0.72rem;">
                                    Official daily break allowance displayed on the employee dashboard and alert messages.
                                </div>
                            </div>

                            <!-- Field 2: Hidden Grace Period Buffer -->
                            <div class="mb-4">
                                <label class="form-label small text-white opacity-70 mb-1 fw-semibold">
                                    Grace Period Buffer (Minutes)
                                </label>
                                <input type="number" name="break_grace_period_minutes" id="break_grace_period_minutes" 
                                       class="form-control text-white" 
                                       value="{{ $breakSettings['break_grace_period_minutes'] ?? 15 }}" 
                                       min="0" max="120" required
                                       style="height: 38px !important; border-radius: 50px !important; padding: 0 1.1rem !important; font-size: 0.85rem !important; background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;">
                                <div class="text-white opacity-40 small mt-1 ps-2" style="font-size: 0.72rem;">
                                    Hidden extra break buffer before session deductions or alerts kick in (Set to 0 to disable).
                                </div>
                            </div>
                        </form>
                    </div>

                    <button type="button" class="row-btn-green w-100 mt-4" id="btnSaveBreakSettings" style="border-radius: 50px !important; height: 42px !important;">
                        Save Break Settings
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Call to Action Card: Attendance Logs Link -->
    <div class="spotify-card mt-4 mb-4">
        <div class="card-header-area px-4 pt-4">
            <h6 class="text-uppercase text-white fw-semibold small mb-2 opacity-75" style="letter-spacing: 0.05em;">Daily Status Logs</h6>
            <div class="grid-separator"></div>
        </div>
        <div class="px-4 pb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <p class="text-white opacity-75 mb-0">View all past employee absences and remote work days, filter records, or manage daily status logs.</p>
            <a href="{{ route('super-admin.attendance-holidays.absents.logs') }}" class="row-btn-outline-blue text-decoration-none d-inline-flex align-items-center" style="border-radius: 50px !important;">
                View Daily Logs
            </a>
        </div>
    </div>

    @push('modals')
    <!-- Break Settings Reconfirmation Modal -->
    <div class="modal fade" id="confirmBreakSettingsModal" tabindex="-1" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="background-color: #0c0c0c !important; border: 1px solid rgba(255, 255, 255, 0.08) !important; border-radius: 12px !important;">
                <div class="modal-body text-center p-4">
                    <div class="text-warning mb-3">
                        <i class="bi bi-stopwatch" style="font-size: 3rem; color: #f59e0b;"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-white">Save Break Settings?</h5>
                    <p class="text-white opacity-70 small mb-4">
                        Are you sure you want to update the Daily Break and Grace Period settings? This will immediately apply to real-time Slack break alerts and session balance calculations across the company.
                    </p>
                    
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 120px; border-radius: 50px !important;">Cancel</button>
                        <button type="button" class="row-btn text-white border-0" id="btnConfirmBreakSettingsSubmit" style="width: 140px; border-radius: 50px !important; background-color: #22c55e !important; font-weight: 500;">Yes, Save</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endpush

    @push('scripts')
    <script>
        $(function() {
            // Lock the left card's min-height to its initial height to prevent layout shift on tab changes
            const $leftCard = $('#markAbsentForm').closest('.spotify-card');
            const initialHeight = $leftCard.outerHeight();
            if (initialHeight > 0) {
                $leftCard.css('min-height', initialHeight + 'px');
            }

            let bulkMode = false;
            let selectedEmployees = []; // Array of objects: {id: X, name: Y, fullText: Z}

            // Sync selections to hidden inputs and dropdown checklist
            function syncSelection() {
                $('#hiddenInputsContainer').empty();
                $('.option-checkbox').prop('checked', false);

                if (selectedEmployees.length === 0) {
                    if (bulkMode) {
                        $('#employeeSearchInput').attr('placeholder', '-- Select Employees --');
                    } else {
                        $('#employeeSearchInput').attr('placeholder', '-- Select Employee --');
                    }
                    if (!$('#employeeSearchInput').is(':focus')) {
                        $('#employeeSearchInput').val('');
                    }
                    return;
                }

                selectedEmployees.forEach(function(emp) {
                    $(`#optCheck_${emp.id}`).prop('checked', true);
                    if (bulkMode) {
                        $('#hiddenInputsContainer').append(`<input type="hidden" name="user_ids[]" value="${emp.id}">`);
                    } else {
                        $('#hiddenInputsContainer').html(`<input type="hidden" name="user_id" value="${emp.id}">`);
                    }
                });

                if (!$('#employeeSearchInput').is(':focus')) {
                    updateInputText();
                }
            }

            function updateInputText() {
                if (selectedEmployees.length === 0) {
                    $('#employeeSearchInput').val('');
                    return;
                }

                if (!bulkMode) {
                    $('#employeeSearchInput').val(selectedEmployees[0].fullText);
                } else {
                    const count = selectedEmployees.length;
                    if (count === 1) {
                        $('#employeeSearchInput').val(selectedEmployees[0].name);
                    } else if (count === 2) {
                        $('#employeeSearchInput').val(selectedEmployees[0].name + ', ' + selectedEmployees[1].name);
                    } else {
                        $('#employeeSearchInput').val(count + ' employees selected');
                    }
                }
            }

            // Reset selection entirely
            function resetDropdown() {
                selectedEmployees = [];
                $('#selectAllEmployees').prop('checked', false);
                $('#employeeSearchInput').val('');
                $('#employeeOptionsList .employee-option-row').removeClass('d-none');
                
                if (bulkMode) {
                    $('#employeeSearchInput').attr('placeholder', '-- Select Employees --');
                    $('.option-checkbox').removeClass('d-none');
                    $('#selectAllContainer').removeClass('d-none');
                    $('#employeeSelectLabel').text('Select Employees');
                } else {
                    $('#employeeSearchInput').attr('placeholder', '-- Select Employee --');
                    $('.option-checkbox').addClass('d-none');
                    $('#selectAllContainer').addClass('d-none');
                    $('#employeeSelectLabel').text('Select Employee');
                }
                syncSelection();
            }

            // Toggle bulk marking
            $('#bulkMarkingToggle').on('change', function() {
                bulkMode = $(this).is(':checked');
                resetDropdown();
            });

            function tryMatchAndSelectAbsent(inputText) {
                if (!inputText) return false;
                const q = inputText.toLowerCase().trim();
                if (!q) return false;

                let matchedRow = null;
                $('#employeeOptionsList .employee-option-row').each(function() {
                    const name = ($(this).data('name') || '').toLowerCase().trim();
                    const label = $(this).find('.option-label-text').text().toLowerCase().trim();
                    if (name === q || label === q || name.startsWith(q) || label.includes(q)) {
                        matchedRow = $(this);
                        return false;
                    }
                });

                if (matchedRow && matchedRow.length) {
                    const id = matchedRow.data('id');
                    if (!bulkMode) {
                        selectedUserIds = [id];
                        $('.option-checkbox').prop('checked', false);
                        matchedRow.find('.option-checkbox').prop('checked', true);
                        syncSelection();
                        updateInputText();
                        return true;
                    }
                }
                return false;
            }

            // Focus on input opens dropdown programmatically and selects text for fast replacement
            $('#employeeSearchInput').on('focus click', function(e) {
                e.stopPropagation();
                if (!$('#employeeDropdownMenu').hasClass('show')) {
                    $('#employeeOptionsList .employee-option-row').removeClass('d-none');
                }
                this.select();
                
                const btn = document.getElementById('employeeSearchInput');
                const dropdown = bootstrap.Dropdown.getOrCreateInstance(btn);
                dropdown.show();
            });

            // Filter checkboxes in real-time
            $('#employeeSearchInput').on('input', function() {
                const query = $(this).val().toLowerCase().trim();
                $('#employeeOptionsList .employee-option-row').each(function() {
                    const name = $(this).data('name') || '';
                    const label = $(this).find('.option-label-text').text().toLowerCase();
                    if (name.includes(query) || label.includes(query)) {
                        $(this).removeClass('d-none');
                    } else {
                        $(this).addClass('d-none');
                    }
                });
            });

            $('#employeeSearchInput').on('change', function() {
                tryMatchAndSelectAbsent($(this).val());
            });

            // Close dropdown and restore selection when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.custom-employee-dropdown').length) {
                    const typed = $('#employeeSearchInput').val();
                    if (typed) {
                        tryMatchAndSelectAbsent(typed);
                    }
                    updateInputText();
                    const btn = document.getElementById('employeeSearchInput');
                    const dropdown = bootstrap.Dropdown.getInstance(btn);
                    if (dropdown) dropdown.hide();
                }
            });

            // Select All option click
            $(document).on('click', '#selectAllContainer', function(e) {
                if ($(e.target).is('#selectAllEmployees')) {
                    return;
                }
                const chk = $(this).find('#selectAllEmployees');
                chk.prop('checked', !chk.is(':checked')).trigger('change');
            });

            // Option Row Click Handler
            $(document).on('click', '.employee-option-row', function(e) {
                if ($(e.target).is('.option-checkbox')) {
                    return;
                }
                const chk = $(this).find('.option-checkbox');
                chk.prop('checked', !chk.is(':checked')).trigger('change');
            });

            // Option Checkbox Change Handler
            $(document).on('change', '.option-checkbox', function() {
                const id = $(this).val();
                const isChecked = $(this).is(':checked');
                const row = $(this).closest('.employee-option-row');
                const fullText = row.find('.option-label-text').text();
                const name = fullText.split(' (')[0];

                if (!bulkMode) {
                    if (isChecked) {
                        selectedEmployees = [{id: id, name: name, fullText: fullText}];
                        $('.option-checkbox').not($(this)).prop('checked', false);
                    } else {
                        selectedEmployees = [];
                    }
                    syncSelection();
                    
                    // Automatically close dropdown in single select mode
                    updateInputText();
                    const btn = document.getElementById('employeeSearchInput');
                    const dropdown = bootstrap.Dropdown.getInstance(btn);
                    if (dropdown) dropdown.hide();
                } else {
                    if (isChecked) {
                        if (!selectedEmployees.some(emp => emp.id == id)) {
                            selectedEmployees.push({id: id, name: name, fullText: fullText});
                        }
                    } else {
                        selectedEmployees = selectedEmployees.filter(emp => emp.id != id);
                    }
                    syncSelection();
                }
            });

            // Select All Employees Logic
            $(document).on('change', '#selectAllEmployees', function() {
                const isChecked = $(this).is(':checked');
                $('#employeeOptionsList .employee-option-row:visible').each(function() {
                    const chk = $(this).find('.option-checkbox');
                    chk.prop('checked', isChecked).trigger('change');
                });
            });

            // Tab Mode Switching
            let submitType = 'absent';

            $('#tabModeAbsent').on('click', function() {
                submitType = 'absent';

                $(this).addClass('fw-semibold').css({'background-color': '#3b82f6', 'color': '#ffffff'});
                $('#tabModeRemote').removeClass('fw-semibold').css({'background-color': 'transparent', 'color': '#ffffff'});
                $('#tabModeLeave').removeClass('fw-semibold').css({'background-color': 'transparent', 'color': '#ffffff'});

                // Show standard fields, hide leave fields
                $('#absentDateGroup').removeClass('d-none');
                $('#absentDateCol').removeClass('col-md-4').addClass('col-12');
                $('#remoteStartTimeCol').addClass('d-none');
                $('#remoteEndTimeCol').addClass('d-none');
                $('#notesInputGroup').removeClass('d-none');
                $('#notesInputGroup label').text('Notes / Reason (Optional)');
                $('#notesInputGroup input').attr('placeholder', 'e.g. Uninformed leave, sick day, etc.');
                $('#leaveModeFields').addClass('d-none');

                // Set date max and helper text
                const todayStr = "{{ date('Y-m-d') }}";
                $('#absentDate').attr('max', todayStr);
                $('#dateHelpText').text('Can only mark absent for today or past dates.');

                // Update action button
                $('#btnActionIcon').removeClass('bi-house-door bi-calendar-check').addClass('bi-person-x');
                $('#btnActionText').text('Mark Absent');
                $('#markAbsentBtn').css('background-color', '');
            });

            $('#tabModeRemote').on('click', function() {
                submitType = 'remote';

                $(this).addClass('fw-semibold').css({'background-color': '#f59e0b', 'color': '#000000'});
                $('#tabModeAbsent').removeClass('fw-semibold').css({'background-color': 'transparent', 'color': '#ffffff'});
                $('#tabModeLeave').removeClass('fw-semibold').css({'background-color': 'transparent', 'color': '#ffffff'});

                // Show standard fields with 3-column split, hide leave fields
                $('#absentDateGroup').removeClass('d-none');
                $('#absentDateCol').removeClass('col-12').addClass('col-md-4');
                $('#remoteStartTimeCol').removeClass('d-none');
                $('#remoteEndTimeCol').removeClass('d-none');
                $('#notesInputGroup').removeClass('d-none');
                $('#notesInputGroup label').text('Notes (Optional)');
                $('#notesInputGroup input').attr('placeholder', 'e.g. Work from home, client meeting, etc.');
                $('#leaveModeFields').addClass('d-none');

                // Remove date max constraint and update helper text
                $('#absentDate').removeAttr('max');
                $('#dateHelpText').text('Can mark remote work for today, past, or future dates. Leave time blank for Full Day.');

                // Update action button
                $('#btnActionIcon').removeClass('bi-person-x bi-calendar-check').addClass('bi-house-door');
                $('#btnActionText').text('Mark as Remote');
                $('#markAbsentBtn').css('background-color', '');
            });

            $('#tabModeLeave').on('click', function() {
                submitType = 'leave';

                $(this).addClass('fw-semibold').css({'background-color': '#22c55e', 'color': '#000000'});
                $('#tabModeAbsent').removeClass('fw-semibold').css({'background-color': 'transparent', 'color': '#ffffff'});
                $('#tabModeRemote').removeClass('fw-semibold').css({'background-color': 'transparent', 'color': '#ffffff'});

                // Hide standard date/notes, show leave fields
                $('#absentDateGroup').addClass('d-none');
                $('#notesInputGroup').addClass('d-none');
                $('#leaveModeFields').removeClass('d-none');

                // Update action button
                $('#btnActionIcon').removeClass('bi-person-x bi-house-door').addClass('bi-calendar-check');
                $('#btnActionText').text('Grant Leave');
                $('#markAbsentBtn').css('background-color', '');
            });

            // Reusable function to handle ajax submission of daily daily flags & leaves
            function submitAttendanceFlag(userIds, forceReject = false) {
                const btn = $('#markAbsentBtn');
                const originalText = btn.html();
                btn.prop('disabled', true);
                
                if (submitType === 'leave') {
                    btn.html('<span class="spinner-border spinner-border-sm me-2"></span>Granting...');
                } else if (submitType === 'remote') {
                    btn.html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...');
                } else {
                    btn.html('<span class="spinner-border spinner-border-sm me-2"></span>Marking...');
                }

                const csrfToken = '{{ csrf_token() }}';
                let successCount = 0;
                let successResponses = [];
                let failResponses = [];

                let actionUrl = '';
                if (submitType === 'leave') {
                    actionUrl = "{{ route('super-admin.attendance-holidays.leave.store') }}";
                } else if (submitType === 'remote') {
                    actionUrl = "{{ route('super-admin.attendance-holidays.remote.store') }}";
                } else {
                    actionUrl = "{{ route('super-admin.attendance-holidays.absents.store') }}";
                }

                const leaveTypeId = $('#leaveTypeSelect').val();
                const leaveStart  = $('#leaveStartDate').val();
                const leaveEnd    = $('#leaveEndDate').val();
                const leaveNotes  = $('#leaveNotes').val().trim();

                const dateVal       = $('#absentDate').val();
                const startTimeVal  = $('#remoteStartTime').val();
                const endTimeVal    = $('#remoteEndTime').val();
                const notesVal      = $('input[name="notes"]').val().trim();

                if (submitType === 'remote') {
                    if (startTimeVal && !endTimeVal) {
                        if (window.showToast) window.showToast('Please select an end time or leave both blank for full day.', 'danger');
                        btn.prop('disabled', false).html(originalText);
                        return;
                    }
                    if (!startTimeVal && endTimeVal) {
                        if (window.showToast) window.showToast('Please select a start time or leave both blank for full day.', 'danger');
                        btn.prop('disabled', false).html(originalText);
                        return;
                    }
                    if (startTimeVal && endTimeVal && startTimeVal >= endTimeVal) {
                        if (window.showToast) window.showToast('Start time must be earlier than end time.', 'danger');
                        btn.prop('disabled', false).html(originalText);
                        return;
                    }
                }

                const requests = userIds.map(uid => {
                    const payload = new FormData();
                    payload.append('_token', csrfToken);
                    payload.append('user_id', uid);
                    
                    if (forceReject) {
                        payload.append('force_reject_pending', '1');
                    }

                    if (submitType === 'leave') {
                        payload.append('leave_type_id', leaveTypeId);
                        payload.append('start_date', leaveStart);
                        payload.append('end_date', leaveEnd);
                        if (leaveNotes) payload.append('reason', leaveNotes);
                    } else if (submitType === 'remote') {
                        payload.append('date', dateVal);
                        if (startTimeVal) payload.append('start_time', startTimeVal);
                        if (endTimeVal) payload.append('end_time', endTimeVal);
                        if (notesVal) payload.append('notes', notesVal);
                    } else {
                        payload.append('date', dateVal);
                        if (notesVal) payload.append('notes', notesVal);
                    }

                    return fetch(actionUrl, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: payload,
                    }).then(async res => {
                        const data = await res.json();
                        const empRow = $('#employeeOptionsList .employee-option-row[data-id="' + uid + '"]');
                        const empName = empRow.length ? empRow.find('.option-label-text').text().split(' (')[0] : 'Employee';
                        if (data.success) {
                            successCount++;
                            successResponses.push(empName);
                        } else {
                            failResponses.push({
                                uid: uid,
                                name: empName,
                                message: data.message,
                                conflict_type: data.conflict_type,
                                logs_url: data.logs_url
                            });
                        }
                    }).catch(() => {
                        const empRow = $('#employeeOptionsList .employee-option-row[data-id="' + uid + '"]');
                        const empName = empRow.length ? empRow.find('.option-label-text').text().split(' (')[0] : 'Employee';
                        failResponses.push({
                            uid: uid,
                            name: empName,
                            message: 'An unexpected error occurred.',
                            conflict_type: 'unknown'
                        });
                    });
                });

                Promise.all(requests).then(() => {
                    btn.prop('disabled', false).html(originalText);

                    if (successCount > 0 && failResponses.length === 0) {
                        let successMsg = '';
                        if (submitType === 'leave') {
                            successMsg = successCount === 1 ? 'Leave granted successfully.' : `Leave granted for ${successCount} employee(s).`;
                        } else if (submitType === 'remote') {
                            successMsg = successCount === 1 ? 'Remote work marked successfully.' : `Remote work marked for ${successCount} employee(s).`;
                        } else {
                            successMsg = successCount === 1 ? 'Absent marked successfully.' : `Absent marked for ${successCount} employee(s).`;
                        }
                        if (window.showToast) window.showToast(successMsg, 'success');
                    }

                    if (failResponses.length > 0) {
                        // Title setup
                        let title = 'Cannot Mark Absent';
                        if (submitType === 'leave') title = 'Cannot Grant Leave';
                        if (submitType === 'remote') title = 'Cannot Mark Remote';
                        $('#absentConflictTitle').text(title);

                        // Icon container setup
                        const hasPendingWarningOnly = failResponses.every(r => r.conflict_type === 'has_pending_leave');
                        $('#conflictIconContainer').html('<i class="bi bi-exclamation-triangle-fill text-danger" id="conflictIcon" style="font-size: 3rem;"></i>');

                        // Message layout setup
                        let messageHtml = '';
                        
                        // Case A: Mixed Results (Some succeeded, some had conflicts)
                        if (successResponses.length > 0) {
                            $('#absentConflictTitle').text('Bulk Marking Summary');
                            $('#conflictIconContainer').html('<i class="bi bi-info-circle-fill text-warning" id="conflictIcon" style="font-size: 3rem;"></i>');
                            
                            messageHtml = `
                                <div style="text-align: left !important;">
                                    <div class="mb-3">
                                        <span class="text-success fw-bold d-block mb-1 small text-start"><i class="bi bi-check-circle-fill me-1"></i> Successfully Processed (${successResponses.length}):</span>
                                        <div class="conflict-scrollbar" style="background: rgba(255,255,255,0.02); padding: 8px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05); text-align: left !important;">
                                            <ul class="mb-0 ps-3 text-white-50 small">
                                                ${successResponses.map(name => `<li>${name}</li>`).join('')}
                                            </ul>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-danger fw-bold d-block mb-1 small text-start"><i class="bi bi-exclamation-triangle-fill me-1"></i> Conflicts / Issues (${failResponses.length}):</span>
                                        <div class="conflict-scrollbar" style="background: rgba(255,255,255,0.02); padding: 8px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05); text-align: left !important;">
                                            <ul class="mb-0 ps-3 text-white-50 small">
                                                ${failResponses.map(r => `<li><strong>${r.name}</strong>: ${r.message}</li>`).join('')}
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            `;
                            
                            // OK button reloads the page
                            $('#absentConflictActions').html('<button type="button" class="row-btn" id="btnReloadPage" style="width: 120px; border-radius: 50px !important;">OK</button>');
                            $('#btnReloadPage').off('click').on('click', function() {
                                location.reload();
                            });
                        } 
                        // Case B: Pure Failure (No successes)
                        else {
                            if (failResponses.length === 1) {
                                messageHtml = '<p class="mb-0 text-center">' + failResponses[0].message + '</p>';
                            } else {
                                messageHtml = `
                                    <div style="text-align: left !important;">
                                        <span class="text-danger fw-bold d-block mb-1 small text-start"><i class="bi bi-exclamation-triangle-fill me-1"></i> Conflicts / Issues (${failResponses.length}):</span>
                                        <div class="conflict-scrollbar" style="background: rgba(255,255,255,0.02); padding: 8px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05); text-align: left !important;">
                                            <ul class="mb-0 ps-3 text-white-50 small">
                                                ${failResponses.map(r => `<li><strong>${r.name}</strong>: ${r.message}</li>`).join('')}
                                            </ul>
                                        </div>
                                    </div>
                                `;
                            }

                            // Actions setup: If logs_url is present, keep only View Record option
                            let actionsHtml = '';
                            if (failResponses.length === 1 && failResponses[0].logs_url) {
                                actionsHtml = `
                                    <a href="${failResponses[0].logs_url}" target="_blank" class="row-btn text-decoration-none text-center d-flex align-items-center justify-content-center text-white" style="background-color: #f59e0b; width: 140px; border-radius: 50px !important; border: 0;">View Record</a>
                                `;
                            } else {
                                actionsHtml = '<button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 120px; border-radius: 50px !important;">OK</button>';
                            }
                            $('#absentConflictActions').html(actionsHtml);
                        }

                        $('#absentConflictMessage').html(messageHtml);
                        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('absentConflictModal'));
                        modal.show();
                    }

                    if (successCount > 0 && failResponses.length === 0) {
                        setTimeout(() => location.reload(), 1200);
                    }
                });
            }

            $('#markAbsentForm').on('submit', function(e) {
                e.preventDefault();

                // Leave mode: validate leave-specific fields
                if (submitType === 'leave') {
                    const leaveTypeId = $('#leaveTypeSelect').val();
                    const leaveStart  = $('#leaveStartDate').val();
                    const leaveEnd    = $('#leaveEndDate').val();

                    if (!leaveTypeId) {
                        if (window.showToast) window.showToast('Please select a leave type.', 'danger');
                        else alert('Please select a leave type.');
                        return;
                    }
                    if (!leaveStart || !leaveEnd) {
                        if (window.showToast) window.showToast('Please select both start and end dates.', 'danger');
                        else alert('Please select both start and end dates.');
                        return;
                    }
                }

                // Collect user IDs from both single mode (user_id) and bulk mode (user_ids[])
                let userIds = [];
                const singleId = $('input[name="user_id"]').val();
                if (singleId) {
                    userIds = [singleId];
                } else {
                    $('input[name="user_ids[]"]').each(function() {
                        if ($(this).val()) userIds.push($(this).val());
                    });
                }

                if (userIds.length === 0) {
                    if (window.showToast) window.showToast('Please select at least one employee.', 'danger');
                    else alert('Please select at least one employee.');
                    return;
                }

                submitAttendanceFlag(userIds, false);
            });


            // Break Settings Form logic with modal
            $('#btnSaveBreakSettings').on('click', function(e) {
                e.preventDefault();
                const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmBreakSettingsModal'));
                modal.show();
            });

            // Modal click handler to submit break settings form
            $('#btnConfirmBreakSettingsSubmit').on('click', function() {
                $('#breakSettingsForm').submit();
            });
        });
    </script>

    {{-- Absent Conflict Modal --}}
    <div class="modal fade" id="absentConflictModal" tabindex="-1" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="background-color: #0c0c0c !important; border: 1px solid rgba(255, 255, 255, 0.08) !important; border-radius: 12px !important;">
                <div class="modal-body text-center p-4">
                    <div class="mb-3" id="conflictIconContainer">
                        <i class="bi bi-exclamation-triangle-fill text-danger" id="conflictIcon" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-white" id="absentConflictTitle">Cannot Mark Absent</h5>
                    <div class="text-white opacity-70 small mb-4" id="absentConflictMessage" style="max-height: 250px; overflow-y: auto; text-align: center; padding: 0 10px;"></div>
                    
                    <div class="d-flex justify-content-center gap-2" id="absentConflictActions">
                        <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 120px; border-radius: 50px !important;">OK</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endpush
</x-app-layout>
