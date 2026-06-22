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
        .table-light-theme.spotify-card:hover {
            background-color: #f1f5f9 !important;
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
        /* Row hover highlights in light mode */
        .table-light-theme #dynamic-tbody tr:hover td {
            background-color: #e2e8f0 !important; /* Elegant hover highlight in light mode */
        }
        .table-light-theme #dynamic-tbody tr[style*="rgba(255, 0, 0"]:hover td {
            background-color: #fecaca !important; /* Slightly darker rose red for weekend hover */
        }
        .table-light-theme #dynamic-tbody tr.tr-dirty:hover td {
            background-color: rgba(22, 163, 74, 0.18) !important;
        }
        /* Weekend rows */
        .table-light-theme #dynamic-tbody tr[style*="rgba(255, 0, 0"] td,
        .table-light-theme #dynamic-tbody tr[style*="rgba(255, 0, 0"] {
            background-color: #fee2e2 !important; /* Soft rose red background */
            color: #7f1d1d !important; /* Darker red-900 for high-contrast weekend text */
            font-weight: 600 !important;
            border-color: #fca5a5 !important;
        }
        .table-light-theme #dynamic-tbody tr[style*="rgba(255, 0, 0"] td:nth-child(2) {
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
            color: #16a34a !important; /* Premium bright green */
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
        
        /* Light mode text-warning override for high contrast on white background */
        .table-light-theme .text-warning,
        .table-light-theme .text-warning i {
            color: #ffc400b4 !important; 
        }

        /* Light mode input overrides in edit mode to ensure high readability and premium aesthetic */
        .table-light-theme .edit-time-input {
            background-color: #ffffff !important;
            color: #0f172a !important;
            border-color: #cbd5e1 !important;
        }
        .table-light-theme .edit-time-input:focus {
            background-color: #ffffff !important;
            border-color: #16a34a !important;
            box-shadow: 0 0 5px rgba(22, 163, 74, 0.4) !important;
        }
        
        /* Light mode virtual entry bubble/glow override (dark yellow bubble) */
        .table-light-theme .edit-time-input-virtual {
            background-color: #fffbeb !important;
            border-color: #d97706 !important;
            color: #b45309 !important;
            box-shadow: 0 0 5px rgba(217, 119, 6, 0.35) !important;
        }

        /* Light mode active session bubble/glow override (blue bubble) */
        .table-light-theme .edit-time-input-active {
            background-color: #eff6ff !important;
            border-color: #2563eb !important;
            color: #1e40af !important;
            box-shadow: 0 0 5px rgba(37, 99, 235, 0.35) !important;
        }

        /* Light mode error validation highlight override (red bubble) */
        .table-light-theme .edit-time-input.border-danger {
            background-color: #fef2f2 !important;
            border-color: #ef4444 !important;
            color: #991b1b !important;
            box-shadow: 0 0 5px rgba(239, 68, 68, 0.4) !important;
        }

        /* Leave day row styling in Light Mode */
        .table-light-theme #dynamic-tbody tr.tr-leave td,
        .table-light-theme #dynamic-tbody tr[style*="rgba(217, 119, 6"] td {
            background-color: #fef08a !important; /* Brighter premium yellow */
            color: #713f12 !important; /* Dark brown text for contrast */
        }
        .table-light-theme #dynamic-tbody tr.tr-leave td span,
        .table-light-theme #dynamic-tbody tr[style*="rgba(217, 119, 6"] td span {
            color: #713f12 !important;
        }
        .table-light-theme #dynamic-tbody tr.tr-leave td.text-total-zero,
        .table-light-theme #dynamic-tbody tr[style*="rgba(217, 119, 6"] td.text-total-zero {
            color: #a16207 !important;
        }
        /* Leave inputs in light mode edit mode */
        .table-light-theme #dynamic-tbody tr.tr-leave .edit-time-input:not(.edit-time-input-virtual):not(.edit-time-input-active) {
            background-color: #fefef0 !important;
            color: #713f12 !important;
            border-color: #fde047 !important;
        }
        
        
        /* Current day percentage badge styling for high readability and premium look */
        tr.tr-current .badge-percentage {
            background-color: #2563eb !important; /* Premium blue background */
            color: #ffffff !important; /* High contrast white text */
        }
        .table-light-theme tr.tr-current .badge-percentage {
            background-color: #2563eb !important; /* Premium blue background */
            color: #ffffff !important; /* High contrast white text */
        }
        
        /* Ensure percentage badge on leave rows looks like other rows, not blue */
        .table-light-theme #dynamic-tbody tr.tr-leave td span.badge-percentage,
        .table-light-theme #dynamic-tbody tr[style*="rgba(217, 119, 6"] td span.badge-percentage {
            background-color: #334155 !important;
            color: #ffffff !important;
        }
        #dynamic-tbody tr.tr-leave td span.badge-percentage,
        #dynamic-tbody tr[style*="rgba(217, 119, 6"] td span.badge-percentage {
            background-color: #ffffffee !important;
            color: #000000 !important;
        }
        
        /* Leave day row styling in Dark Mode */
        #dynamic-tbody tr.tr-leave td {
            color: rgba(255, 255, 255, 0.95) !important;
        }
        #dynamic-tbody tr.tr-leave td.text-total-zero {
            color: rgba(245, 158, 11, 0.75) !important; /* Brighter yellow text for total zero */
        }
        /* Leave inputs in dark mode edit mode */
        #dynamic-tbody tr.tr-leave .edit-time-input:not(.edit-time-input-virtual):not(.edit-time-input-active) {
            background-color: rgba(245, 158, 11, 0.15) !important;
            color: #fbbf24 !important;
            border-color: rgba(245, 158, 11, 0.4) !important;
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
        .edit-time-input {
            transition: all 0.2s ease-in-out;
        }
        .edit-time-input:focus {
            background-color: #1a1a1a !important;
            border-color: #1db954 !important;
            box-shadow: 0 0 5px rgba(29, 185, 84, 0.4) !important;
        }
        .tr-dirty {
            background-color: rgba(29, 185, 84, 0.08) !important;
            border-left: 3px solid #1db954 !important;
        }
        .table-light-theme .tr-dirty {
            background-color: rgba(22, 163, 74, 0.1) !important;
            border-left: 3px solid #16a34a !important;
        }
        .border-warning-glow {
            border: 1.5px solid #f59e0b !important;
        }
        .border-warning-glow:focus {
            box-shadow: 0 0 5px rgba(245, 158, 11, 0.5) !important;
        }
        .edit-time-input-virtual {
            border-color: #f59e0b !important;
            box-shadow: 0 0 5px rgba(245, 158, 11, 0.35) !important;
        }
        .edit-time-input-active {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 5px rgba(59, 130, 246, 0.35) !important;
        }
        .edit-time-input.border-danger {
            border-color: #ef4444 !important;
            box-shadow: 0 0 5px rgba(239, 68, 68, 0.4) !important;
        }
        #dynamic-tbody td {
            height: 46px;
            vertical-align: middle !important;
            padding-top: 4px !important;
            padding-bottom: 4px !important;
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
                <input type="number" class="form-control form-control-sm text-white" id="custom-year-select" style="height: 32px !important; border-radius: 50px !important; padding: 0 1rem !important; font-size: 0.85rem !important; background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;">
            </div>

            <!-- Clear Filter Button -->
            <div class="col-md-2 d-flex align-items-end">
                <a href="{{ route('super-admin.session-management.track-record') }}" class="row-btn w-100 text-decoration-none justify-content-center" style="height: 32px !important;">
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
                <div class="d-flex align-items-center gap-1">
                    <h6 class="text-uppercase text-white fw-semibold small mb-0 opacity-75" style="letter-spacing: 0.05em;" id="report-title">Employee Timesheet Summary</h6>
                    <button type="button" class="btn btn-sm btn-link text-white p-0 d-none" id="edit-sheet-btn" data-custom-tooltip="Edit Timesheet" data-tooltip-placement="top" style="border: none; background: transparent; outline: none; box-shadow: none; padding: 0 !important; margin-left: 6px !important;">
                        <i class="bi bi-pencil text-secondary fs-6"></i>
                    </button>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <!-- Save / Cancel buttons (hidden initially) -->
                    <div id="edit-actions" class="d-none d-flex align-items-center gap-2">
                        <button type="button" class="row-btn-outline-blue" id="save-sheet-btn" style="height: 28px !important; padding: 0 12px !important; font-size: 0.75rem !important; display: inline-flex !important; align-items: center !important;">
                            Save
                        </button>
                        <button type="button" class="row-btn-outline-red" id="cancel-sheet-btn" style="height: 28px !important; padding: 0 12px !important; font-size: 0.75rem !important; display: inline-flex !important; align-items: center !important;">
                            Cancel
                        </button>
                    </div>
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
        
        <div class="table-responsive" style="max-height: 74vh; min-height: 61vh; overflow-y: auto; overflow-x: auto;">
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

                let isEditMode = false;
                let rawReportData = null;
                let originalReportData = null;

                function cleanTimeStr(val) {
                    if (!val || val === '-' || val === 'Incomplete' || val === 'Active') return '';
                    return val.replace(/\s*\(.*?\)/g, '').trim();
                }

                function validateTimeFormat(timeStr) {
                    if (!timeStr) return true;
                    const regex = /^(0?[1-9]|1[0-2]):([0-5][0-9])(:[0-5][0-9])?\s*(AM|PM)$/i;
                    return regex.test(timeStr.trim());
                }

                function parseTimeToSeconds(timeStr) {
                    if (!timeStr) return null;
                    timeStr = timeStr.trim().toUpperCase();
                    const match = timeStr.match(/^(0?[1-9]|1[0-2]):([0-5][0-9])(?::([0-5][0-9]))?\s*(AM|PM)$/i);
                    if (!match) return null;
                    
                    let hours = parseInt(match[1]);
                    const minutes = parseInt(match[2]);
                    const seconds = match[3] ? parseInt(match[3]) : 0;
                    const ampm = match[4];
                    
                    if (hours < 1 || hours > 12 || minutes < 0 || minutes > 59 || seconds < 0 || seconds > 59) {
                        return null;
                    }
                    
                    if (ampm === 'PM' && hours !== 12) {
                        hours += 12;
                    } else if (ampm === 'AM' && hours === 12) {
                        hours = 0;
                    }
                    
                    return hours * 3600 + minutes * 60 + seconds;
                }

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

                // Set initial default selections for month/year select
                const currentYear = new Date().getFullYear();
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
                function fetchReport(autoEdit = false) {
                    const userId = $('#employee-select').val();
                    const period = $('#period-select').val();

                    // Clear comment textarea on every fresh load
                    $('#summary-comment').val('');

                    if (!userId) {
                        // Keep view in initial state if no user selected
                        $('#placeholder-card').removeClass('d-none');
                        $('#results-card').addClass('d-none');
                        $('#summary-dashboard-card').addClass('d-none');
                        $('#edit-sheet-btn').addClass('d-none');
                        $('#edit-actions').addClass('d-none');
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
                             rawReportData = response;
                             originalReportData = JSON.parse(JSON.stringify(response));
                             isEditMode = autoEdit;
                            if (isEditMode) {
                                $('#edit-sheet-btn').addClass('d-none');
                                $('#edit-actions').removeClass('d-none');
                            } else {
                                $('#edit-actions').addClass('d-none');
                                if (userId && userId !== 'all') {
                                    $('#edit-sheet-btn').removeClass('d-none');
                                } else {
                                    $('#edit-sheet-btn').addClass('d-none');
                                }
                            }

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
                        window.customTooltip.removeClass('show arrow-left arrow-bottom arrow-right');
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

                // Toggle Edit Mode
                $('#edit-sheet-btn').on('click', function() {
                    isEditMode = true;
                    $('#edit-sheet-btn').addClass('d-none');
                    $('#edit-actions').removeClass('d-none');
                    renderTable(rawReportData);
                });

                // Cancel Edit Mode
                $('#cancel-sheet-btn').on('click', function() {
                    isEditMode = false;
                    $('#edit-actions').addClass('d-none');
                    $('#edit-sheet-btn').removeClass('d-none');
                    rawReportData = JSON.parse(JSON.stringify(originalReportData));
                    renderTable(rawReportData);
                });

                // Highlight Row on input
                $(document).on('input change', '.edit-time-input', function() {
                    const row = $(this).closest('tr');
                    row.addClass('tr-dirty');
                    
                    const dateStr = row.data('date');
                    const type = $(this).data('type'); // 'in' or 'out'
                    const idx = parseInt($(this).data('session-index'));
                    const val = $(this).val().trim();

                    // Sync into rawReportData
                    if (rawReportData && rawReportData.records) {
                        const rec = rawReportData.records.find(r => r.raw_date_string === dateStr);
                        if (rec) {
                            rec.is_dirty = true;
                            if (!rec.sessions) rec.sessions = [];
                            while (rec.sessions.length <= idx) {
                                rec.sessions.push({ in: '', out: '', is_virtual_in: false, is_virtual_out: false });
                            }
                            rec.sessions[idx][type] = val;
                            
                            // If user typed a change, it is no longer virtual
                            const originalValue = $(this).attr('data-original-value') || '';
                            if (val !== originalValue) {
                                rec.sessions[idx]['is_virtual_' + type] = false;
                                rec.sessions[idx][type + '_is_virtual'] = false;
                            }
                        }
                    }

                    if ($(this).attr('data-custom-tooltip') && $(this).attr('data-custom-tooltip').startsWith('Active -')) {
                        $(this).attr('data-custom-tooltip', 'Active - ' + val);
                    }

                    // Virtual to Real transition
                    const originalValue = $(this).attr('data-original-value') || '';
                    if (val !== originalValue) {
                        $(this).attr('data-is-virtual', 'false');
                        $(this).removeClass('edit-time-input-virtual');
                        $(this).removeAttr('data-custom-tooltip');
                    }

                    let displaySessions = Math.max(2, rawReportData.max_sessions || 2);
                    if (isEditMode) {
                        displaySessions = Math.min(9, displaySessions + 1);
                    }
                    if (type === 'out' && idx === displaySessions - 1 && val.length > 0) {
                        const maxAllowed = 9; // Allow up to 9 sessions (Sign In 9 & Sign Out 9)
                        if (displaySessions < maxAllowed) {
                            rawReportData.max_sessions = displaySessions;

                            // Save focus state to restore after rendering
                            const activeElement = document.activeElement;
                            let focusDate = null, focusType = null, focusIdx = null, selStart = 0, selEnd = 0;
                            if (activeElement && $(activeElement).hasClass('edit-time-input')) {
                                focusDate = $(activeElement).closest('tr').data('date');
                                focusType = $(activeElement).data('type');
                                focusIdx = $(activeElement).data('session-index');
                                try {
                                    selStart = activeElement.selectionStart;
                                    selEnd = activeElement.selectionEnd;
                                } catch(e) {}
                            }

                            // Re-render table with new columns
                            renderTable(rawReportData);

                            // Restore focus and selection
                            if (focusDate !== null) {
                                const targetInput = $(`tr[data-date="${focusDate}"]`).find(`.edit-time-input[data-type="${focusType}"][data-session-index="${focusIdx}"]`);
                                if (targetInput.length > 0) {
                                    targetInput.focus();
                                    try {
                                        targetInput[0].setSelectionRange(selStart, selEnd);
                                    } catch(e) {}
                                }
                            }
                        }
                    }
                });

                // Validate and format time input on blur
                $(document).on('blur', '.edit-time-input', function() {
                    let val = $(this).val().trim();
                    if (!val) {
                        $(this).removeClass('border-danger');
                        return;
                    }
                    
                    // 1. Standardize AM/PM spacing and casing (e.g. 08:00am -> 08:00 AM)
                    val = val.replace(/\s*(AM|PM|am|pm)/i, ' $1').toUpperCase();
                    
                    // 2. Pad hour and append missing seconds if needed
                    const parts = val.split(/\s+/);
                    if (parts.length === 2) {
                        let timePart = parts[0];
                        const ampm = parts[1];
                        
                        const timeSubParts = timePart.split(':');
                        if (timeSubParts.length >= 2) {
                            let hour = timeSubParts[0].trim();
                            let minute = timeSubParts[1].trim();
                            let second = '00';
                            
                            if (timeSubParts.length === 3) {
                                second = timeSubParts[2].trim();
                            }
                            
                            // Pad hour if single digit
                            if (hour.length === 1) {
                                hour = '0' + hour;
                            }
                            
                            // Reassemble with seconds
                            val = `${hour}:${minute}:${second} ${ampm}`;
                        }
                    }
                    
                    $(this).val(val);

                    // Sync formatted/sanitized time back into rawReportData
                    const row = $(this).closest('tr');
                    const dateStr = row.data('date');
                    const type = $(this).data('type');
                    const idx = parseInt($(this).data('session-index'));

                    if (rawReportData && rawReportData.records) {
                        const rec = rawReportData.records.find(r => r.raw_date_string === dateStr);
                        if (rec) {
                            if (!rec.sessions) rec.sessions = [];
                            while (rec.sessions.length <= idx) {
                                rec.sessions.push({ in: '', out: '', is_virtual_in: false, is_virtual_out: false });
                            }
                            rec.sessions[idx][type] = val;
                        }
                    }

                    if ($(this).attr('data-custom-tooltip') && $(this).attr('data-custom-tooltip').startsWith('Active -')) {
                        $(this).attr('data-custom-tooltip', 'Active - ' + val);
                    }

                    if (!validateTimeFormat(val)) {
                        $(this).addClass('border-danger');
                        showError('Invalid time format. Please use hh:mm AM/PM or hh:mm:ss AM/PM.');
                    } else {
                        $(this).removeClass('border-danger');
                    }
                });

                // Save Edits
                $('#save-sheet-btn').on('click', function() {
                    $('.edit-time-input').trigger('blur');
                    
                    let dateEntries = {};
                    let hasValidationError = false;

                    // Clear previous input validation borders
                    $('.edit-time-input').removeClass('border-danger');

                    $('#dynamic-tbody tr[data-date].tr-dirty').each(function() {
                        const row = $(this);
                        const dateStr = row.data('date');
                        if (!dateStr) return;

                        let inputs = [];
                        row.find('.edit-time-input').each(function() {
                            const type = $(this).data('type');
                            const idx = parseInt($(this).data('session-index'));
                            const val = $(this).val().trim();
                            const isVirtual = $(this).attr('data-is-virtual') === 'true';
                            inputs.push({
                                type: type,
                                idx: idx,
                                val: val,
                                isVirtual: isVirtual,
                                element: $(this)
                            });
                        });

                        let sessions = [];
                        inputs.forEach(inp => {
                            if (!sessions[inp.idx]) {
                                sessions[inp.idx] = { in: null, out: null };
                            }
                            sessions[inp.idx][inp.type] = inp;
                        });

                        let timeline = [];
                        let formatError = false;

                        for (let i = 0; i < sessions.length; i++) {
                            const sess = sessions[i];
                            if (!sess) continue;

                            const inInp = sess.in;
                            const outInp = sess.out;

                            if (inInp && inInp.val) {
                                if (!validateTimeFormat(inInp.val)) {
                                    inInp.element.addClass('border-danger');
                                    hasValidationError = true;
                                    formatError = true;
                                } else {
                                    const secs = parseTimeToSeconds(inInp.val);
                                    timeline.push({
                                        type: 'in',
                                        sessionIndex: i,
                                        val: inInp.val,
                                        seconds: secs,
                                        element: inInp.element
                                    });
                                }
                            }

                            if (outInp && outInp.val) {
                                if (!validateTimeFormat(outInp.val)) {
                                    outInp.element.addClass('border-danger');
                                    hasValidationError = true;
                                    formatError = true;
                                } else {
                                    const secs = parseTimeToSeconds(outInp.val);
                                    timeline.push({
                                        type: 'out',
                                        sessionIndex: i,
                                        val: outInp.val,
                                        seconds: secs,
                                        element: outInp.element
                                    });
                                }
                            }
                        }

                        if (formatError) {
                            return; // Skip rest of this row, go to next row
                        }

                        // Missing Sign In check
                        let missingSignInError = false;
                        for (let i = 0; i < sessions.length; i++) {
                            const sess = sessions[i];
                            if (!sess) continue;
                            const inVal = sess.in ? sess.in.val : '';
                            const outVal = sess.out ? sess.out.val : '';

                            if (outVal && !inVal) {
                                if (sess.out) sess.out.element.addClass('border-danger');
                                if (sess.in) sess.in.element.addClass('border-danger');
                                hasValidationError = true;
                                missingSignInError = true;
                            }
                        }

                        if (missingSignInError) {
                            return; // Skip rest of this row, go to next row
                        }

                        // Check for session gaps before the last filled session
                        let maxFilledIndex = -1;
                        for (let i = 0; i < sessions.length; i++) {
                            const sess = sessions[i];
                            if (!sess) continue;
                            if ((sess.in && sess.in.val) || (sess.out && sess.out.val)) {
                                maxFilledIndex = i;
                            }
                        }

                        let sessionGapError = false;
                        for (let i = 0; i < maxFilledIndex; i++) {
                            const sess = sessions[i];
                            const inVal = (sess && sess.in) ? sess.in.val : '';
                            const outVal = (sess && sess.out) ? sess.out.val : '';

                            if (!inVal || !outVal) {
                                if (sess) {
                                    if (sess.in && !inVal) sess.in.element.addClass('border-danger');
                                    if (sess.out && !outVal) sess.out.element.addClass('border-danger');
                                }
                                hasValidationError = true;
                                sessionGapError = true;
                            }
                        }

                        if (sessionGapError) {
                            return; // Skip rest of this row, go to next row
                        }

                        // Chronological alignment check
                        let chronologyError = false;
                        for (let k = 0; k < timeline.length - 1; k++) {
                            const currentEvent = timeline[k];
                            const nextEvent = timeline[k + 1];

                            if (currentEvent.seconds >= nextEvent.seconds) {
                                currentEvent.element.addClass('border-danger');
                                nextEvent.element.addClass('border-danger');
                                hasValidationError = true;
                                chronologyError = true;
                            }
                        }

                        if (chronologyError) {
                            return; // Skip rest of this row, go to next row
                        }

                        // Clean data structure construction
                        let cleanSessions = [];
                        for (let i = 0; i < sessions.length; i++) {
                            const sess = sessions[i];
                            if (!sess) continue;
                            const inVal = sess.in ? sess.in.val : '';
                            const outVal = sess.out ? sess.out.val : '';
                            const inIsVirtual = sess.in ? sess.in.isVirtual : false;
                            const outIsVirtual = sess.out ? sess.out.isVirtual : false;

                            if (inVal || outVal) {
                                cleanSessions.push({
                                    in: inVal,
                                    out: outVal,
                                    in_is_virtual: inIsVirtual,
                                    out_is_virtual: outIsVirtual
                                });
                            }
                        }

                        dateEntries[dateStr] = cleanSessions;
                    });

                    if (hasValidationError) {
                        showError('Validation Error: Please fix all highlighted inputs in red before saving.');
                        return;
                    }

                    const userId = $('#employee-select').val();
                    if (!userId) return;

                    // Show reconfirmation modal
                    const confirmModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmActionModal'));
                    confirmModal.show();

                    // Bind click to confirm action button
                    $('#btnConfirmAction').off('click').on('click', function() {
                        confirmModal.hide();
                        
                        // Disable actions
                        $('#save-sheet-btn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');
                        $('#cancel-sheet-btn').prop('disabled', true);

                        $.ajax({
                            url: "{{ route('super-admin.session-management.track-record.save-overrides') }}",
                            method: "POST",
                            data: {
                                _token: "{{ csrf_token() }}",
                                user_id: userId,
                                date_entries: dateEntries
                            },
                            success: function(response) {
                                if (window.showToast) {
                                    window.showToast(response.message || 'Timesheet manual overrides saved successfully!', 'success');
                                } else {
                                    alert(response.message || 'Timesheet manual overrides saved successfully!');
                                }
                                isEditMode = false;
                                $('#edit-actions').addClass('d-none');
                                $('#edit-sheet-btn').removeClass('d-none');
                                fetchReport();
                            },
                            error: function(err) {
                                let msg = 'Failed to save timesheet overrides.';
                                if (err.responseJSON && err.responseJSON.message) {
                                    msg = err.responseJSON.message;
                                }
                                showError(msg);
                            },
                            complete: function() {
                                $('#save-sheet-btn').prop('disabled', false).html('Save');
                                $('#cancel-sheet-btn').prop('disabled', false);
                            }
                        });
                    });
                });

                // Initial run - parse URL query parameters to auto-fill searches or trigger edit mode
                const urlParams = new URLSearchParams(window.location.search);
                const paramUserId = urlParams.get('user_id');
                const paramPeriod = urlParams.get('period');
                const paramMonth = urlParams.get('month');
                const paramYear = urlParams.get('year');
                const paramEdit = urlParams.get('edit');

                let autoEditTrigger = false;

                if (paramUserId) {
                    $('#employee-select').val(paramUserId);
                }
                if (paramPeriod) {
                    $('#period-select').val(paramPeriod).trigger('change');
                }
                if (paramMonth) {
                    $('#custom-month-select').val(paramMonth);
                }
                if (paramYear) {
                    $('#custom-year-select').val(paramYear);
                }
                if (paramEdit === 'true' || paramEdit === '1') {
                    autoEditTrigger = true;
                }

                updateExportButtonVisibility();

                if (paramUserId) {
                    fetchReport(autoEditTrigger);
                }

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

                    let displaySessions = Math.max(2, maxSessions);
                    if (isEditMode) {
                        displaySessions = Math.min(9, displaySessions + 1);
                    }
                    
                    let tableWidth = '100%';
                    let tableMinWidth = 1000;
                    let sessColWidth = '10%';

                    if (isToday) {
                        sessColWidth = '15.5%';
                        if (displaySessions > 2) {
                            tableWidth = (100 + (displaySessions - 2) * 31) + '%';
                            tableMinWidth += (displaySessions - 2) * 310;
                        }
                    } else {
                        sessColWidth = '10%';
                        if (displaySessions > 2) {
                            tableWidth = (100 + (displaySessions - 2) * 20) + '%';
                            tableMinWidth += (displaySessions - 2) * 200;
                        }
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
                        `;
                        for (let i = 1; i <= displaySessions; i++) {
                            colgroupHtml += `
                                <col style="width: ${sessColWidth};">
                                <col style="width: ${sessColWidth};">
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
                        `;
                        for (let i = 1; i <= displaySessions; i++) {
                            colgroupHtml += `
                                <col style="width: ${sessColWidth};">
                                <col style="width: ${sessColWidth};">
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
                    const todayStr = new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Karachi' });
                    records.forEach(function(rec) {
                        const isCurrentDay = (rec.raw_date_string === todayStr);
                        let rowStyle = '';
                        let rowAttr = '';
                        let rowClass = '';
                        if (rec.is_leave) {
                            rowStyle = 'style="background-color: rgba(217, 119, 6, 0.18) !important;"';
                            rowClass = 'tr-leave';
                        } else if (rec.is_weekend) {
                            rowStyle = 'style="background-color: rgba(255, 0, 0, 0.18) !important;"';
                        }
                        if (isCurrentDay) {
                            rowClass += (rowClass ? ' ' : '') + 'tr-current';
                        }
                        if (rec.raw_date_string) {
                            rowAttr = `data-date="${rec.raw_date_string}"`;
                        }
                        if (rec.is_dirty) {
                            rowClass += (rowClass ? ' ' : '') + 'tr-dirty';
                        }
                        bodyHtml += `<tr ${rowStyle} ${rowAttr} class="${rowClass}">`;
                        
                        // Date/Employee name column
                        if (isAll) {
                            bodyHtml += `<td class="ps-4 text-start fw-semibold text-white">${rec.employee_name || '-'}</td>`;
                        } else {
                            if (rec.is_filler) {
                                bodyHtml += `<td class="ps-4 text-start text-white font-monospace"><span class="opacity-25">-</span></td>`;
                            } else {
                                bodyHtml += `<td class="ps-4 text-start fw-semibold text-white font-monospace">${rec.date || '-'}</td>`;
                            }
                        }
                        
                        // Day column
                        let dayClass = rec.is_weekend ? 'text-weekend-day' : 'text-normal-day';
                        let dayInnerClass = '';
                        if (rec.is_filler) {
                            dayInnerClass = 'opacity-35 fst-italic';
                        }
                        bodyHtml += `<td class="text-center ${dayClass}"><span class="${dayInnerClass}">${rec.day_name}</span></td>`;
                        
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
                        let totalInnerClass = '';
                        if (rec.is_filler) {
                            totalInnerClass = 'opacity-35';
                        }
                        bodyHtml += `<td class="text-center font-monospace ${totalClass}"><span class="${totalInnerClass}">${rec.total_formatted}</span></td>`;
 
                        // Fill in dynamic session slots
                        for (let i = 0; i < displaySessions; i++) {
                            const session = rec.sessions[i];
                            let cellStyle = '';
                            if (rec.is_filler) {
                                cellStyle = 'style="opacity: 0.35;"';
                            }
 
                            if (isEditMode && !rec.is_filler) {
                                let inRaw = '';
                                let outRaw = '';
                                let isVirtualIn = false;
                                let isVirtualOut = false;
                                let virtualInReason = '';
                                let virtualOutReason = '';

                                if (session) {
                                    inRaw = cleanTimeStr(session.in);
                                    outRaw = cleanTimeStr(session.out);
                                    isVirtualIn = !!session.is_virtual_in;
                                    isVirtualOut = !!session.is_virtual_out;
                                    virtualInReason = session.virtual_in_reason || 'Auto-appended';
                                    virtualOutReason = session.virtual_out_reason || 'Auto-appended';
                                }

                                // Render Sign In input
                                let inInputHtml = '';
                                let inTdAttrs = '';
                                if (isCurrentDay) {
                                    const inTooltipAttr = isVirtualIn ? `data-custom-tooltip="${virtualInReason}" data-tooltip-placement="top"` : '';
                                    inTdAttrs = `${inTooltipAttr}`;
                                    
                                    inInputHtml = `
                                        <input type="text" 
                                               class="form-control form-control-sm text-center font-monospace bg-dark text-white edit-time-input ${isVirtualIn ? 'edit-time-input-virtual' : 'border-secondary'}" 
                                               value="${inRaw}" 
                                               placeholder="hh:mm AM/PM" 
                                               data-session-index="${i}" 
                                               data-type="in"
                                               data-is-virtual="${isVirtualIn}"
                                               data-original-value="${inRaw}"
                                               tabindex="-1"
                                               readonly
                                               style="font-size: 0.85rem !important; height: 28px; border-radius: 4px; opacity: 0.6; cursor: not-allowed; user-select: none; pointer-events: none;">
                                    `;
                                } else {
                                    if (isVirtualIn) {
                                        inInputHtml = `
                                            <input type="text" 
                                                   class="form-control form-control-sm text-center font-monospace bg-dark text-white edit-time-input edit-time-input-virtual" 
                                                   value="${inRaw}" 
                                                   placeholder="hh:mm AM/PM" 
                                                   data-session-index="${i}" 
                                                   data-type="in"
                                                   data-custom-tooltip="${virtualInReason}"
                                                   data-tooltip-placement="top"
                                                   data-is-virtual="true"
                                                   data-original-value="${inRaw}"
                                                   style="font-size: 0.85rem !important; height: 28px; border-radius: 4px;">
                                        `;
                                    } else {
                                        inInputHtml = `
                                            <input type="text" 
                                                   class="form-control form-control-sm text-center font-monospace bg-dark text-white border-secondary edit-time-input" 
                                                   value="${inRaw}" 
                                                   placeholder="hh:mm AM/PM" 
                                                   data-session-index="${i}" 
                                                   data-type="in"
                                                   data-is-virtual="false"
                                                   data-original-value="${inRaw}"
                                                   style="font-size: 0.85rem !important; height: 28px; border-radius: 4px;">
                                        `;
                                    }
                                }

                                // Render Sign Out input
                                let outInputHtml = '';
                                let outTdAttrs = '';
                                const isActiveOut = session && session.out && session.out.includes('(Active)');

                                if (isCurrentDay) {
                                    let outTooltipAttr = '';
                                    const placement = (i === displaySessions - 1) ? 'left' : 'top';
                                    if (isActiveOut) {
                                        outTooltipAttr = `data-custom-tooltip="active time" data-tooltip-placement="${placement}"`;
                                    } else if (isVirtualOut) {
                                        outTooltipAttr = `data-custom-tooltip="${virtualOutReason}" data-tooltip-placement="${placement}"`;
                                    }
                                    outTdAttrs = `${outTooltipAttr}`;
                                    
                                    outInputHtml = `
                                        <input type="text" 
                                               class="form-control form-control-sm text-center font-monospace bg-dark text-white edit-time-input ${isActiveOut ? 'edit-time-input-active' : (isVirtualOut ? 'edit-time-input-virtual' : 'border-secondary')}" 
                                               value="${outRaw}" 
                                               placeholder="hh:mm AM/PM" 
                                               data-session-index="${i}" 
                                               data-type="out"
                                               data-is-virtual="${isVirtualOut}"
                                               data-original-value="${outRaw}"
                                               tabindex="-1"
                                               readonly
                                               style="font-size: 0.85rem !important; height: 28px; border-radius: 4px; opacity: 0.6; cursor: not-allowed; user-select: none; pointer-events: none;">
                                    `;
                                } else {
                                    const placement = (i === displaySessions - 1) ? 'left' : 'top';
                                    if (isActiveOut) {
                                        outInputHtml = `
                                            <input type="text" 
                                                   class="form-control form-control-sm text-center font-monospace bg-dark text-white edit-time-input edit-time-input-active" 
                                                   value="${outRaw}" 
                                                   placeholder="hh:mm AM/PM" 
                                                   data-session-index="${i}" 
                                                   data-type="out"
                                                   data-custom-tooltip="Active - ${outRaw}"
                                                   data-tooltip-placement="${placement}"
                                                   data-is-virtual="false"
                                                   data-original-value="${outRaw}"
                                                   style="font-size: 0.85rem !important; height: 28px; border-radius: 4px;">
                                        `;
                                    } else if (isVirtualOut) {
                                        outInputHtml = `
                                            <input type="text" 
                                                   class="form-control form-control-sm text-center font-monospace bg-dark text-white edit-time-input edit-time-input-virtual" 
                                                   value="${outRaw}" 
                                                   placeholder="hh:mm AM/PM" 
                                                   data-session-index="${i}" 
                                                   data-type="out"
                                                   data-custom-tooltip="${virtualOutReason}"
                                                   data-tooltip-placement="${placement}"
                                                   data-is-virtual="true"
                                                   data-original-value="${outRaw}"
                                                   style="font-size: 0.85rem !important; height: 28px; border-radius: 4px;">
                                        `;
                                    } else {
                                        outInputHtml = `
                                            <input type="text" 
                                                   class="form-control form-control-sm text-center font-monospace bg-dark text-white border-secondary edit-time-input" 
                                                   value="${outRaw}" 
                                                   placeholder="hh:mm AM/PM" 
                                                   data-session-index="${i}" 
                                                   data-type="out"
                                                   data-is-virtual="false"
                                                   data-original-value="${outRaw}"
                                                   style="font-size: 0.85rem !important; height: 28px; border-radius: 4px;">
                                        `;
                                    }
                                }

                                bodyHtml += `<td class="text-center font-monospace px-1" ${inTdAttrs}>${inInputHtml}</td>`;
                                bodyHtml += `<td class="text-center font-monospace px-1" ${outTdAttrs}>${outInputHtml}</td>`;
                            } else {
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
                                        const placement = (i === displaySessions - 1) ? 'left' : 'top';
                                        outVal = `<span class="text-warning" data-custom-tooltip="${session.virtual_out_reason || 'Virtual Entry'}" data-tooltip-placement="${placement}">${session.out} <i class="bi bi-exclamation-triangle-fill text-warning ms-1" style="font-size: 0.82rem;"></i></span>`;
                                    } else if (session.out) {
                                        outClass = 'text-white';
                                    } else {
                                        outClass = 'text-muted';
                                    }
     
                                    let cellInnerStyle = '';
                                    if (rec.is_filler) {
                                        cellInnerStyle = 'style="opacity: 0.35;"';
                                    }
                                    bodyHtml += `<td class="text-center font-monospace ${inClass}"><span ${cellInnerStyle}>${inVal}</span></td>`;
                                    bodyHtml += `<td class="text-center font-monospace ${outClass}"><span ${cellInnerStyle}>${outVal}</span></td>`;
                                } else {
                                    let dashClass = 'opacity-25';
                                    let dashStyle = '';
                                    if (rec.is_filler) {
                                        dashClass = '';
                                        dashStyle = 'style="opacity: 0.15;"';
                                    }
                                    bodyHtml += `<td class="text-center text-muted font-monospace"><span class="${dashClass}" ${dashStyle}>-</span></td>`;
                                    bodyHtml += `<td class="text-center text-muted font-monospace"><span class="${dashClass}" ${dashStyle}>-</span></td>`;
                                }
                            }
                        }
                        bodyHtml += '</tr>';
                    });
                    tbody.html(bodyHtml);
                }
            });
        </script>
    @endpush

    @push('modals')
    <!-- Beautiful Custom Confirm Modal -->
    <div class="modal fade" id="confirmActionModal" tabindex="-1" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow" style="background-color: #0c0c0c !important; border: 1px solid rgba(255, 255, 255, 0.08) !important;">
                <div class="modal-body text-center p-4">
                    <div class="text-warning mb-3">
                        <i class="bi bi-exclamation-circle-fill" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-white" id="confirmTitle">Save Override Changes?</h5>
                    <p class="text-white opacity-50 small mb-4" id="confirmText">Are you sure you want to save the manual overrides for this timesheet?</p>
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="row-btn" data-bs-dismiss="modal" style="width: 145px;">Cancel</button>
                        <button type="button" class="row-btn-blue" id="btnConfirmAction" style="width: 145px;">Yes, Save</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endpush
</x-app-layout>
