<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <span>Employee Track Record</span>
            <!-- Download Excel Button -->
            <div id="export-btn-wrapper" class="d-none">
                <button type="button" class="row-btn-outline-blue" id="export-btn">
                    <i class="bi bi-file-earmark-excel me-1"></i>Download Excel
                </button>
            </div>
        </div>
    </x-slot>

    <style>
        .profile-field-row {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
            padding-top: 10px;
            padding-bottom: 10px;
        }
        .profile-field-row:last-child {
            border-bottom: none !important;
        }
        #dynamic-thead th {
            position: sticky !important;
            top: 0 !important;
            z-index: 10 !important;
            background-color: #0a0a0a !important;
        }

        /* --- Light spreadsheet theme for results card --- */
        .table-light-theme {
            background-color: #f1f5f9 !important; /* Softer light gray background (less bright/glaring) */
            border-color: #cbd5e1 !important;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05) !important;
        }
        .table-light-theme .card-header-area {
            background-color: #e2e8f0 !important; /* Soft slate gray for card header */
            border-bottom: 1px solid #cbd5e1 !important;
        }
        .table-light-theme #report-title {
            color: #0f172a !important; /* Dark slate text */
        }
        .table-light-theme #table-theme-toggle {
            color: #0f172a !important;
        }
        .table-light-theme .grid-separator {
            border-bottom-color: #cbd5e1 !important;
        }
        .table-light-theme #dynamic-table {
            background-color: #f8fafc !important; /* Soft off-white table background */
            border: 1px solid #cbd5e1 !important;
        }
        .table-light-theme #dynamic-thead th {
            background-color: #cbd5e1 !important; /* Solid gray header column background */
            color: #0f172a !important; /* High contrast dark text */
            border: 1px solid #94a3b8 !important;
            font-weight: 700 !important;
        }
        .table-light-theme #dynamic-tbody td {
            background-color: #f8fafc !important; /* Soft off-white cell background */
            color: #0f172a !important; /* High-contrast dark text */
            border: 1px solid #cbd5e1 !important;
        }
        /* Style alternate rows for Excel feel */
        .table-light-theme #dynamic-tbody tr:nth-child(even) td {
            background-color: #f1f5f9 !important; /* Alternating row soft gray */
        }
        /* Weekend rows */
        .table-light-theme #dynamic-tbody tr[style*="rgba(230, 47, 47"] td,
        .table-light-theme #dynamic-tbody tr[style*="rgba(230, 47, 47"] {
            background-color: #fee2e2 !important; /* Soft rose red background */
            color: #7f1d1d !important; /* Darker red-900 for high-contrast weekend text */
            font-weight: 600 !important;
            border-color: #fca5a5 !important;
        }
        .table-light-theme #dynamic-tbody tr[style*="rgba(230, 47, 47"] td:nth-child(2) {
            color: #991b1b !important; /* Richer red-800 day name */
            font-weight: 700 !important;
        }
        /* Style text/mute states in light mode */
        .table-light-theme .text-white {
            color: #0f172a !important;
        }
        .table-light-theme .text-muted {
            color: #334155 !important; /* Slate-700 for high readability */
        }
        .table-light-theme .opacity-25 {
            opacity: 1 !important; /* Ensure dashes (-) are fully opaque and visible */
            color: #475569 !important; /* Slate-600 */
        }
        .table-light-theme .opacity-50 {
            opacity: 0.95 !important;
        }
        .table-light-theme .opacity-75 {
            opacity: 0.98 !important;
        }
        /* Dynamic table classes with high specificity (Dark mode defaults) */
        #dynamic-tbody td.text-weekend-day {
            color: #ef4444 !important;
            font-weight: 600 !important;
        }
        #dynamic-tbody td.text-normal-day {
            color: rgba(255, 255, 255, 0.75) !important;
        }
        #dynamic-tbody td.text-total-positive {
            color: #10b981 !important;
            font-weight: 600 !important;
        }
        #dynamic-tbody td.text-total-zero {
            color: rgba(255, 255, 255, 0.4) !important;
        }
        .badge-leaves {
            background: rgba(59, 130, 246, 0.12) !important;
            color: #3b82f6 !important;
            border: 1px solid rgba(59, 130, 246, 0.25) !important;
        }
        .badge-absent {
            background: rgba(239, 68, 68, 0.12) !important;
            color: #ef4444 !important;
            border: 1px solid rgba(239, 68, 68, 0.25) !important;
        }
        .badge-holiday {
            background: rgba(245, 158, 11, 0.12) !important;
            color: #f59e0b !important;
            border: 1px solid rgba(245, 158, 11, 0.25) !important;
        }
        .badge-working-hrs, .badge-expected {
            background: rgba(255, 255, 255, 0.05) !important;
            color: #e5e5e5 !important;
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
        }
        .badge-given {
            background: rgba(16, 185, 129, 0.12) !important;
            color: #10b981 !important;
            border: 1px solid rgba(16, 185, 129, 0.25) !important;
        }
        .badge-diff-positive {
            background: rgba(16, 185, 129, 0.12) !important;
            color: #10b981 !important;
            border: 1px solid rgba(16, 185, 129, 0.25) !important;
        }
        .badge-diff-negative {
            background: rgba(239, 68, 68, 0.12) !important;
            color: #ef4444 !important;
            border: 1px solid rgba(239, 68, 68, 0.25) !important;
        }
        .badge-percentage {
            background: #ffffffee !important;
            color: #000000 !important;
            border: transparent !important;
        }
        .badge-session-incomplete {
            background: rgba(239, 68, 68, 0.12) !important;
            color: #ef4444 !important;
            border: 1px solid rgba(239, 68, 68, 0.25) !important;
        }
        .badge-session-active {
            background: rgba(59, 130, 246, 0.12) !important;
            color: #3b82f6 !important;
            border: 1px solid rgba(59, 130, 246, 0.25) !important;
        }

        /* Light mode overrides for dynamic table classes with high specificity */
        .table-light-theme #dynamic-tbody td.text-weekend-day {
            color: #7f1d1d !important; /* Deep red-900 */
            font-weight: 700 !important;
        }
        .table-light-theme #dynamic-tbody td.text-normal-day {
            color: #0f172a !important; /* Dark slate */
        }
        .table-light-theme #dynamic-tbody td.text-total-positive {
            color: #166534 !important; /* Rich green-800 */
            font-weight: 700 !important;
        }
        .table-light-theme #dynamic-tbody td.text-total-zero {
            color: #94a3b8 !important; /* Soft light grey (Slate-400) */
            font-weight: 500 !important;
        }
        .table-light-theme .badge-leaves {
            background-color: #eff6ff !important;
            color: #1e40af !important;
            border: 1px solid #93c5fd !important;
            font-weight: 600 !important;
        }
        .table-light-theme .badge-absent {
            background-color: #fef2f2 !important;
            color: #991b1b !important;
            border: 1px solid #fca5a5 !important;
            font-weight: 600 !important;
        }
        .table-light-theme .badge-holiday {
            background-color: #fffbeb !important;
            color: #92400e !important;
            border: 1px solid #fcd34d !important;
            font-weight: 600 !important;
        }
        .table-light-theme .badge-working-hrs,
        .table-light-theme .badge-expected {
            background-color: #f1f5f9 !important;
            color: #1e293b !important;
            border: 1px solid #94a3b8 !important;
            font-weight: 600 !important;
        }
        .table-light-theme .badge-given,
        .table-light-theme .badge-diff-positive {
            background-color: #f0fdf4 !important;
            color: #166534 !important;
            border: 1px solid #86efac !important;
            font-weight: 600 !important;
        }
        .table-light-theme .badge-diff-negative {
            background-color: #fef2f2 !important;
            color: #991b1b !important;
            border: 1px solid #fca5a5 !important;
            font-weight: 600 !important;
        }
        .table-light-theme .badge-percentage {
            background-color: #334155 !important; /* Dark gray background */
            color: #ffffff !important; /* Clear white text */
            border: none !important;
            font-weight: 600 !important;
        }
        .table-light-theme .badge-session-incomplete {
            background-color: #fef2f2 !important;
            color: #991b1b !important;
            border: 1px solid #fca5a5 !important;
            font-weight: 600 !important;
        }
        .table-light-theme .badge-session-active {
            background-color: #eff6ff !important;
            color: #1e40af !important;
            border: 1px solid #93c5fd !important;
            font-weight: 600 !important;
        }
        .table-light-theme #report-user-badge {
            background-color: #16a34a !important; /* Premium bright green badge */
            color: #ffffff !important; /* High contrast white text */
            font-weight: 700 !important;
            border: 1px solid #15803d !important;
        }

        /* --- Pill Theme Toggle Switch --- */
        .theme-switch-wrapper {
            display: inline-flex;
            align-items: center;
        }
        .theme-switch-pill {
            position: relative;
            width: 52px;
            height: 28px;
            background-color: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 50px;
            cursor: pointer;
            padding: 3px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
        }
        .theme-switch-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            font-size: 0.82rem;
            z-index: 1;
            pointer-events: none;
            transition: all 0.3s ease;
        }
        .theme-switch-pill .sun-icon {
            color: #f59e0b; /* Golden sun color */
            margin-left: 2px;
        }
        .theme-switch-pill .moon-icon {
            color: #9ca3af; /* Silver moon color */
            margin-right: 2px;
        }
        .theme-switch-handle {
            position: absolute;
            left: 3px;
            width: 20px;
            height: 20px;
            background-color: #ffffffc9;
            border-radius: 50%; /* Perfect circle */
            transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
            z-index: 2;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        /* Interactive Switch States when Light Mode is active */
        .table-light-theme #table-theme-toggle {
            background-color: #cbd5e1 !important;
            border-color: #cbd5e1 !important;
        }
        .table-light-theme #table-theme-toggle .theme-switch-handle {
            left: 27px; /* Slide to right */
            background-color: #ffffff !important; /* Keep circle clean white (low contrast) */
            box-shadow: 0 1px 3px rgba(0,0,0,0.15);
        }
        .table-light-theme #table-theme-toggle .sun-icon {
            color: #d97706 !important; /* Beautiful gold sun color */
        }
        .table-light-theme #table-theme-toggle .moon-icon {
            color: #64748b !important; /* Muted moon icon */
        }
    </style>

    <!-- Filters Panel Card -->
    <div class="spotify-card p-4 mb-4 mt-3">
        <div class="row g-3">
            <!-- Employee Dropdown -->
            <div class="col-md-3">
                <label class="form-label text-white opacity-75 small fw-semibold">Employee</label>
                <select class="form-select form-select-sm text-white" id="employee-select" style="height: 32px !important; border-radius: 50px !important; padding: 0 1rem !important; font-size: 0.85rem !important;">
                    <option value="">-- Select Employee --</option>
                    <option value="all">All Employees</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
            </div>

            <!-- Date Period -->
            <div class="col-md-3">
                <label class="form-label text-white opacity-75 small fw-semibold">Period</label>
                <select class="form-select form-select-sm text-white" id="period-select" style="height: 32px !important; border-radius: 50px !important; padding: 0 1rem !important; font-size: 0.85rem !important;">
                    <option value="today">Today</option>
                    <option value="month" selected>Current Month</option>
                </select>
            </div>

            <!-- Custom Month Select -->
            <div class="col-md-2" id="custom-month-wrapper">
                <label class="form-label text-white opacity-75 small fw-semibold">Month</label>
                <select class="form-select form-select-sm text-white" id="custom-month-select" style="height: 32px !important; border-radius: 50px !important; padding: 0 1rem !important; font-size: 0.85rem !important;">
                    <option value="1">January</option>
                    <option value="2">February</option>
                    <option value="3">March</option>
                    <option value="4">April</option>
                    <option value="5">May</option>
                    <option value="6">June</option>
                    <option value="7">July</option>
                    <option value="8">August</option>
                    <option value="9">September</option>
                    <option value="10">October</option>
                    <option value="11">November</option>
                    <option value="12">December</option>
                </select>
            </div>

            <!-- Custom Year Select -->
            <div class="col-md-2" id="custom-year-wrapper">
                <label class="form-label text-white opacity-75 small fw-semibold">Year</label>
                <select class="form-select form-select-sm text-white" id="custom-year-select" style="height: 32px !important; border-radius: 50px !important; padding: 0 1rem !important; font-size: 0.85rem !important;">
                    <!-- Will be populated dynamically -->
                </select>
            </div>

            <!-- Clear Filter Button -->
            <div class="col-md-2 d-flex align-items-end">
                <a href="{{ route('super-admin.session-management.track-record') }}" class="btn btn-outline-secondary w-100 text-decoration-none d-flex align-items-center justify-content-center gap-1.5" style="height: 32px !important; border-radius: 50px !important; padding: 0 !important; font-size: 0.85rem !important;">
                    <i class="bi bi-x-circle me-1"></i> Clear Filters
                </a>
            </div>
        </div>
    </div>

    <!-- Initial Placeholder State Card -->
    <div id="placeholder-card" class="spotify-card p-5 text-center mb-4">
        <div class="py-5">
            <div class="mb-4 text-muted">
                <i class="bi bi-file-earmark-bar-graph" style="font-size: 4rem;"></i>
            </div>
            <h5 class="fw-bold text-white mb-2">Track Record Generation</h5>
            <p class="text-white opacity-50 mx-auto" style="max-width: 500px;">
                Select an employee above to dynamically generate their chronological check-in sessions and calculated total working hours.
            </p>
        </div>
    </div>

    <!-- Main Results Table Card (Hidden initially) -->
    <div id="results-card" class="spotify-card mb-4 d-none">
        <div class="card-header-area px-4 pt-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="text-uppercase text-white fw-semibold small mb-0 opacity-75" style="letter-spacing: 0.05em;" id="report-title">Employee Timesheet Summary</h6>
                <div class="d-flex align-items-center gap-3">
                    <!-- Theme Toggle Switch -->
                    <div class="theme-switch-wrapper">
                        <div class="theme-switch-pill" id="table-theme-toggle" data-custom-tooltip="Light Mode" data-tooltip-placement="top">
                            <span class="theme-switch-icon sun-icon"><i class="bi bi-sun"></i></span>
                            <span class="theme-switch-icon moon-icon"><i class="bi bi-moon"></i></span>
                            <div class="theme-switch-handle"></div>
                        </div>
                    </div>
                    <span id="report-user-badge" class="row-badge-green">--</span>
                </div>
            </div>
            <div class="grid-separator"></div>
        </div>
        
        <div class="table-responsive" style="max-height: 70vh; overflow-y: auto; overflow-x: auto;">
            <table class="table table-hover mb-0 align-middle" id="dynamic-table" style="table-layout: fixed;">
                <colgroup id="dynamic-colgroup">
                    <!-- Column widths generated dynamically -->
                </colgroup>
                <thead id="dynamic-thead">
                    <!-- Table header columns generated dynamically -->
                </thead>
                <tbody id="dynamic-tbody">
                    <!-- Report rows injected dynamically -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Summary Dashboard Card (Hidden initially) -->
    <div id="summary-dashboard-card" class="spotify-card p-4 mb-4 d-none">
        <div class="row mx-0">
            <!-- Left Side: Work Summary -->
            <div class="col-md-6 border-end border-secondary border-opacity-25 ps-0 pe-md-4">
                <div class="d-flex align-items-center mb-2">
                    <div class="icon-circle me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; border-radius: 50%; background-color: rgba(59, 130, 246, 0.1);">
                        <i class="bi bi-clock-history text-primary"></i>
                    </div>
                    <h6 class="text-uppercase text-white fw-bold mb-0 opacity-75 small" style="letter-spacing: 0.05em;">Work Summary</h6>
                </div>
                <hr class="border-secondary my-2">
                
                <div>
                    <div class="d-flex justify-content-between align-items-center profile-field-row">
                        <span class="text-white opacity-50 small">Expected Hours</span>
                        <span id="sum-expected-time" class="text-white fw-semibold small text-end">00:00:00</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center profile-field-row">
                        <span class="text-white opacity-50 small">Logged Hours</span>
                        <span id="sum-time-given" class="text-white fw-semibold small text-end">00:00:00</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center profile-field-row" id="sum-diff-box">
                        <span class="text-white opacity-50 small">Time Difference</span>
                        <span id="sum-difference" class="fw-semibold small text-end">00:00:00</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center profile-field-row">
                        <span class="text-white opacity-50 small">Leaves Taken</span>
                        <span id="sum-leaves" class="text-white fw-semibold small text-end">0</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center profile-field-row">
                        <span class="text-white opacity-50 small">Total Absents</span>
                        <span id="sum-absents" class="text-white fw-semibold small text-end">0</span>
                    </div>
                </div>
            </div>

            <!-- Right Side: Financial Estimates -->
            <div class="col-md-6 ps-md-4 pe-0">
                <div class="d-flex align-items-center mb-2">
                    <div class="icon-circle me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; border-radius: 50%; background-color: rgba(16, 185, 129, 0.1);">
                        <i class="bi bi-wallet2 text-success"></i>
                    </div>
                    <h6 class="text-uppercase text-white fw-bold mb-0 opacity-75 small" style="letter-spacing: 0.05em;">Financial Estimation</h6>
                </div>
                <hr class="border-secondary my-2">
                
                <div>
                    <div class="d-flex justify-content-between align-items-center profile-field-row">
                        <span class="text-white opacity-50 small">Expected Salary</span>
                        <span class="text-white fw-semibold small text-end">Rs. <span id="sum-actual-salary">0</span></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center profile-field-row">
                        <span class="text-white opacity-50 small">Total Deductions</span>
                        <span class="text-danger fw-semibold small text-end">Rs. <span id="sum-deductions">0</span></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center profile-field-row">
                        <span class="text-white opacity-50 small">Take-Away Salary</span>
                        <span class="text-success fw-bold small text-end" style="font-size: 1.05rem;">Rs. <span id="sum-take-away">0</span></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Comments Section -->
        <div class="border-top border-secondary border-opacity-25 mt-4 pt-3">
            <label for="summary-comment" class="form-label text-white opacity-75 small fw-semibold">
                <i class="bi bi-chat-left-text me-1 text-primary"></i> Comments (will be included in Excel export)
            </label>
            <textarea id="summary-comment" class="form-control" rows="2" placeholder="Enter comments here..." style="background-color: rgba(255, 255, 255, 0.02) !important; border: 1px solid rgba(255, 255, 255, 0.12); color: #ffffff; border-radius: 6px; font-size: 0.9rem;"></textarea>
        </div>
    </div>

    @push('scripts')
        <script>
            $(function() {
                // Make the main header h1 full-width to allow the button to stick to the right
                $('main h1').addClass('w-100');

                function showError(message) {
                    if (window.showToast) {
                        window.showToast(message, 'danger');
                    } else {
                        alert(message);
                    }
                }

                function showInfo() {
                    if (window.showToast) {
                        window.showToast('PDF generation feature will be implemented in a future update.', 'danger');
                    } else {
                        alert('PDF generation feature will be implemented in a future update.');
                    }
                }

                // Populate custom year select from current year down to 2010
                const currentYear = new Date().getFullYear();
                const yearSelect = $('#custom-year-select');
                for (let y = currentYear; y >= 2010; y--) {
                    yearSelect.append(new Option(y, y));
                }

                // Set initial default selections for month/year select
                const currentMonth = new Date().getMonth() + 1; // 1-12
                $('#custom-month-select').val(currentMonth);
                $('#custom-year-select').val(currentYear);

                // Enable/disable month/year selectors based on period
                $('#period-select').on('change', function() {
                    const val = $(this).val();
                    if (val === 'today') {
                        $('#custom-month-select, #custom-year-select').prop('disabled', true);
                    } else {
                        $('#custom-month-select, #custom-year-select').prop('disabled', false);
                    }
                }).trigger('change');

                // Lock period select to 'today' and disable it if 'all' employees is selected
                $('#employee-select').on('change', function() {
                    const userId = $(this).val();
                    if (userId === 'all') {
                        $('#period-select').val('today').trigger('change').prop('disabled', true);
                    } else {
                        $('#period-select').prop('disabled', false).trigger('change');
                    }
                });

                // Auto-fetch function when filters are updated
                function fetchReport() {
                    const userId = $('#employee-select').val();
                    const period = $('#period-select').val();

                    // Clear comment textarea on every fresh load
                    $('#summary-comment').val('');

                    if (!userId) {
                        // Keep view in initial state if no user selected
                        $('#placeholder-card').removeClass('d-none');
                        $('#results-card').addClass('d-none');
                        $('#summary-dashboard-card').addClass('d-none');
                        return;
                    }

                    let startDate = '';
                    let endDate = '';
                    if (period === 'month') {
                        const year = $('#custom-year-select').val();
                        const month = $('#custom-month-select').val().padStart(2, '0');
                        startDate = `${year}-${month}-01`;
                        const lastDay = new Date(year, parseInt(month), 0).getDate();
                        endDate = `${year}-${month}-${lastDay}`;
                    }

                    $.ajax({
                        url: "{{ route('super-admin.session-management.track-record.report') }}",
                        method: "GET",
                        data: {
                            user_id: userId,
                            period: period === 'month' ? 'custom' : period,
                            start_date: startDate,
                            end_date: endDate
                        },
                        success: function(response) {
                            // Swap View states
                            $('#placeholder-card').addClass('d-none');
                            $('#results-card').removeClass('d-none');

                            if (response.summary && response.summary.show_summary) {
                                $('#summary-dashboard-card').removeClass('d-none');
                            } else {
                                $('#summary-dashboard-card').addClass('d-none');
                            }

                            // Set metadata badges
                            const employeeName = $('#employee-select option:selected').text().split(' (')[0];
                            $('#report-user-badge').text(employeeName);

                            renderTable(response);
                        },
                        error: function(err) {
                            let errorMsg = 'An error occurred while generating the track record.';
                            if (err.responseJSON && err.responseJSON.message) {
                                errorMsg = err.responseJSON.message;
                            }
                            showError(errorMsg);
                        }
                    });
                }

                function updateExportButtonVisibility() {
                    const userId = $('#employee-select').val();
                    const period = $('#period-select').val();

                    let showExport = false;
                    if (userId && userId !== 'all') {
                        if (period === 'month') {
                            showExport = true;
                        }
                    }

                    if (showExport) {
                        $('#export-btn-wrapper').removeClass('d-none');
                    } else {
                        $('#export-btn-wrapper').addClass('d-none');
                    }
                }

                // Listen to filter updates
                $('#employee-select, #period-select, #custom-month-select, #custom-year-select').on('change', function() {
                    updateExportButtonVisibility();
                    fetchReport();
                });

                // Toggle spreadsheet style (light/dark)
                $('#table-theme-toggle').on('click', function() {
                    const resultsCard = $('#results-card');
                    const toggle = $(this);
                    
                    // Hide current tooltip before updating text to prevent ghost tooltips
                    if (typeof hideTooltip === 'function') {
                        hideTooltip();
                    } else if (window.customTooltip) {
                        window.customTooltip.removeClass('show arrow-left arrow-bottom');
                    }
                    
                    if (resultsCard.hasClass('table-light-theme')) {
                        resultsCard.removeClass('table-light-theme');
                        toggle.attr('data-custom-tooltip', 'Light Mode');
                    } else {
                        resultsCard.addClass('table-light-theme');
                        toggle.attr('data-custom-tooltip', 'Dark Mode');
                    }
                    
                    // Re-trigger show if cursor is still hovering
                    toggle.trigger('mouseenter');
                });

                // Excel Button Action
                $('#export-btn').on('click', function() {
                    const userId = $('#employee-select').val();
                    const period = $('#period-select').val();

                    if (!userId || userId === 'all') {
                        showError('Please select a specific employee first before exporting.');
                        return;
                    }

                    if (period === 'today') {
                        showError('Export is only available for full calendar months.');
                        return;
                    }

                    let startDate = '';
                    let endDate = '';
                    if (period === 'month') {
                        const year = $('#custom-year-select').val();
                        const month = $('#custom-month-select').val().padStart(2, '0');
                        startDate = `${year}-${month}-01`;
                        const lastDay = new Date(year, parseInt(month), 0).getDate();
                        endDate = `${year}-${month}-${lastDay}`;
                    }

                    // Build download URL and redirect
                    const url = new URL("{{ route('super-admin.session-management.track-record.export') }}", window.location.origin);
                    url.searchParams.append('user_id', userId);
                    url.searchParams.append('period', 'custom');
                    if (startDate) url.searchParams.append('start_date', startDate);
                    if (endDate) url.searchParams.append('end_date', endDate);
                    
                    const comment = $('#summary-comment').val();
                    if (comment) {
                        url.searchParams.append('comment', comment);
                    }

                    window.location.href = url.toString();
                });

                // Initial run
                updateExportButtonVisibility();

                // Render Table dynamically matching Excel template layout
                function renderTable(data) {
                    const records = data.records;
                    const maxSessions = data.max_sessions;
                    const summary = data.summary;

                    const thead = $('#dynamic-thead');
                    const tbody = $('#dynamic-tbody');

                    thead.empty();
                    tbody.empty();

                    // Update summary card values
                    if (summary) {
                        $('#sum-expected-time').text(summary.total_expected_formatted);
                        $('#sum-time-given').text(summary.total_session_formatted);
                        $('#sum-difference').text(summary.total_difference_formatted);
                        $('#sum-leaves').text(summary.total_leaves);
                        $('#sum-absents').text(summary.total_absents);
                        $('#sum-actual-salary').text(summary.expected_salary);
                        $('#sum-deductions').text(summary.deductions);
                        $('#sum-take-away').text(summary.take_away);

                        // Color diff value
                        const diffSec = summary.total_difference_seconds;
                        const diffVal = $('#sum-difference');
                        
                        if (diffSec >= 0) {
                            diffVal.removeClass('text-danger').addClass('text-success');
                        } else {
                            diffVal.removeClass('text-success').addClass('text-danger');
                        }
                    }

                    // 1. Build Headers with fixed widths to support overflow scrolling on 3+ sessions
                    // 1. Build Colgroup and Table Width
                    const period = $('#period-select').val();
                    const employeeId = $('#employee-select').val();
                    const isToday = (period === 'today');
                    const isAll = (employeeId === 'all');

                    const displaySessions = Math.max(2, maxSessions);
                    
                    let tableWidth = '100%';
                    let tableMinWidth = 1000;
                    if (displaySessions > 2) {
                        tableWidth = `calc(100% + ${(displaySessions - 2) * 230}px)`;
                        tableMinWidth += (displaySessions - 2) * 230;
                    }
                    $('#dynamic-table').css('width', tableWidth);
                    $('#dynamic-table').css('min-width', tableMinWidth + 'px');

                    let colgroupHtml = '';
                    if (isToday) {
                        colgroupHtml = `
                            <col style="width: 10%;">
                            <col style="width: 8%;">
                            <col style="width: 8%;">
                            <col style="width: 12%;">
                            <col style="width: 15.5%;">
                            <col style="width: 15.5%;">
                            <col style="width: 15.5%;">
                            <col style="width: 15.5%;">
                        `;
                        for (let i = 3; i <= displaySessions; i++) {
                            colgroupHtml += `
                                <col style="width: 115px;">
                                <col style="width: 115px;">
                            `;
                        }
                    } else {
                        colgroupHtml = `
                            <col style="width: 10%;">
                            <col style="width: 8%;">
                            <col style="width: 12%;">
                            <col style="width: 10%;">
                            <col style="width: 8%;">
                            <col style="width: 12%;">
                            <col style="width: 10%;">
                            <col style="width: 10%;">
                            <col style="width: 10%;">
                            <col style="width: 10%;">
                        `;
                        for (let i = 3; i <= displaySessions; i++) {
                            colgroupHtml += `
                                <col style="width: 115px;">
                                <col style="width: 115px;">
                            `;
                        }
                    }
                    $('#dynamic-colgroup').html(colgroupHtml);

                    let headerHtml = '<tr>';
                    if (isAll) {
                        headerHtml += '<th class="ps-4 text-start" style="vertical-align: middle;">Employee</th>';
                    } else {
                        headerHtml += '<th class="ps-4 text-start" style="vertical-align: middle;">Date</th>';
                    }
                    headerHtml += '<th class="text-center" style="vertical-align: middle;">Day</th>';
                    
                    if (!isToday) {
                        headerHtml += '<th colspan="2" class="text-center" style="vertical-align: middle;">Weekly Summary</th>';
                    }
                    
                    headerHtml += '<th class="text-center" style="vertical-align: middle;">%</th>';
                    headerHtml += '<th class="text-center" style="vertical-align: middle;">Total Session Time</th>';
                    
                    for (let i = 1; i <= displaySessions; i++) {
                        headerHtml += `<th class="text-center" style="vertical-align: middle;">Sign In ${i}</th>`;
                        headerHtml += `<th class="text-center" style="vertical-align: middle;">Sign Out ${i}</th>`;
                    }
                    headerHtml += '</tr>';
                    thead.html(headerHtml);

                    // 2. Build Rows
                    if (records.length === 0) {
                        let emptyCols = (isToday ? 4 : 6) + (displaySessions * 2);
                        tbody.html(`
                            <tr>
                                <td colspan="${emptyCols}" class="text-center text-white opacity-50 py-5">
                                    <div class="mb-2"><i class="bi bi-calendar-x fs-3"></i></div>
                                    No records found for the selected date range.
                                </td>
                            </tr>
                        `);
                        return;
                    }

                    let bodyHtml = '';
                    records.forEach(function(rec) {
                        let rowStyle = '';
                        let rowClass = '';
                        if (rec.is_weekend) {
                            rowStyle = 'style="background-color: rgba(230, 47, 47, 0.13) !important;"';
                        }
                        bodyHtml += `<tr ${rowStyle}>`;
                        
                        // Date/Employee name column
                        if (isAll) {
                            bodyHtml += `<td class="ps-4 text-start fw-semibold text-white">${rec.employee_name || '-'}</td>`;
                        } else {
                            if (rec.is_filler) {
                                bodyHtml += `<td class="ps-4 text-start text-white font-monospace opacity-25">-</td>`;
                            } else {
                                bodyHtml += `<td class="ps-4 text-start fw-semibold text-white font-monospace">${rec.date || '-'}</td>`;
                            }
                        }
                        
                        // Day column
                        let dayClass = rec.is_weekend ? 'text-weekend-day' : 'text-normal-day';
                        if (rec.is_filler) {
                            dayClass += ' opacity-35 fst-italic';
                        }
                        bodyHtml += `<td class="text-center ${dayClass}">${rec.day_name}</td>`;
                        
                        // Weekly Summary Column (colspan="2")
                        if (!isToday) {
                            let wsHtml = '';
                            if (rec.weekly_summary_label) {
                                if (rec.day_of_week === 1) {
                                    wsHtml = `<span class="badge rounded-pill px-3 py-1.5 badge-leaves" style="font-size: 0.78rem; font-weight: 500;">Leaves: ${rec.weekly_summary_value}</span>`;
                                } else if (rec.day_of_week === 2) {
                                    wsHtml = `<span class="badge rounded-pill px-3 py-1.5 badge-absent" style="font-size: 0.78rem; font-weight: 500;">Absent: ${rec.weekly_summary_value}</span>`;
                                } else if (rec.day_of_week === 3) {
                                    wsHtml = `<span class="badge rounded-pill px-3 py-1.5 badge-holiday" style="font-size: 0.78rem; font-weight: 500;">Holiday: ${rec.weekly_summary_value}</span>`;
                                } else if (rec.day_of_week === 4) {
                                    wsHtml = `<span class="badge rounded-pill px-3 py-1.5 badge-working-hrs" style="font-size: 0.78rem; font-weight: 500;">Working Hrs: ${rec.weekly_summary_value}</span>`;
                                } else if (rec.day_of_week === 5) {
                                    wsHtml = `<span class="badge rounded-pill px-3 py-1.5 badge-expected" style="font-size: 0.78rem; font-weight: 500;">Expected: ${rec.weekly_summary_value}</span>`;
                                } else if (rec.day_of_week === 6) {
                                    wsHtml = `<span class="badge rounded-pill px-3 py-1.5 badge-given" style="font-size: 0.78rem; font-weight: 500;">Given: ${rec.weekly_summary_value}</span>`;
                                } else if (rec.day_of_week === 7) {
                                    let diffClass = rec.weekly_summary_diff_seconds >= 0 ? 'badge-diff-positive' : 'badge-diff-negative';
                                    wsHtml = `<span class="badge rounded-pill px-3 py-1.5 ${diffClass}" style="font-size: 0.78rem; font-weight: 500;">Difference: ${rec.weekly_summary_value}</span>`;
                                }
                            }
                            bodyHtml += `<td colspan="2" class="text-center">${wsHtml}</td>`;
                        }
                        
                        // Percentage column with styling corresponding to values
                        let pctBadge = '';
                        if (rec.is_filler) {
                            pctBadge = '<span class="text-muted opacity-25 font-monospace">-</span>';
                        } else {
                            pctBadge = `<span class="badge rounded-pill px-2.5 py-1 badge-percentage" style="font-size: 0.72rem; font-weight: 500;">${rec.pct_formatted}</span>`;
                        }
                        bodyHtml += `<td class="text-center">${pctBadge}</td>`;
                        
                        // Total Session Time
                        let totalClass = rec.total_seconds > 0 ? 'text-total-positive' : 'text-total-zero';
                        if (rec.is_filler) {
                            totalClass += ' opacity-35';
                        }
                        bodyHtml += `<td class="text-center font-monospace ${totalClass}">${rec.total_formatted}</td>`;
 
                        // Fill in dynamic session slots
                        for (let i = 0; i < displaySessions; i++) {
                            const session = rec.sessions[i];
                            let cellStyle = '';
                            if (rec.is_filler) {
                                cellStyle = 'style="opacity: 0.35;"';
                            }
 
                            if (session) {
                                let inVal = session.in || '-';
                                let outVal = session.out || '-';
 
                                let inClass = session.in ? 'text-white' : 'text-muted';
                                let outClass = '';

                                if (session.is_virtual_in) {
                                    inVal = `<span class="text-warning" data-custom-tooltip="${session.virtual_in_reason || 'Virtual Entry'}">${session.in} <i class="bi bi-exclamation-triangle-fill text-warning ms-1" style="font-size: 0.82rem;"></i></span>`;
                                }

                                if (session.out === 'Incomplete') {
                                    outVal = `<span class="badge rounded-pill px-2 py-0.5 badge-session-incomplete" style="font-size: 0.7rem; font-weight: 500;">Incomplete</span>`;
                                } else if (session.out && session.out.includes('(Active)')) {
                                    outVal = `<span class="badge rounded-pill px-2 py-0.5 badge-session-active" style="font-size: 0.7rem; font-weight: 500;">Active</span>`;
                                } else if (session.is_virtual_out) {
                                    outVal = `<span class="text-warning" data-custom-tooltip="${session.virtual_out_reason || 'Virtual Entry'}">${session.out} <i class="bi bi-exclamation-triangle-fill text-warning ms-1" style="font-size: 0.82rem;"></i></span>`;
                                } else if (session.out) {
                                    outClass = 'text-white';
                                } else {
                                    outClass = 'text-muted';
                                }
 
                                bodyHtml += `<td class="text-center font-monospace ${inClass}" ${cellStyle}>${inVal}</td>`;
                                bodyHtml += `<td class="text-center font-monospace ${outClass}" ${cellStyle}>${outVal}</td>`;
                            } else {
                                bodyHtml += `<td class="text-center text-muted opacity-25 font-monospace" ${cellStyle}>-</td>`;
                                bodyHtml += `<td class="text-center text-muted opacity-25 font-monospace" ${cellStyle}>-</td>`;
                            }
                        }
                        bodyHtml += '</tr>';
                    });
                    tbody.html(bodyHtml);
                }
            });
        </script>
    @endpush
</x-app-layout>
