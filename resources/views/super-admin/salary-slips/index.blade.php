<x-app-layout>
    <x-slot name="header">
        <div class="d-flex justify-content-between align-items-center w-100">
            <span>Salary Slip</span>
            <!-- Action Buttons and Employee Name Badge on top right header -->
            <div id="header-actions-wrapper" class="d-none d-flex align-items-center gap-2">
                <button type="button" class="row-btn-outline-blue" id="btn-preview-pdf" style="height: 28px !important; font-size: 0.72rem !important; padding: 0 0.8rem !important;">
                    <i class="bi bi-eye me-1"></i> Preview Salary Slip
                </button>
                <button type="button" class="row-btn-outline-green" id="btn-send-email" style="height: 28px !important; font-size: 0.72rem !important; padding: 0 0.8rem !important;">
                    <i class="bi bi-send me-1"></i> Send Salary Slip
                </button>
                <span id="header-name-badge" class="row-badge-warning" style="height: 28px !important; font-size: 0.72rem !important; padding: 0 0.8rem !important; width: auto !important; max-width: max-content !important; white-space: nowrap !important;">--</span>
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
        #filter-panel-card {
            position: relative !important;
            z-index: 9999 !important;
            overflow: visible !important;
        }
        #dropdown-pages-wrapper {
            position: relative !important;
            z-index: 10000 !important;
        }
        #dropdown-pages-wrapper .dropdown-menu {
            z-index: 10001 !important;
            position: absolute !important;
            top: 100% !important;
            left: 0 !important;
            margin-top: 6px !important;
            transform: none !important;
        }
        #placeholder-card,
        #main-content {
            position: relative !important;
            z-index: 1 !important;
        }
    </style>

    <!-- Filters Panel Card -->
    <div id="filter-panel-card" class="spotify-card p-4 mb-4 mt-3">
        <div class="row g-3 align-items-end">
            <!-- Employee Dropdown -->
            <div class="col-md-3">
                <label class="form-label text-white opacity-75 small fw-semibold">Employee</label>
                <select class="form-select form-select-sm text-white" id="filter-employee" style="height: 32px !important; border-radius: 50px !important; padding: 0 1rem !important; font-size: 0.85rem !important;">
                    <option value="">-- Select Employee --</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>{{ trim($user->name) }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Month Select -->
            <div class="col-md-2">
                <label class="form-label text-white opacity-75 small fw-semibold">Month</label>
                <select class="form-select form-select-sm text-white" id="filter-month" style="height: 32px !important; border-radius: 50px !important; padding: 0 1rem !important; font-size: 0.85rem !important;">
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ date('n') == $m ? 'selected' : '' }}>
                            {{ DateTime::createFromFormat('!m', $m)->format('F') }}
                        </option>
                    @endfor
                </select>
            </div>

            <!-- Year Select -->
            <div class="col-md-2">
                <label class="form-label text-white opacity-75 small fw-semibold">Year</label>
                <input type="number" class="form-control form-control-sm text-white" id="filter-year" value="{{ date('Y') }}" min="2020" max="2035" style="height: 32px !important; border-radius: 50px !important; padding: 0 1rem !important; font-size: 0.85rem !important; background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;">
            </div>

            <!-- Include Pages Checklist Dropdown (After Year) -->
            <div class="col-md-3" style="position: relative; z-index: 10000 !important;">
                <label class="form-label text-white opacity-75 small fw-semibold">Include Pages</label>
                <div class="dropdown custom-checklist-dropdown" id="dropdown-pages-wrapper">
                    <button class="form-select form-select-sm text-white text-start d-flex justify-content-between align-items-center w-100" type="button" id="btn-dropdown-pages" data-bs-toggle="dropdown" data-bs-display="static" data-bs-auto-close="outside" aria-expanded="false" style="height: 32px !important; border-radius: 50px !important; padding: 0 1rem !important; font-size: 0.85rem !important; background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;">
                        <span id="dropdown-pages-label" class="text-truncate me-2">Salary Slip, Track Sheet</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-dark shadow-lg p-2 border-0" aria-labelledby="btn-dropdown-pages" style="background-color: #1a2234; border: 1px solid rgba(255, 255, 255, 0.15) !important; border-radius: 12px; min-width: 100%; z-index: 10001 !important; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.6) !important;">
                        <li class="px-2 py-1">
                            <div class="form-check d-flex align-items-center gap-2 mb-0 cursor-pointer">
                                <input class="form-check-input page-select-chk cursor-pointer mt-0" type="checkbox" id="chk-page-salary-slip" value="salary_slip" checked style="cursor: pointer;">
                                <label class="form-check-label text-white small cursor-pointer flex-grow-1" for="chk-page-salary-slip" style="cursor: pointer;">
                                    Salary Slip
                                </label>
                            </div>
                        </li>
                        <li class="dropdown-divider border-secondary opacity-25 my-1"></li>
                        <li class="px-2 py-1">
                            <div class="form-check d-flex align-items-center gap-2 mb-0 cursor-pointer">
                                <input class="form-check-input page-select-chk cursor-pointer mt-0" type="checkbox" id="chk-page-tracksheet" value="tracksheet" checked style="cursor: pointer;">
                                <label class="form-check-label text-white small cursor-pointer flex-grow-1" for="chk-page-tracksheet" style="cursor: pointer;">
                                    Track Sheet
                                </label>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Clear Filter Button -->
            <div class="col-md-2">
                <button type="button" id="btn-clear-filters" class="row-btn w-100 text-decoration-none justify-content-center" style="height: 32px !important;">
                    <i class="bi bi-x-circle me-1"></i> Clear Filters
                </button>
            </div>
        </div>
    </div>

    <!-- Initial Placeholder State Card -->
    <div id="placeholder-card" class="spotify-card p-5 text-center mb-4">
        <div class="py-5">
            <div class="mb-4 text-muted">
                <i class="bi bi-receipt" style="font-size: 4rem;"></i>
            </div>
            <h5 class="fw-bold text-white mb-2">Salary Slip Generation</h5>
            <p class="text-white opacity-50 mx-auto" style="max-width: 500px;">
                Select an employee above to dynamically view work summary, configure persistent bank details, and generate salary slips.
            </p>
        </div>
    </div>

    <!-- Main Results Section (Hidden initially) -->
    <div id="main-content" class="d-none">

        <!-- DEDICATED ROW: PROFESSIONAL COMMUNICATION EMAIL -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="spotify-card p-4" id="recipient-email-card">
                    <div>
                        <div class="d-flex align-items-center mb-2">
                            <div class="icon-circle me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; border-radius: 50%; background-color: rgba(59, 130, 246, 0.1);">
                                <i class="bi bi-envelope-at text-primary"></i>
                            </div>
                            <h6 class="text-uppercase text-white fw-bold mb-0 opacity-75 small" style="letter-spacing: 0.05em;">Professional Communication Email</h6>
                            <i class="bi bi-info-circle text-white opacity-75 ms-2 cursor-pointer" data-custom-tooltip="Monthly salary slips and official payroll statements will be dispatched to this designated email address. If left blank, delivery automatically falls back to employee login email." data-tooltip-placement="top" style="font-size: 0.85rem;"></i>
                        </div>
                        <hr class="border-secondary my-2">
                    </div>

                    <div class="pt-2">
                        <div class="input-group">
                            <input type="email" id="salary_slip_email" class="form-control text-white" placeholder="Fallback: employee login email" style="height: 38px !important; border-radius: 50px 0 0 50px !important; padding: 0 1.1rem !important; font-size: 0.85rem !important; background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;">
                            <button type="button" id="btn-save-recipient-email" class="row-btn-blue d-flex align-items-center justify-content-center" style="height: 38px !important; border-radius: 0 50px 50px 0 !important; font-size: 0.82rem !important; padding: 0 1.25rem !important; white-space: nowrap;">
                                <i class="bi bi-check2-circle me-1"></i> Save Email
                            </button>
                        </div>
                        <div class="form-text text-white opacity-25 small mt-1 ps-2" style="font-size: 0.76rem;">
                            Changes saved here synchronize live across employee profile and HR views.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ROW 2: BANK DETAILS (LEFT) & LETTERHEAD UPLOAD IMAGE PREVIEW (RIGHT) -->
        <div class="row g-4 mb-4">
            <!-- Left Side: Bank Payment Details Card -->
            <div class="col-md-6">
                <div class="spotify-card p-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center mb-2">
                            <div class="icon-circle me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; border-radius: 50%; background-color: rgba(245, 158, 11, 0.1);">
                                <i class="bi bi-bank text-warning"></i>
                            </div>
                            <h6 class="text-uppercase text-white fw-bold mb-0 opacity-75 small" style="letter-spacing: 0.05em;">Bank Payment Details</h6>
                        </div>
                        <hr class="border-secondary my-2">
                        
                        <form id="bank-details-form" class="row g-3 mt-1">
                            <div class="col-12">
                                <label class="form-label text-white opacity-50 small mb-1">Bank Name *</label>
                                <input type="text" id="bank_name" class="form-control text-white" placeholder="e.g. NayaPay / HBL" style="height: 38px !important; border-radius: 50px !important; padding: 0 1rem !important; font-size: 0.85rem !important; background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;">
                            </div>
                            <div class="col-12">
                                <label class="form-label text-white opacity-50 small mb-1">Account Title *</label>
                                <input type="text" id="account_title" class="form-control text-white" placeholder="e.g. Abdul Hanan" style="height: 38px !important; border-radius: 50px !important; padding: 0 1rem !important; font-size: 0.85rem !important; background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;">
                            </div>
                            <div class="col-12">
                                <label class="form-label text-white opacity-50 small mb-1">Account Number</label>
                                <input type="text" id="account_no" class="form-control text-white" placeholder="Account No" style="height: 38px !important; border-radius: 50px !important; padding: 0 1rem !important; font-size: 0.85rem !important; background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;">
                            </div>
                            <div class="col-12">
                                <label class="form-label text-white opacity-50 small mb-1">IBAN *</label>
                                <input type="text" id="iban" class="form-control text-white" placeholder="e.g. PK08NAYA..." style="height: 38px !important; border-radius: 50px !important; padding: 0 1rem !important; font-size: 0.85rem !important; background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.15) !important;">
                            </div>
                        </form>
                    </div>

                    <div class="mt-4">
                        <button type="button" id="btn-save-bank-details" class="row-btn-outline-blue w-100 justify-content-center" style="height: 38px !important; border-radius: 50px !important; font-size: 0.85rem !important;">
                            <i class="bi bi-save me-1"></i> Save Bank Details
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Side: Salary Slip Letterhead Upload Preview Box Card -->
            <div class="col-md-6">
                <div class="spotify-card p-4 h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center mb-2">
                            <div class="icon-circle me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; border-radius: 50%; background-color: rgba(59, 130, 246, 0.1);">
                                <i class="bi bi-file-earmark-image text-primary"></i>
                            </div>
                            <h6 class="text-uppercase text-white fw-bold mb-0 opacity-75 small" style="letter-spacing: 0.05em;">
                                Salary Slip Letterhead
                                <i class="bi bi-info-circle text-white opacity-75 ms-2 cursor-pointer" data-custom-tooltip="Allowed Formats: PNG, JPG, JPEG&#10;Strictly A4 Size (210mm x 297mm)&#10;Max File Size: 2MB" data-tooltip-placement="top" style="font-size: 0.85rem;"></i>
                            </h6>
                        </div>
                        <hr class="border-secondary my-2">
                    </div>

                    <!-- Hidden File Input placed outside preview box to prevent event bubbling -->
                    <input type="file" id="letterhead_image" class="d-none" accept="image/png, image/jpeg, image/jpg">

                    <!-- Interactive Image Drag & Drop Preview Box with my-auto (Equal top & bottom spacing) -->
                    <div id="letterhead-preview-box" class="p-3 text-center cursor-pointer my-auto d-flex flex-column align-items-center justify-content-center" style="border: 2px dashed rgba(255, 255, 255, 0.2); border-radius: 12px; background-color: rgba(255, 255, 255, 0.02); height: 260px; transition: all 0.2s ease;">
                        <div id="preview-empty-state" class="py-2 {{ !empty($letterheadUrl) ? 'd-none' : '' }}">
                            <i class="bi bi-cloud-arrow-up text-primary mb-2" style="font-size: 2.5rem; display: block;"></i>
                            <h6 class="text-white fw-semibold mb-1" style="font-size: 0.9rem;">Click or Drag & Drop Image Here</h6>
                            <p class="text-white opacity-50 mb-0 small" style="font-size: 0.78rem;">Only A4 size images (PNG, JPG, JPEG up to 2MB)</p>
                        </div>
                        <div id="preview-active-state" class="{{ !empty($letterheadUrl) ? 'd-flex' : 'd-none' }} w-100 h-100 flex-column align-items-center justify-content-center">
                            <img id="letterhead-img-preview" src="{{ $letterheadUrl ?? '' }}" alt="Letterhead Preview" style="height: 185px; width: 130px; object-fit: contain; border-radius: 6px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.05); box-shadow: 0 4px 12px rgba(0,0,0,0.5);">
                            <div class="text-white opacity-75 small mt-2 fw-semibold" id="preview-filename" style="font-size: 0.76rem; max-width: 90%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">Saved Letterhead (A4)</div>
                        </div>
                    </div>

                    <div>
                        <button type="button" id="btn-upload-letterhead" class="row-btn-outline-blue w-100 justify-content-center" style="height: 38px !important; border-radius: 50px !important; font-size: 0.85rem !important;">
                            <i class="bi bi-upload me-1"></i> {{ !empty($letterheadUrl) ? 'Change A4 Letterhead Image' : 'Select A4 Letterhead Image' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ROW 3: WORK SUMMARY & FINANCIAL ESTIMATION (MATCHING TRACK RECORD DESIGN) -->
        <div id="summary-dashboard-card" class="spotify-card p-4 mb-4">
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
                        <div class="d-flex justify-content-between align-items-center profile-field-row">
                            <span class="text-white opacity-50 small">Unpaid Leaves</span>
                            <span id="sum-unpaid-leaves" class="text-white fw-semibold small text-end">0</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center profile-field-row">
                            <span class="text-white opacity-50 small">Company Holidays</span>
                            <span id="sum-holidays" class="text-white fw-semibold small text-end">0</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center profile-field-row">
                            <span class="text-white opacity-50 small">Remote Work</span>
                            <span id="sum-remotes" class="text-white fw-semibold small text-end">0</span>
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
                            <span class="text-white fw-semibold small text-end" id="fin-expected-salary-container"><span id="fin-expected-salary">—</span></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center profile-field-row">
                            <span class="text-white opacity-50 small">Basic Salary (70%)</span>
                            <span class="text-white fw-semibold small text-end" id="fin-basic-salary-container"><span id="fin-basic-salary">—</span></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center profile-field-row">
                            <span class="text-white opacity-50 small">House Rent / Travel Allowance (20%)</span>
                            <span class="text-white fw-semibold small text-end" id="fin-rent-travel-container"><span id="fin-rent-travel">—</span></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center profile-field-row">
                            <span class="text-white opacity-50 small">Medical Allowance (10%)</span>
                            <span class="text-white fw-semibold small text-end" id="fin-medical-container"><span id="fin-medical">—</span></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center profile-field-row">
                            <span class="text-white opacity-50 small">Sales Commission / Bonus</span>
                            <span class="text-success fw-semibold small text-end" id="fin-commission-container"><span id="fin-commission">Rs. 0</span></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center profile-field-row">
                            <span class="text-white opacity-50 small">Total Deductions</span>
                            <span class="text-danger fw-semibold small text-end" id="sum-deductions-container"><span id="sum-deductions">Rs. 0</span></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center profile-field-row">
                            <span class="text-white opacity-50 small">Gross Pay / Take Away</span>
                            <span class="text-success fw-bold small text-end" style="font-size: 1.05rem;" id="sum-take-away-container"><span id="sum-take-away">—</span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    @push('scripts')
    <script>
        $(document).ready(function() {
            let currentUserId = null;

            function showAlert(msg, isSuccess = false) {
                const toastClass = isSuccess ? 'sleek-toast-success' : 'sleek-toast-danger';
                const iconClass = isSuccess ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill';
                
                const toastHtml = `
                    <div class="sleek-toast ${toastClass}">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi ${iconClass}"></i>
                            <span class="small fw-semibold">${msg}</span>
                        </div>
                        <button type="button" class="btn-close ms-3" style="font-size: 0.75rem;" onclick="$(this).closest('.sleek-toast').remove()"></button>
                    </div>
                `;
                
                let container = $('.sleek-toast-container');
                if (container.length === 0) {
                    container = $('<div class="sleek-toast-container"></div>').prependTo('body');
                }
                
                const toastEl = $(toastHtml).appendTo(container);
                
                setTimeout(function() {
                    toastEl.animate({ opacity: 0 }, 400, function() {
                        $(this).slideUp(200, function() {
                            $(this).remove();
                        });
                    });
                }, 4500);
            }

            function hideAlert() {
                $('#salary-slip-alert').addClass('d-none');
            }

            function validateBankDetails() {
                const bName = $('#bank_name').val().trim();
                const aTitle = $('#account_title').val().trim();
                const aNo = $('#account_no').val().trim();
                const iban = $('#iban').val().trim();

                const isComplete = (bName !== '' && aTitle !== '' && (aNo !== '' || iban !== ''));
                if (isComplete) {
                    hideAlert();
                }
                return isComplete;
            }

               let currentFinancialData = null;

            function updateFinancialDisplay() {
                if (!currentFinancialData) return;
                const fin = currentFinancialData;
                const comm = parseFloat(fin.sales_commission) || 0;

                if (fin.has_expected_salary && fin.base_salary !== null) {
                    $('#fin-expected-salary').text('Rs. ' + Number(fin.base_salary).toLocaleString());
                    $('#fin-basic-salary').text('Rs. ' + Number(fin.basic_salary).toLocaleString());
                    $('#fin-rent-travel').text('Rs. ' + Number(fin.house_rent_travel).toLocaleString());
                    $('#fin-medical').text('Rs. ' + Number(fin.medical_allowance).toLocaleString());
                    $('#sum-deductions').text('Rs. ' + Number(fin.deductions).toLocaleString());
                    
                    let netTakeAway = Math.max(0, (fin.gross_pay - fin.deductions) + comm);
                    $('#sum-take-away').text('Rs. ' + Number(netTakeAway).toLocaleString());
                } else {
                    $('#fin-expected-salary').text('—');
                    $('#fin-basic-salary').text('—');
                    $('#fin-rent-travel').text('—');
                    $('#fin-medical').text('—');
                    $('#sum-deductions').text('Rs. 0');
                    
                    if (comm > 0) {
                        $('#sum-take-away').text('Rs. ' + Number(comm).toLocaleString());
                    } else {
                        $('#sum-take-away').text('—');
                    }
                }

                $('#fin-commission').text(comm > 0 ? ('+ Rs. ' + Number(comm).toLocaleString()) : 'Rs. 0');
            }

            function fetchDetails() {
                currentUserId = $('#filter-employee').val();
                currentFinancialData = null;
                const month = $('#filter-month').val();
                const year = $('#filter-year').val();

                if (!currentUserId) {
                    $('#placeholder-card').removeClass('d-none');
                    $('#main-content').addClass('d-none');
                    $('#header-actions-wrapper').addClass('d-none');
                    hideAlert();
                    return;
                }

                $.ajax({
                    url: "{{ route('super-admin.salary-slips.api.details') }}",
                    method: 'GET',
                    data: { user_id: currentUserId, month: month, year: year },
                    success: function(res) {
                        $('#placeholder-card').addClass('d-none');
                        $('#main-content').removeClass('d-none');
                        $('#header-actions-wrapper').removeClass('d-none');

                        // Update Name Badge on the most right side of top header
                        $('#header-name-badge').text(res.user.name);

                        // Work Summary Box
                        $('#sum-expected-time').text(res.summary.expected_time_formatted);
                        $('#sum-time-given').text(res.summary.time_given_formatted);
                        $('#sum-difference').text(res.summary.difference_formatted);
                        if (res.summary.is_difference_positive) {
                            $('#sum-diff-box span:last-child').removeClass('text-danger').addClass('text-success');
                        } else {
                            $('#sum-diff-box span:last-child').removeClass('text-success').addClass('text-danger');
                        }
                        $('#sum-leaves').text(res.financial.leaves);
                        $('#sum-unpaid-leaves').text(res.financial.unpaid_leaves || 0);
                        $('#sum-absents').text(res.financial.absents);
                        $('#sum-holidays').text(res.financial.holidays || 0);
                        $('#sum-remotes').text(Number(res.financial.remotes || 0));

                        // Financial Box
                        currentFinancialData = res.financial;
                        updateFinancialDisplay();

                        // Recipient Email Card
                        $('#salary_slip_email').val(res.user.salary_slip_email || '');
                        $('#recipient-fallback-email').text(res.user.email);
                        $('#salary_slip_email').attr('placeholder', 'Fallback: ' + res.user.email);

                        // Bank Details
                        $('#bank_name').val(res.bank_details.bank_name || '');
                        $('#account_title').val(res.bank_details.account_title || '');
                        $('#account_no').val(res.bank_details.account_no || '');
                        $('#iban').val(res.bank_details.iban || '');

                        validateBankDetails();
                    },
                    error: function() {
                        showAlert('Error loading salary slip details. Please try again.');
                    }
                });
            }

            function updatePageSelectionLabel() {
                const slipChecked = $('#chk-page-salary-slip').is(':checked');
                const trackChecked = $('#chk-page-tracksheet').is(':checked');

                if (slipChecked && trackChecked) {
                    $('#dropdown-pages-label').text('Salary Slip, Track Sheet');
                } else if (slipChecked) {
                    $('#dropdown-pages-label').text('Salary Slip');
                } else if (trackChecked) {
                    $('#dropdown-pages-label').text('Track Sheet');
                } else {
                    $('#dropdown-pages-label').text('Select Pages...');
                }
            }

            $('.page-select-chk').on('change', function() {
                const slipChecked = $('#chk-page-salary-slip').is(':checked');
                const trackChecked = $('#chk-page-tracksheet').is(':checked');
                if (!slipChecked && !trackChecked) {
                    $(this).prop('checked', true);
                    showAlert('At least one page must be selected.');
                }
                updatePageSelectionLabel();
            });

            $('#filter-employee, #filter-month, #filter-year').on('change', fetchDetails);
            $('#bank_name, #account_title, #account_no, #iban').on('input', validateBankDetails);

            $('#btn-clear-filters').on('click', function() {
                $('#filter-employee').val('').trigger('change');
                $('#chk-page-salary-slip').prop('checked', true);
                $('#chk-page-tracksheet').prop('checked', true);
                updatePageSelectionLabel();
            });

            // Letterhead Image Box & Upload/Save Button Handlers
            $('#letterhead-preview-box').on('click', function(e) {
                if (e.target.id === 'letterhead_image') return;
                $('#letterhead_image').click();
            });

            $('#btn-upload-letterhead').on('click', function() {
                const fileInput = $('#letterhead_image')[0];
                if (!fileInput.files || !fileInput.files[0]) {
                    $('#letterhead_image').click();
                    return;
                }

                const btn = $(this);
                btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Saving Letterhead...');

                const formData = new FormData();
                formData.append('_token', "{{ csrf_token() }}");
                formData.append('letterhead_image', fileInput.files[0]);

                $.ajax({
                    url: "{{ route('super-admin.salary-slips.upload-letterhead') }}",
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(res) {
                        btn.prop('disabled', false)
                            .removeClass('row-btn-outline-green')
                            .addClass('row-btn-outline-blue')
                            .html('<i class="bi bi-cloud-arrow-up me-1"></i> Update Letterhead Image');
                        showAlert(res.message, true);
                    },
                    error: function(err) {
                        btn.prop('disabled', false)
                            .removeClass('row-btn-outline-green')
                            .addClass('row-btn-outline-blue')
                            .html('<i class="bi bi-cloud-arrow-up me-1"></i> Update Letterhead Image');
                        const msg = (err.responseJSON && err.responseJSON.message) ? err.responseJSON.message : 'Failed to upload letterhead.';
                        showAlert(msg);
                    }
                });
            });

            $('#letterhead_image').on('change', function() {
                const file = this.files[0];
                if (!file) return;

                if (!['image/png', 'image/jpeg', 'image/jpg'].includes(file.type)) {
                    showAlert('Only PNG, JPG, and JPEG images are allowed for the letterhead.');
                    $(this).val('');
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(evt) {
                    const img = new Image();
                    img.onload = function() {
                        const ratio = img.height / img.width;
                        if (ratio < 1.25 || ratio > 1.58) {
                            showAlert('Invalid image dimension. Please upload an A4 size image in portrait orientation (e.g. 210mm x 297mm or 1240px x 1754px).', false);
                            $('#letterhead_image').val('');
                            $('#preview-empty-state').removeClass('d-none');
                            $('#preview-active-state').addClass('d-none');
                            return;
                        }

                        $('#letterhead-img-preview').attr('src', evt.target.result);
                        $('#preview-empty-state').addClass('d-none');
                        $('#preview-active-state').removeClass('d-none');
                        $('#preview-filename').text(file.name + ' (' + img.width + 'x' + img.height + 'px - A4)');
                        
                        // Switch button to Save Letterhead Image
                        $('#btn-upload-letterhead')
                            .removeClass('row-btn-outline-blue')
                            .addClass('row-btn-outline-green')
                            .html('<i class="bi bi-save me-1"></i> Save Letterhead Image');

                        showAlert('A4 Letterhead image loaded. Click "Save Letterhead Image" to persist.', true);
                    };
                    img.src = evt.target.result;
                };
                reader.readAsDataURL(file);
            });

            const previewBox = document.getElementById('letterhead-preview-box');
            if (previewBox) {
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    previewBox.addEventListener(eventName, function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                    }, false);
                });
                ['dragenter', 'dragover'].forEach(eventName => {
                    previewBox.addEventListener(eventName, () => $(previewBox).css('border-color', '#3b82f6'), false);
                });
                ['dragleave', 'drop'].forEach(eventName => {
                    previewBox.addEventListener(eventName, () => $(previewBox).css('border-color', 'rgba(255, 255, 255, 0.2)'), false);
                });
                previewBox.addEventListener('drop', function(e) {
                    const dt = e.dataTransfer;
                    const files = dt.files;
                    if (files && files.length > 0) {
                        const file = files[0];
                        if (file.type.startsWith('image/')) {
                            const input = document.getElementById('letterhead_image');
                            const dataTransfer = new DataTransfer();
                            dataTransfer.items.add(file);
                            input.files = dataTransfer.files;
                            $(input).trigger('change');
                        } else {
                            showAlert('Please upload a valid image file (PNG/JPEG/JPG).');
                        }
                    }
                }, false);
            }

            // Save Recipient Email
            $('#btn-save-recipient-email').on('click', function() {
                if (!currentUserId) return;
                const btn = $(this);
                btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Saving...');

                $.ajax({
                    url: "{{ route('super-admin.salary-slips.save-recipient-email') }}",
                    method: 'POST',
                    data: {
                        _token: "{{ csrf_token() }}",
                        user_id: currentUserId,
                        salary_slip_email: $('#salary_slip_email').val()
                    },
                    success: function(res) {
                        btn.prop('disabled', false).html('<i class="bi bi-check2-circle me-1"></i> Save Email');
                        showAlert(res.message, true);
                    },
                    error: function() {
                        btn.prop('disabled', false).html('<i class="bi bi-check2-circle me-1"></i> Save Email');
                        showAlert('Failed to update recipient email.');
                    }
                });
            });

            // Save Bank Details
            $('#btn-save-bank-details').on('click', function() {
                if (!currentUserId) return;
                const btn = $(this);
                btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Saving...');

                $.ajax({
                    url: "{{ route('super-admin.salary-slips.save-bank-details') }}",
                    method: 'POST',
                    data: {
                        _token: "{{ csrf_token() }}",
                        user_id: currentUserId,
                        bank_name: $('#bank_name').val(),
                        account_title: $('#account_title').val(),
                        account_no: $('#account_no').val(),
                        iban: $('#iban').val()
                    },
                    success: function(res) {
                        btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i> Save Bank Details');
                        showAlert(res.message, true);
                        validateBankDetails();
                    },
                    error: function() {
                        btn.prop('disabled', false).html('<i class="bi bi-save me-1"></i> Save Bank Details');
                        showAlert('Failed to save bank details.');
                    }
                });
            });

            // Enterprise Instant Preview in New Tab (0.01s GPU Render with Curved Edges & Print to PDF)
            $('#btn-preview-pdf').on('click', function() {
                if (!currentUserId) {
                    showAlert('Please select an employee first.');
                    return;
                }

                if (!currentFinancialData || !currentFinancialData.has_expected_salary) {
                    showAlert('Salary is not configured for this employee.');
                    return;
                }

                if (!validateBankDetails()) {
                    showAlert('Kindly fill in the bank details (Bank Name, Account Title, Account No / IBAN) before generating or previewing the salary slip.');
                    return;
                }

                const includeSalarySlip = $('#chk-page-salary-slip').is(':checked') ? 1 : 0;
                const includeTracksheet = $('#chk-page-tracksheet').is(':checked') ? 1 : 0;

                if (!includeSalarySlip && !includeTracksheet) {
                    showAlert('Please select at least one page to preview.');
                    return;
                }

                const month = $('#filter-month').val();
                const year = $('#filter-year').val();
                const url = `{{ route('super-admin.salary-slips.preview') }}?user_id=${currentUserId}&month=${month}&year=${year}&include_salary_slip=${includeSalarySlip}&include_tracksheet=${includeTracksheet}`;
                window.open(url, '_blank');
            });

            // Send Email
            $('#btn-send-email').on('click', function() {
                if (!currentUserId) {
                    showAlert('Please select an employee first.');
                    return;
                }

                if (!currentFinancialData || !currentFinancialData.has_expected_salary) {
                    showAlert('Salary is not configured for this employee.');
                    return;
                }

                if (!validateBankDetails()) {
                    showAlert('Kindly fill in the bank details before sending the salary slip.');
                    return;
                }

                const includeSalarySlip = $('#chk-page-salary-slip').is(':checked') ? 1 : 0;
                const includeTracksheet = $('#chk-page-tracksheet').is(':checked') ? 1 : 0;

                if (!includeSalarySlip && !includeTracksheet) {
                    showAlert('Please select at least one page to send.');
                    return;
                }

                const btn = $(this);
                btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Sending Email...');

                const month = $('#filter-month').val();
                const year = $('#filter-year').val();

                $.ajax({
                    url: "{{ route('super-admin.salary-slips.send-email') }}",
                    method: 'POST',
                    data: {
                        _token: "{{ csrf_token() }}",
                        user_id: currentUserId,
                        month: month,
                        year: year,
                        include_salary_slip: includeSalarySlip,
                        include_tracksheet: includeTracksheet
                    },
                    success: function(res) {
                        btn.prop('disabled', false).html('<i class="bi bi-send me-1"></i> Send Salary Slip');
                        showAlert(res.message, true);
                    },
                    error: function(err) {
                        btn.prop('disabled', false).html('<i class="bi bi-send me-1"></i> Send Salary Slip');
                        const msg = (err.responseJSON && err.responseJSON.message) ? err.responseJSON.message : 'Failed to send salary slip email.';
                        showAlert(msg);
                    }
                });
            });

            // Auto-fetch if employee is already selected (e.g. from notification query param)
            if ($('#filter-employee').val()) {
                fetchDetails();
            }
        });
    </script>
    @endpush
</x-app-layout>
