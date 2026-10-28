<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <span>Salary Estimations & Payroll Overview</span>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="row-btn-outline-blue" id="btn-export-excel" style="height: 28px !important; font-size: 0.72rem !important; padding: 0 0.85rem !important;">
                    <i class="bi bi-file-earmark-excel me-1"></i> Download Excel
                </button>
                <button type="button" class="row-btn-outline-yellow" id="btn-open-rules-modal" data-bs-toggle="modal" data-bs-target="#deductionRulesModal" style="height: 28px !important; font-size: 0.72rem !important; padding: 0 0.85rem !important;">
                    <i class="bi bi-sliders me-1"></i> Salary Deduction Rules
                </button>
            </div>
        </div>
    </x-slot>

    <style>
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
        .salary-table tbody tr {
            transition: background-color 0.15s ease-in-out;
        }
        .salary-table tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.03) !important;
        }
        .avatar-circle {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(16, 185, 129, 0.2));
            border: 1px solid rgba(255, 255, 255, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.82rem;
            color: #fff;
        }
        .stat-card-glow-green {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.08) 0%, rgba(5, 150, 105, 0.02) 100%);
            border: 1px solid rgba(16, 185, 129, 0.25) !important;
        }
        .stat-card-glow-blue {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.08) 0%, rgba(37, 99, 235, 0.02) 100%);
            border: 1px solid rgba(59, 130, 246, 0.25) !important;
        }
        .stat-card-glow-red {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.08) 0%, rgba(220, 38, 38, 0.02) 100%);
            border: 1px solid rgba(239, 68, 68, 0.25) !important;
        }
        .gross-salary-cell,
        .commission-salary-cell {
            position: relative;
            vertical-align: middle;
        }
        .gross-display,
        .commission-display {
            cursor: pointer;
        }
        .gross-text,
        .commission-text {
            transition: color 0.15s ease-in-out;
        }
        .gross-salary-cell:hover .gross-text,
        .gross-display:hover .gross-text,
        .commission-salary-cell:hover .commission-text,
        .commission-display:hover .commission-text {
            color: #60a5fa !important;
        }
        .btn-circle-edit {
            width: 26px;
            height: 26px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 0.75rem;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.18);
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.15s ease-in-out;
            z-index: 5;
        }
        .gross-salary-cell:hover .btn-circle-edit,
        .commission-salary-cell:hover .btn-circle-edit {
            opacity: 1 !important;
            visibility: visible !important;
        }
        .btn-circle-edit:hover {
            background: rgba(59, 130, 246, 0.2) !important;
            border-color: #3b82f6 !important;
            color: #60a5fa !important;
            transform: translateY(-50%) scale(1.08);
        }
        .btn-circle-blue {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 1px solid rgba(59, 130, 246, 0.3) !important;
            background: rgba(59, 130, 246, 0.1) !important;
            color: #3b82f6 !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            transition: all 0.2s ease-in-out;
            cursor: pointer;
        }
        .btn-circle-blue:hover {
            background: #3b82f6 !important;
            color: #fff !important;
            border-color: #3b82f6 !important;
        }
        .btn-circle-red-filled {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 1px solid #ef4444 !important;
            background: #ef4444 !important;
            color: #ffffff !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            transition: all 0.2s ease-in-out;
            cursor: pointer;
        }
        .btn-circle-red-filled:hover {
            background: #dc2626 !important;
            color: #ffffff !important;
            border-color: #dc2626 !important;
        }
        /* Remove up/down counter arrows on number inputs */
        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none !important;
            margin: 0 !important;
        }
        input[type=number] {
            -moz-appearance: textfield !important;
        }
        .gross-input {
            width: 110px;
            height: 28px;
            background-color: #181818 !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            border-radius: 50px !important;
            color: #ffffff !important;
            font-size: 0.82rem !important;
            text-align: center !important;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .gross-input:focus {
            outline: none !important;
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3) !important;
        }
        .gross-input.is-invalid {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.3) !important;
        }
        @keyframes highlightSuccessPulse {
            0% { background-color: rgba(16, 185, 129, 0.3); }
            50% { background-color: rgba(16, 185, 129, 0.15); }
            100% { background-color: transparent; }
        }
        .row-highlight-success {
            animation: highlightSuccessPulse 2.5s ease-out !important;
        }
    </style>

    <!-- Filters Panel Card -->
    <div class="spotify-card p-4 mb-4 mt-3">
        <div class="row g-3">
            <!-- Employee Dropdown -->
            <div class="col-lg-3 col-md-6">
                <label class="form-label text-white opacity-75 small fw-semibold">Employee</label>
                <select class="form-select form-select-sm text-white" id="filter-employee" style="height: 32px !important; border-radius: 50px !important; padding: 0 1rem !important; font-size: 0.85rem !important;">
                    <option value="">All Employees</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ ($selectedUserId ?? '') == $u->id ? 'selected' : '' }}>{{ trim($u->name) }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Salary Status Filter (Configured vs Not Configured) -->
            <div class="col-lg-2 col-md-6">
                <label class="form-label text-white opacity-75 small fw-semibold">Salary Status</label>
                <select class="form-select form-select-sm text-white" id="filter-salary-status" style="height: 32px !important; border-radius: 50px !important; padding: 0 1rem !important; font-size: 0.85rem !important;">
                    <option value="all" {{ ($selectedSalaryStatus ?? 'all') == 'all' ? 'selected' : '' }}>All Statuses</option>
                    <option value="configured" {{ ($selectedSalaryStatus ?? '') == 'configured' ? 'selected' : '' }}>Configured Only</option>
                    <option value="not_configured" {{ ($selectedSalaryStatus ?? '') == 'not_configured' ? 'selected' : '' }}>Not Configured Only</option>
                </select>
            </div>

            <!-- Month Select -->
            <div class="col-lg-3 col-md-4">
                <label class="form-label text-white opacity-75 small fw-semibold">Month</label>
                <select class="form-select form-select-sm text-white" id="filter-month" style="height: 32px !important; border-radius: 50px !important; padding: 0 1rem !important; font-size: 0.85rem !important;">
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ $selectedMonth == $m ? 'selected' : '' }}>
                            {{ DateTime::createFromFormat('!m', $m)->format('F') }}
                        </option>
                    @endfor
                </select>
            </div>

            <!-- Year Select -->
            <div class="col-lg-2 col-md-4">
                <label class="form-label text-white opacity-75 small fw-semibold">Year</label>
                <input type="number" class="form-control form-control-sm text-white" id="filter-year" value="{{ $selectedYear }}" min="2020" max="2035" style="height: 32px !important; border-radius: 50px !important; padding: 0 1rem !important; font-size: 0.85rem !important; background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;">
            </div>

            <!-- Clear Filters Button -->
            <div class="col-lg-2 col-md-4 d-flex align-items-end">
                <button type="button" id="btn-clear-filters" class="row-btn w-100 text-decoration-none justify-content-center" style="height: 32px !important;">
                    <i class="bi bi-x-circle me-1"></i> Clear Filters
                </button>
            </div>
        </div>
    </div>

    <!-- Company Payroll KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <!-- Total Expected Gross Payroll -->
        <div class="col-md-4">
            <div class="spotify-card p-3 h-100 stat-card-glow-blue">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-white opacity-50 small text-uppercase fw-semibold" style="letter-spacing: 0.05em; font-size: 0.72rem;">Total Gross Payroll</span>
                        <h4 class="text-white fw-bold mb-0 mt-1" id="stat-total-base">PKR {{ number_format($total_base_payroll, 2) }}</h4>
                    </div>
                    <div class="icon-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; border-radius: 50%; background-color: rgba(59, 130, 246, 0.15);">
                        <i class="bi bi-cash-stack text-primary fs-5"></i>
                    </div>
                </div>
                <div class="text-white opacity-40 small mt-2" style="font-size: 0.72rem;">
                    Base salaries for <span id="stat-employee-count" class="text-white fw-semibold">{{ $employee_count }}</span> active employees
                </div>
            </div>
        </div>

        <!-- Total Monthly Deductions -->
        <div class="col-md-4">
            <div class="spotify-card p-3 h-100 stat-card-glow-red">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-white opacity-50 small text-uppercase fw-semibold" style="letter-spacing: 0.05em; font-size: 0.72rem;">Total Deductions</span>
                        <h4 class="text-danger fw-bold mb-0 mt-1" id="stat-total-deductions">PKR {{ number_format($total_deductions, 2) }}</h4>
                    </div>
                    <div class="icon-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; border-radius: 50%; background-color: rgba(239, 68, 68, 0.15);">
                        <i class="bi bi-dash-circle text-danger fs-5"></i>
                    </div>
                </div>
                <div class="text-white opacity-40 small mt-2" style="font-size: 0.72rem;">
                    Deducted for unapproved absents and unpaid leaves
                </div>
            </div>
        </div>

        <!-- Total Estimated Net Company Expense -->
        <div class="col-md-4">
            <div class="spotify-card p-3 h-100 stat-card-glow-green">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-white opacity-50 small text-uppercase fw-semibold" style="letter-spacing: 0.05em; font-size: 0.72rem;">Total Estimated Net Payout</span>
                        <h4 class="text-success fw-bold mb-0 mt-1" id="stat-total-net">PKR {{ number_format($total_net_payroll, 2) }}</h4>
                    </div>
                    <div class="icon-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; border-radius: 50%; background-color: rgba(16, 185, 129, 0.15);">
                        <i class="bi bi-check2-all text-success fs-5"></i>
                    </div>
                </div>
                <div class="text-white opacity-40 small mt-2" style="font-size: 0.72rem;">
                    Total net payroll amount to be disbursed
                </div>
            </div>
        </div>
    </div>

    <!-- All Employees Final Salary Sheet (Continuous Table) -->
    <div class="spotify-card p-4 mb-4">
        <div class="mb-3">
            <h6 class="text-uppercase text-white fw-bold mb-0 opacity-75 small" style="letter-spacing: 0.05em;">
                All Employees Final Salary Sheet
            </h6>
        </div>

        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle salary-table mb-0" id="salaryEstimationTable" style="border-collapse: separate; border-spacing: 0 4px;">
                <thead>
                    <tr class="text-white opacity-50 small" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                        <th class="ps-3 py-3">Employee</th>
                        <th class="py-3 text-center" style="min-width: 200px;">Base Gross</th>
                        <th class="py-3 text-center" style="min-width: 190px;">Sales Commission</th>
                        <th class="py-3 text-center">Attendance Summary</th>
                        <th class="py-3 text-end">Deductions</th>
                        <th class="py-3 text-start ps-4">Net Salary (Estimated)</th>
                        <th class="pe-3 py-3 text-center" style="width: 140px;">Action</th>
                    </tr>
                </thead>
                <tbody id="salary-table-body">
                    <!-- Populated dynamically via JS / initially rendered -->
                </tbody>
                <tfoot id="salary-table-footer">
                    <tr style="border-top: 2px solid rgba(255, 255, 255, 0.15); background-color: rgba(255, 255, 255, 0.02);">
                        <td class="ps-3 py-3 text-uppercase text-white fw-bold small" style="letter-spacing: 0.05em;">
                            Grand Total Summary
                        </td>
                        <td class="py-3 text-center text-white fw-bold small" id="footer-total-base">
                            PKR {{ number_format($total_base_payroll, 2) }}
                        </td>
                        <td class="py-3 text-center text-white fw-bold small" id="footer-total-commission">
                            PKR {{ number_format($total_commissions, 2) }}
                        </td>
                        <td class="py-3 text-center text-white opacity-40 small">
                            —
                        </td>
                        <td class="py-3 text-end text-danger fw-bold small" id="footer-total-deductions">
                            - PKR {{ number_format($total_deductions, 2) }}
                        </td>
                        <td class="py-3 text-start ps-4 text-success fw-bold fs-6" id="footer-total-net">
                            PKR {{ number_format($total_net_payroll, 2) }}
                        </td>
                        <td class="pe-3 py-3 text-center text-white opacity-40 small">
                            —
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    @push('modals')
    <!-- Salary Deduction Rules Popup Modal -->
    <div class="modal fade" id="deductionRulesModal" tabindex="-1" aria-labelledby="deductionRulesModalLabel" aria-hidden="true" style="z-index: 1055;">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg" style="background-color: #0c0c0c !important; border: 1px solid rgba(255, 255, 255, 0.1) !important; border-radius: 16px !important;">
                <div class="modal-header border-bottom border-secondary px-4 py-3">
                    <h5 class="modal-title text-white fw-bold fs-6 mb-0" id="deductionRulesModalLabel">
                        Company Salary Deduction Rule Configuration
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Active Rule Banner -->
                    <div class="mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2" 
                         style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 50px !important; padding: 10px 24px !important;">
                        <div>
                            <div class="small text-white opacity-50 mb-0" style="font-size: 0.75rem;">Active Model:</div>
                            <div class="fw-bold text-white small" style="font-size: 0.85rem;">
                                @if(($settings['rule'] ?? 'calendar_days') === 'working_days')
                                    <i class="bi bi-calendar-range text-info me-1"></i>Working Days Model (Mon-Fri)
                                @elseif(($settings['rule'] ?? 'calendar_days') === 'fixed_30')
                                    <i class="bi bi-calendar-check text-warning me-1"></i>Fixed 30 Days Model
                                @else
                                    <i class="bi bi-calendar3 text-primary me-1"></i>Calendar Days Model (28-31 Days)
                                @endif
                            </div>
                        </div>
                        <div>
                            @if(isset($settings['effective_month']))
                                <span class="badge text-white fw-normal" style="font-size: 0.72rem; background-color: rgba(255, 255, 255, 0.08) !important; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 50px !important; padding: 5px 12px !important;">
                                    Effective: {{ date('M Y', strtotime($settings['effective_month'])) }}
                                </span>
                            @else
                                <span class="badge text-white fw-normal" style="font-size: 0.72rem; background-color: rgba(255, 255, 255, 0.08) !important; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 50px !important; padding: 5px 12px !important;">
                                    Global Policy
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Rule Setting Form -->
                    <form id="deductionRuleForm" action="{{ route('super-admin.attendance-holidays.settings.update') }}" method="POST">
                        @csrf
                        <input type="hidden" name="recalculate_all" id="recalculateAllInput" value="1">
                        <input type="hidden" name="rule" id="ruleInput" value="{{ $settings['rule'] ?? 'calendar_days' }}">
                        
                        <label class="form-label small text-white opacity-70 mb-2">Select Deduction Rule Model</label>
                        
                        <!-- Choice cards layout -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <div class="card p-3 text-center option-choice-card @if(($settings['rule'] ?? 'calendar_days') === 'calendar_days') active @else opacity-50 @endif" 
                                     id="choiceCalendar" 
                                     data-custom-tooltip="Calculates daily salary rate based on the total number of days in the current month." 
                                     data-tooltip-placement="top"
                                     style="background-color: rgba(255, 255, 255, 0.02); border-radius: 50px !important; min-height: 90px;">
                                    <span class="text-white fw-bold d-block mb-1 small">Calendar Days</span>
                                    <span class="text-white opacity-60 small d-block" style="font-size: 0.72rem; line-height: 1.25;">
                                         Total days in month (28-31).
                                     </span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card p-3 text-center option-choice-card @if(($settings['rule'] ?? 'calendar_days') === 'working_days') active @else opacity-50 @endif" 
                                     id="choiceWorking" 
                                     data-custom-tooltip="Calculates daily salary rate strictly based on working days (Monday to Friday) in the current month." 
                                     data-tooltip-placement="top"
                                     style="background-color: rgba(255, 255, 255, 0.02); border-radius: 50px !important; min-height: 90px;">
                                    <span class="text-white fw-bold d-block mb-1 small">Working Days</span>
                                    <span class="text-white opacity-60 small d-block" style="font-size: 0.72rem; line-height: 1.25;">
                                         Mon-Fri working days only.
                                     </span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card p-3 text-center option-choice-card @if(($settings['rule'] ?? 'calendar_days') === 'fixed_30') active @else opacity-50 @endif" 
                                     id="choiceFixed30" 
                                     data-custom-tooltip="Calculates daily salary rate based on a fixed 30-day month division." 
                                     data-tooltip-placement="top"
                                     style="background-color: rgba(255, 255, 255, 0.02); border-radius: 50px !important; min-height: 90px;">
                                    <span class="text-white fw-bold d-block mb-1 small">Fixed 30 Days</span>
                                    <span class="text-white opacity-60 small d-block" style="font-size: 0.72rem; line-height: 1.25;">
                                         Locked 30-day division.
                                     </span>
                                </div>
                            </div>
                        </div>

                        <!-- Scope selection layout -->
                        <div class="mb-4">
                            <label class="form-label small text-white opacity-70 mb-2">Recalculation Scope</label>
                            <div class="row g-3 mb-2">
                                <div class="col-md-6">
                                    <div class="card p-3 text-center option-choice-card @if(($settings['recalculate_all'] ?? true) == true) active @else opacity-50 @endif" 
                                         id="scopeRetroactive" 
                                         data-custom-tooltip="Recalculates past salary deductions retroactively based on the updated rule." 
                                         data-tooltip-placement="top"
                                         style="background-color: rgba(255, 255, 255, 0.02); border-radius: 50px !important;">
                                        <span class="text-white small fw-bold">Retroactive (All Records)</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card p-3 text-center option-choice-card @if(($settings['recalculate_all'] ?? true) == false) active @else opacity-50 @endif" 
                                         id="scopeCurrentMonth" 
                                         data-custom-tooltip="Applies the updated calculation rule to salary deductions from the current month onwards." 
                                         data-tooltip-placement="top"
                                         style="background-color: rgba(255, 255, 255, 0.02); border-radius: 50px !important;">
                                        <span class="text-white small fw-bold">From Current Month Onward</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Actions -->
                        <div class="d-flex justify-content-end gap-2 pt-2 border-top border-secondary">
                            <button type="button" class="row-btn px-4" data-bs-dismiss="modal" style="height: 36px !important; border-radius: 50px !important;">
                                Cancel
                            </button>
                            <button type="submit" class="row-btn-blue px-4" style="height: 36px !important; border-radius: 50px !important;">
                                <i class="bi bi-check2-circle me-1"></i> Save & Apply Rule
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endpush

    <!-- JavaScript Interactions for Rules Modal & AJAX Updates -->
    @push('scripts')
    <script>
        $(document).ready(function() {
            // Initial raw data from server
            let allEmployeesData = @json($employees);

            // -------------------------------------------------------------
            // DEDUCTION RULE MODAL CARD INTERACTIONS
            // -------------------------------------------------------------
            $('#choiceCalendar').on('click', function() {
                $('.option-choice-card[id^="choice"]').removeClass('active').addClass('opacity-50');
                $(this).addClass('active').removeClass('opacity-50');
                $('#ruleInput').val('calendar_days');
            });

            $('#choiceWorking').on('click', function() {
                $('.option-choice-card[id^="choice"]').removeClass('active').addClass('opacity-50');
                $(this).addClass('active').removeClass('opacity-50');
                $('#ruleInput').val('working_days');
            });

            $('#choiceFixed30').on('click', function() {
                $('.option-choice-card[id^="choice"]').removeClass('active').addClass('opacity-50');
                $(this).addClass('active').removeClass('opacity-50');
                $('#ruleInput').val('fixed_30');
            });

            $('#scopeRetroactive').on('click', function() {
                $('.option-choice-card[id^="scope"]').removeClass('active').addClass('opacity-50');
                $(this).addClass('active').removeClass('opacity-50');
                $('#recalculateAllInput').val('1');
            });

            $('#scopeCurrentMonth').on('click', function() {
                $('.option-choice-card[id^="scope"]').removeClass('active').addClass('opacity-50');
                $(this).addClass('active').removeClass('opacity-50');
                $('#recalculateAllInput').val('0');
            });

            // -------------------------------------------------------------
            // CONTINUOUS TABLE RENDERING (ALL EMPLOYEES IN ONE LIST)
            // -------------------------------------------------------------
            function formatNumber(num) {
                return parseFloat(num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function renderTable() {
                if (allEmployeesData && allEmployeesData.length > 0) {
                    let rowsHtml = '';
                    allEmployeesData.forEach((emp) => {
                        const initial = emp.name ? emp.name.charAt(0).toUpperCase() : 'E';
                        const baseFormatted = emp.has_expected_salary 
                            ? `PKR ${formatNumber(emp.base_salary)}` 
                            : '<span class="text-white opacity-40 small">Not Configured</span>';

                        const commFormatted = (emp.sales_commission !== null && emp.sales_commission !== undefined && emp.sales_commission > 0)
                            ? `PKR ${formatNumber(emp.sales_commission)}`
                            : '<span class="text-white opacity-40 small commission-text">PKR 0.00</span>';

                        const dedFormatted = emp.deductions > 0 
                            ? `<span class="text-danger fw-semibold small">- PKR ${formatNumber(emp.deductions)}</span>` 
                            : '<span class="text-white opacity-40 small">PKR 0.00</span>';
                        const netFormatted = `PKR ${formatNumber(emp.net_salary)}`;

                        let joinBadge = '';
                        if (emp.days_before_join && emp.days_before_join > 0) {
                            joinBadge = `<span class="badge fw-normal" style="font-size: 0.68rem; background-color: rgba(168, 85, 247, 0.12); color: #c084fc;" data-custom-tooltip="Pre-Joining Unworked Days" data-tooltip-placement="top">${emp.days_before_join} J</span>`;
                        }

                        // All badges visible
                        const attendanceBadges = `
                            <span class="badge bg-opacity-10 bg-success text-success fw-normal" style="font-size: 0.68rem;" data-custom-tooltip="Working Days Worked" data-tooltip-placement="top">${emp.working_days || 0} W</span>
                            <span class="badge bg-opacity-10 bg-info text-info fw-normal" style="font-size: 0.68rem;" data-custom-tooltip="Approved Leaves" data-tooltip-placement="top">${emp.leaves || 0} L</span>
                            <span class="badge bg-opacity-10 bg-danger text-danger fw-normal" style="font-size: 0.68rem;" data-custom-tooltip="Absents" data-tooltip-placement="top">${emp.absents || 0} A</span>
                            <span class="badge bg-opacity-10 bg-warning text-warning fw-normal" style="font-size: 0.68rem;" data-custom-tooltip="Unpaid Leaves" data-tooltip-placement="top">${emp.unpaid_leaves || 0} U</span>
                            <span class="badge fw-normal" style="font-size: 0.68rem; background-color: rgba(59, 130, 246, 0.12); color: #60a5fa;" data-custom-tooltip="Remote Work Days" data-tooltip-placement="top">${Number(emp.remotes || 0)} R</span>
                            ${joinBadge}
                        `;

                        rowsHtml += `
                            <tr class="employee-row" data-user-id="${emp.user_id}">
                                <td class="ps-3">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle me-2 flex-shrink-0">
                                            ${initial}
                                        </div>
                                        <div>
                                            <div class="text-white fw-semibold small">${emp.name}</div>
                                            <div class="text-white opacity-40 small" style="font-size: 0.72rem;">
                                                ${emp.designation} • <span class="opacity-75">${emp.department}</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center gross-salary-cell" data-user-id="${emp.user_id}" data-salary="${emp.has_expected_salary ? emp.base_salary : ''}">
                                    <div class="gross-display position-relative d-inline-flex align-items-center justify-content-center" style="min-height: 28px;">
                                        <span class="text-white fw-semibold small gross-text">${baseFormatted}</span>
                                        <button type="button" class="btn-circle-edit position-absolute" style="left: calc(100% + 8px); top: 50%; transform: translateY(-50%);" data-custom-tooltip="Edit" data-tooltip-placement="top">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </div>
                                    <div class="gross-edit-form d-none align-items-center justify-content-center gap-2">
                                        <input type="text" inputmode="numeric" autocomplete="off" class="form-control form-control-sm gross-input py-0 px-2" value="${emp.has_expected_salary ? Math.round(emp.base_salary) : ''}" placeholder="0" maxlength="11">
                                        <button type="button" class="btn-circle-blue btn-save-gross" data-custom-tooltip="Save" data-tooltip-placement="top">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                        <button type="button" class="btn-circle-red-filled btn-cancel-gross" data-custom-tooltip="Cancel" data-tooltip-placement="top">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="text-center commission-salary-cell" data-user-id="${emp.user_id}" data-commission="${emp.sales_commission !== null && emp.sales_commission !== undefined ? emp.sales_commission : ''}">
                                    <div class="commission-display position-relative d-inline-flex align-items-center justify-content-center" style="min-height: 28px;">
                                        <span class="text-white fw-semibold small commission-text">${commFormatted}</span>
                                        <button type="button" class="btn-circle-edit position-absolute" style="left: calc(100% + 8px); top: 50%; transform: translateY(-50%);" data-custom-tooltip="Edit" data-tooltip-placement="top">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </div>
                                    <div class="commission-edit-form d-none align-items-center justify-content-center gap-2">
                                        <input type="text" inputmode="numeric" autocomplete="off" class="form-control form-control-sm gross-input commission-input py-0 px-2" value="${emp.sales_commission !== null && emp.sales_commission !== undefined ? Math.round(emp.sales_commission) : ''}" placeholder="0" maxlength="11">
                                        <button type="button" class="btn-circle-blue btn-save-commission" data-custom-tooltip="Save" data-tooltip-placement="top">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                        <button type="button" class="btn-circle-red-filled btn-cancel-commission" data-custom-tooltip="Cancel" data-tooltip-placement="top">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        ${attendanceBadges}
                                    </div>
                                </td>
                                <td class="text-end">
                                    ${dedFormatted}
                                </td>
                                <td class="text-start ps-4">
                                    <span class="text-success fw-bold small fs-6">
                                        ${netFormatted}
                                    </span>
                                </td>
                                <td class="pe-3 text-center">
                                    <a href="${emp.salary_slip_url}" class="row-btn-outline-blue d-inline-flex align-items-center justify-content-center text-decoration-none" style="height: 28px !important; font-size: 0.72rem !important; padding: 0 0.75rem !important;">
                                        <i class="bi bi-receipt me-1"></i> Generate Slip
                                    </a>
                                </td>
                            </tr>
                        `;
                    });
                    $('#salary-table-body').html(rowsHtml);
                    $('#salary-table-footer').removeClass('d-none');
                } else {
                    $('#salary-table-body').html(`
                        <tr>
                            <td colspan="7" class="text-center py-4 text-white opacity-50">
                                No employees found for the selected filters.
                            </td>
                        </tr>
                    `);
                    $('#salary-table-footer').addClass('d-none');
                }
            }

            // Initial Table Render
            renderTable();

            // -------------------------------------------------------------
            // INLINE GROSS PAY & SALES COMMISSION EDITING (CIRCULAR EDIT BUTTON & ACTION BUTTONS)
            // -------------------------------------------------------------
            function openGrossEditor($cell) {
                closeAllGrossEditors();
                closeAllCommissionEditors();
                $('.custom-sidebar-tooltip').removeClass('show');

                $cell.find('.gross-display').addClass('d-none').removeClass('d-inline-flex');
                const $editForm = $cell.find('.gross-edit-form');
                $editForm.removeClass('d-none').addClass('d-inline-flex');
                
                const $input = $editForm.find('.gross-input');
                $input.focus().select();
            }

            function closeAllGrossEditors() {
                $('.gross-salary-cell').each(function() {
                    const $c = $(this);
                    $c.find('.gross-edit-form').addClass('d-none').removeClass('d-inline-flex');
                    $c.find('.gross-display').removeClass('d-none').addClass('d-inline-flex');
                });
            }

            function openCommissionEditor($cell) {
                closeAllGrossEditors();
                closeAllCommissionEditors();
                $('.custom-sidebar-tooltip').removeClass('show');

                $cell.find('.commission-display').addClass('d-none').removeClass('d-inline-flex');
                const $editForm = $cell.find('.commission-edit-form');
                $editForm.removeClass('d-none').addClass('d-inline-flex');
                
                const $input = $editForm.find('.commission-input');
                $input.focus().select();
            }

            function closeAllCommissionEditors() {
                $('.commission-salary-cell').each(function() {
                    const $c = $(this);
                    $c.find('.commission-edit-form').addClass('d-none').removeClass('d-inline-flex');
                    $c.find('.commission-display').removeClass('d-none').addClass('d-inline-flex');
                });
            }

            function triggerTopSnackbar(message, type = 'success') {
                if (typeof window.showToast === 'function') {
                    window.showToast(message, type);
                } else {
                    alert(message);
                }
            }

            // Click on circular edit pencil button or amount display opens inline editor (Gross)
            $(document).on('click', '.gross-salary-cell .btn-circle-edit, .gross-display', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('.custom-sidebar-tooltip').removeClass('show');
                const $cell = $(this).closest('.gross-salary-cell');
                openGrossEditor($cell);
            });

            // Cancel button (Gross)
            $(document).on('click', '.btn-cancel-gross', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('.custom-sidebar-tooltip').removeClass('show');
                const $cell = $(this).closest('.gross-salary-cell');
                const origVal = $cell.attr('data-salary');
                $cell.find('.gross-input').val(origVal !== '' ? Math.round(origVal) : '');
                $cell.find('.gross-edit-form').addClass('d-none').removeClass('d-inline-flex');
                $cell.find('.gross-display').removeClass('d-none').addClass('d-inline-flex');
            });

            // Save button (Gross)
            $(document).on('click', '.btn-save-gross', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('.custom-sidebar-tooltip').removeClass('show');
                const $cell = $(this).closest('.gross-salary-cell');
                submitGrossUpdate($cell);
            });

            // Keyboard navigation (Enter to save, Esc to cancel) (Gross)
            $(document).on('keydown', '.gross-input:not(.commission-input)', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $('.custom-sidebar-tooltip').removeClass('show');
                    const $cell = $(this).closest('.gross-salary-cell');
                    submitGrossUpdate($cell);
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    $('.custom-sidebar-tooltip').removeClass('show');
                    const $cell = $(this).closest('.gross-salary-cell');
                    $cell.find('.btn-cancel-gross').trigger('click');
                }
            });

            // Real-time validation for gross salary input (reject negative, reject leading zeros like 001122)
            $(document).on('input', '.gross-input:not(.commission-input)', function() {
                const $input = $(this);
                const val = $input.val().trim();
                if (val !== '' && !/^(0|[1-9]\d*)$/.test(val)) {
                    $input.addClass('is-invalid');
                } else {
                    $input.removeClass('is-invalid');
                }
            });

            // Click on circular edit pencil button or amount display opens inline editor (Commission)
            $(document).on('click', '.commission-salary-cell .btn-circle-edit, .commission-display', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('.custom-sidebar-tooltip').removeClass('show');
                const $cell = $(this).closest('.commission-salary-cell');
                openCommissionEditor($cell);
            });

            // Cancel button (Commission)
            $(document).on('click', '.btn-cancel-commission', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('.custom-sidebar-tooltip').removeClass('show');
                const $cell = $(this).closest('.commission-salary-cell');
                const origVal = $cell.attr('data-commission');
                $cell.find('.commission-input').val(origVal !== '' ? Math.round(origVal) : '');
                $cell.find('.commission-edit-form').addClass('d-none').removeClass('d-inline-flex');
                $cell.find('.commission-display').removeClass('d-none').addClass('d-inline-flex');
            });

            // Save button (Commission)
            $(document).on('click', '.btn-save-commission', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $('.custom-sidebar-tooltip').removeClass('show');
                const $cell = $(this).closest('.commission-salary-cell');
                submitCommissionUpdate($cell);
            });

            // Keyboard navigation (Enter to save, Esc to cancel) (Commission)
            $(document).on('keydown', '.commission-input', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $('.custom-sidebar-tooltip').removeClass('show');
                    const $cell = $(this).closest('.commission-salary-cell');
                    submitCommissionUpdate($cell);
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    $('.custom-sidebar-tooltip').removeClass('show');
                    const $cell = $(this).closest('.commission-salary-cell');
                    $cell.find('.btn-cancel-commission').trigger('click');
                }
            });

            // Real-time validation for commission input (reject negative, reject leading zeros like 001122)
            $(document).on('input', '.commission-input', function() {
                const $input = $(this);
                const val = $input.val().trim();
                if (val !== '' && !/^(0|[1-9]\d*)$/.test(val)) {
                    $input.addClass('is-invalid');
                } else {
                    $input.removeClass('is-invalid');
                }
            });

            function submitGrossUpdate($cell) {
                const userId = $cell.attr('data-user-id');
                const $input = $cell.find('.gross-input:not(.commission-input)');
                const newVal = $input.val().trim();
                const $saveBtn = $cell.find('.btn-save-gross');
                const $cancelBtn = $cell.find('.btn-cancel-gross');

                if (newVal !== '' && !/^(0|[1-9]\d*)$/.test(newVal)) {
                    $input.addClass('is-invalid');
                    triggerTopSnackbar('Invalid salary: Amount cannot be negative and cannot have leading zeros (e.g. 001122).', 'danger');
                    $input.focus().select();
                    return;
                }

                const month = $('#filter-month').val();
                const year = $('#filter-year').val();
                const filterUserId = $('#filter-employee').val();
                const salaryStatus = $('#filter-salary-status').val();

                // Loading state
                $saveBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" style="width: 12px; height: 12px;"></span>');
                $cancelBtn.prop('disabled', true);
                $input.prop('disabled', true);

                $.ajax({
                    url: "{{ route('super-admin.salary.estimations.update-salary') }}",
                    method: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        user_id: userId,
                        expected_salary_amount: newVal === '' ? null : newVal,
                        month: month,
                        year: year,
                        filter_user_id: filterUserId,
                        salary_status: salaryStatus
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            const d = response.data;
                            const emp = response.employee;

                            // Update KPI cards
                            $('#stat-total-base').text(`PKR ${formatNumber(d.total_base_payroll)}`);
                            $('#stat-total-deductions').text(`PKR ${formatNumber(d.total_deductions)}`);
                            $('#stat-total-net').text(`PKR ${formatNumber(d.total_net_payroll)}`);
                            $('#stat-employee-count').text(d.employee_count);

                            // Update table footer totals
                            $('#footer-total-base').text(`PKR ${formatNumber(d.total_base_payroll)}`);
                            $('#footer-total-commission').text(`PKR ${formatNumber(d.total_commissions)}`);
                            $('#footer-total-deductions').text(`- PKR ${formatNumber(d.total_deductions)}`);
                            $('#footer-total-net').text(`PKR ${formatNumber(d.total_net_payroll)}`);

                            // Update dataset and re-render
                            allEmployeesData = d.employees || [];
                            renderTable();

                            // Apply smooth green highlight animation to updated row
                            const $updatedRow = $(`tr.employee-row[data-user-id="${userId}"]`);
                            if ($updatedRow.length) {
                                $updatedRow.addClass('row-highlight-success');
                                setTimeout(() => {
                                    $updatedRow.removeClass('row-highlight-success');
                                }, 2600);
                            }

                            // Show top custom snackbar confirmation
                            triggerTopSnackbar(`Updated gross salary for ${emp ? emp.name : 'Employee'}. Deductions & net payout recalculated.`, 'success');
                        } else {
                            triggerTopSnackbar(response.message || 'Failed to update salary.', 'danger');
                            $saveBtn.prop('disabled', false).html('<i class="bi bi-check-lg"></i>');
                            $cancelBtn.prop('disabled', false);
                            $input.prop('disabled', false);
                        }
                    },
                    error: function(xhr) {
                        let msg = 'Failed to update gross salary. Please try again.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        triggerTopSnackbar(msg, 'danger');
                        $saveBtn.prop('disabled', false).html('<i class="bi bi-check-lg"></i>');
                        $cancelBtn.prop('disabled', false);
                        $input.prop('disabled', false);
                    }
                });
            }

            function submitCommissionUpdate($cell) {
                const userId = $cell.attr('data-user-id');
                const $input = $cell.find('.commission-input');
                const newVal = $input.val().trim();
                const $saveBtn = $cell.find('.btn-save-commission');
                const $cancelBtn = $cell.find('.btn-cancel-commission');

                if (newVal !== '' && !/^(0|[1-9]\d*)$/.test(newVal)) {
                    $input.addClass('is-invalid');
                    triggerTopSnackbar('Invalid commission: Amount cannot be negative and cannot have leading zeros (e.g. 001122).', 'danger');
                    $input.focus().select();
                    return;
                }

                const month = $('#filter-month').val();
                const year = $('#filter-year').val();
                const filterUserId = $('#filter-employee').val();
                const salaryStatus = $('#filter-salary-status').val();

                // Loading state
                $saveBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" style="width: 12px; height: 12px;"></span>');
                $cancelBtn.prop('disabled', true);
                $input.prop('disabled', true);

                $.ajax({
                    url: "{{ route('super-admin.salary.estimations.update-commission') }}",
                    method: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        user_id: userId,
                        sales_commission: newVal === '' ? null : newVal,
                        month: month,
                        year: year,
                        filter_user_id: filterUserId,
                        salary_status: salaryStatus
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            const d = response.data;
                            const emp = response.employee;

                            // Update KPI cards
                            $('#stat-total-base').text(`PKR ${formatNumber(d.total_base_payroll)}`);
                            $('#stat-total-deductions').text(`PKR ${formatNumber(d.total_deductions)}`);
                            $('#stat-total-net').text(`PKR ${formatNumber(d.total_net_payroll)}`);
                            $('#stat-employee-count').text(d.employee_count);

                            // Update table footer totals
                            $('#footer-total-base').text(`PKR ${formatNumber(d.total_base_payroll)}`);
                            $('#footer-total-commission').text(`PKR ${formatNumber(d.total_commissions)}`);
                            $('#footer-total-deductions').text(`- PKR ${formatNumber(d.total_deductions)}`);
                            $('#footer-total-net').text(`PKR ${formatNumber(d.total_net_payroll)}`);

                            // Update dataset and re-render
                            allEmployeesData = d.employees || [];
                            renderTable();

                            // Apply smooth green highlight animation to updated row
                            const $updatedRow = $(`tr.employee-row[data-user-id="${userId}"]`);
                            if ($updatedRow.length) {
                                $updatedRow.addClass('row-highlight-success');
                                setTimeout(() => {
                                    $updatedRow.removeClass('row-highlight-success');
                                }, 2600);
                            }

                            // Show top custom snackbar confirmation
                            triggerTopSnackbar(`Updated sales commission for ${emp ? emp.name : 'Employee'}. Deductions & net payout recalculated.`, 'success');
                        } else {
                            triggerTopSnackbar(response.message || 'Failed to update commission.', 'danger');
                            $saveBtn.prop('disabled', false).html('<i class="bi bi-check-lg"></i>');
                            $cancelBtn.prop('disabled', false);
                            $input.prop('disabled', false);
                        }
                    },
                    error: function(xhr) {
                        let msg = 'Failed to update sales commission. Please try again.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        triggerTopSnackbar(msg, 'danger');
                        $saveBtn.prop('disabled', false).html('<i class="bi bi-check-lg"></i>');
                        $cancelBtn.prop('disabled', false);
                        $input.prop('disabled', false);
                    }
                });
            }

            // -------------------------------------------------------------
            // MONTH / YEAR / EMPLOYEE / SALARY STATUS DYNAMIC AJAX
            // -------------------------------------------------------------
            function fetchSalaryEstimations() {
                const month = $('#filter-month').val();
                const year = $('#filter-year').val();
                const userId = $('#filter-employee').val();
                const salaryStatus = $('#filter-salary-status').val();

                if (!month || !year) return;

                const monthName = $('#filter-month option:selected').text().trim();

                // HIDE the footer summary while loading
                $('#salary-table-footer').addClass('d-none');

                // Show loading spinner row
                $('#salary-table-body').html(`
                    <tr>
                        <td colspan="7" class="text-center py-5 text-white opacity-50">
                            <span class="spinner-border spinner-border-sm me-2"></span> Calculating payroll estimations for ${monthName} ${year}...
                        </td>
                    </tr>
                `);

                $.ajax({
                    url: "{{ route('super-admin.salary.estimations.api') }}",
                    method: "GET",
                    data: { 
                        month: month, 
                        year: year, 
                        user_id: userId,
                        salary_status: salaryStatus
                    },
                    success: function(response) {
                        if (response.status === 'success' && response.data) {
                            const d = response.data;
                            
                            // Update KPIs
                            $('#stat-total-base').text(`PKR ${formatNumber(d.total_base_payroll)}`);
                            $('#stat-total-deductions').text(`PKR ${formatNumber(d.total_deductions)}`);
                            $('#stat-total-net').text(`PKR ${formatNumber(d.total_net_payroll)}`);
                            $('#stat-employee-count').text(d.employee_count);

                            // Update Footer totals
                            $('#footer-total-base').text(`PKR ${formatNumber(d.total_base_payroll)}`);
                            $('#footer-total-commission').text(`PKR ${formatNumber(d.total_commissions)}`);
                            $('#footer-total-deductions').text(`- PKR ${formatNumber(d.total_deductions)}`);
                            $('#footer-total-net').text(`PKR ${formatNumber(d.total_net_payroll)}`);

                            // Update dataset and re-render
                            allEmployeesData = d.employees || [];
                            renderTable();
                        }
                    },
                    error: function() {
                        $('#salary-table-body').html(`
                            <tr>
                                <td colspan="7" class="text-center py-4 text-danger small">
                                    Failed to calculate payroll estimations. Please try again.
                                </td>
                            </tr>
                        `);
                        $('#salary-table-footer').addClass('d-none');
                    }
                });
            }

            // Bind change events to all filter controls
            $('#filter-employee, #filter-salary-status, #filter-month, #filter-year').on('change', function() {
                fetchSalaryEstimations();
            });

            // Clear Filters button
            $('#btn-clear-filters').on('click', function() {
                const now = new Date();
                $('#filter-employee').val('');
                $('#filter-salary-status').val('all');
                $('#filter-month').val(now.getMonth() + 1);
                $('#filter-year').val(now.getFullYear());
                fetchSalaryEstimations();
            });

            // Export to Excel
            $('#btn-export-excel').on('click', function() {
                const month = $('#filter-month').val();
                const year = $('#filter-year').val();
                const userId = $('#filter-employee').val();
                const salaryStatus = $('#filter-salary-status').val();

                const url = new URL("{{ route('super-admin.salary.estimations.export') }}", window.location.origin);
                if (month) url.searchParams.append('month', month);
                if (year) url.searchParams.append('year', year);
                if (userId) url.searchParams.append('user_id', userId);
                if (salaryStatus) url.searchParams.append('salary_status', salaryStatus);

                window.location.href = url.toString();
            });
        });
    </script>
    @endpush
</x-app-layout>
