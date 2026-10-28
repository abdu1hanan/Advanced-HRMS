<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Slip - {{ $user['name'] }} - {{ $period['month_year_label'] }}</title>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts for crisp modern typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400;1,600&display=swap" rel="stylesheet">

    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #0f172a;
            margin: 0;
            padding: 0;
            background-color: #0f172a;
        }

        /* TOP FLOATING ACTION BAR */
        .preview-topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: #1e293b;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 25px;
            z-index: 9999;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
        }

        .topbar-title {
            color: #ffffff;
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-print-action {
            background: #002D62;
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0, 45, 98, 0.5);
        }

        .btn-print-action:hover {
            background: #003c82;
            transform: translateY(-1px);
        }

        .btn-close-action {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-close-action:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* A4 PAGE CONTAINER */
        .preview-viewport {
            padding-top: 80px;
            padding-bottom: 50px;
        }

        .a4-page {
            width: 210mm;
            height: 297mm;
            max-height: 297mm;
            margin: 0 auto 40px auto;
            background: #ffffff;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5);
            position: relative;
            box-sizing: border-box;
            overflow: hidden;
        }

        /* PAGE 1 STYLING WITH LETTERHEAD SPACING */
        .p1-bg-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: fill;
            z-index: 1;
        }

        /* PAGE 1 STYLING WITH LETTERHEAD SPACING */
        .p1-bg-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: fill;
            z-index: 1;
        }

        .p1-content-wrapper {
            position: relative;
            z-index: 2;
            /* Top: 158px (moved down slightly from logo), Width: 80px side margins, Bottom: 95px */
            padding: 158px 80px 95px 80px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            gap: 16px;
        }

        /* TOP DYNAMIC SALARY SLIP BANNER */
        .slip-title-box {
            background-color: #002D62;
            color: #ffffff;
            padding: 10px 16px;
            font-weight: 800;
            font-size: 13px;
            letter-spacing: 0.08em;
            text-align: center;
            border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0, 45, 98, 0.04);
            text-transform: uppercase;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
        }

        /* CARD CONTAINERS & HEADERS */
        .slip-card {
            border: 1px solid #dce6f1;
            border-radius: 8px;
            background: #ffffff;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0, 45, 98, 0.04);
        }

        .slip-card-header {
            background-color: #002D62;
            color: #ffffff;
            padding: 9px 15px;
            font-weight: 700;
            font-size: 12px;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .slip-card-header-left {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .slip-card-header-left i {
            font-size: 13.5px;
        }

        .per-month-badge {
            font-size: 10.5px;
            font-weight: 500;
            opacity: 0.9;
        }

        /* DETAILS TABLE */
        .details-table {
            width: 100%;
            height: 100%;
            border-collapse: collapse;
        }

        .details-table tr {
            border-bottom: 1px solid #f1f5f9;
        }

        .details-table tr:last-child {
            border-bottom: none;
        }

        .details-table td {
            padding: 7.5px 13px;
            font-size: 11px;
            vertical-align: middle;
        }

        .details-label {
            color: #0f172a;
            font-weight: 600;
            width: 48%;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .details-label i {
            color: #002D62;
            font-size: 12px;
            width: 14px;
            text-align: center;
        }

        .details-val {
            color: #0f172a;
            font-weight: 500;
            width: 52%;
        }

        /* DATA TABLE (EARNINGS & DEDUCTIONS) */
        .slip-table {
            width: 100%;
            border-collapse: collapse;
        }

        .slip-table thead th {
            background-color: #edf4fc;
            color: #002D62;
            font-weight: 700;
            font-size: 11px;
            letter-spacing: 0.04em;
            padding: 7.5px 15px;
            border-bottom: 1px solid #dce6f1;
            text-align: left;
        }

        .slip-table thead th.text-end {
            text-align: right;
        }

        .slip-table tbody td {
            padding: 7.5px 15px;
            font-size: 11.5px;
            color: #0f172a;
            font-weight: 500;
            border-bottom: 1px solid #f1f5f9;
        }

        .slip-table tbody tr:last-child td {
            border-bottom: none;
        }

        .slip-table tbody td.text-end {
            text-align: right;
            font-weight: 600;
        }

        /* GROSS PAY INNER HIGHLIGHT BOX */
        .gross-pay-container {
            padding: 6px 10px 8px 10px;
            background: #ffffff;
        }

        .gross-pay-box {
            background-color: #eaf2fa;
            border: 1px solid #cbdff2;
            border-radius: 6px;
            padding: 9px 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .gross-pay-label {
            color: #002D62;
            font-weight: 800;
            font-size: 12.5px;
            letter-spacing: 0.04em;
        }

        .gross-pay-amount {
            color: #002D62;
            font-weight: 800;
            font-size: 16px;
        }

        /* BOTTOM NET TRANSFER BAR */
        .transferred-outer-box {
            border: 1.5px solid #cbdff2;
            border-radius: 8px;
            overflow: hidden;
            background: #ffffff;
            display: flex;
            align-items: stretch;
            box-shadow: 0 1px 4px rgba(0, 45, 98, 0.04);
        }

        .transferred-left-badge {
            background-color: #002D62;
            color: #ffffff;
            padding: 11.5px 32px 11.5px 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            clip-path: polygon(0 0, calc(100% - 20px) 0, 100% 100%, 0 100%);
            font-weight: 700;
            font-size: 11px;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }

        .transferred-left-badge i {
            font-size: 15px;
        }

        .transferred-right-val {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding: 7px 22px;
            color: #002D62;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.01em;
            background: #ffffff;
        }

        /* PAGE 2 STYLING */
        .p2-content-wrapper {
            padding: 30px 35px;
        }

        /* PAGE 2 TIMESHEET TABLE */
        .p2-table-card {
            border: 1.5px solid #1e293b;
            overflow: hidden;
            margin-bottom: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .timesheet-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5px;
        }
        .timesheet-table th {
            background-color: #002D62;
            color: #ffffff;
            font-weight: 800;
            text-align: center;
            padding: 7px 4px;
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .timesheet-table td {
            border: 1px solid #1e293b;
            padding: 4.5px 5px;
            text-align: center;
            vertical-align: middle;
            font-size: 10.5px;
        }

        .summary-card-wrapper {
            border: 1.5px solid #1e293b;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        .summary-table td {
            border: 1px solid #1e293b;
            padding: 6px 10px;
        }
        .summary-title-block {
            background-color: #002D62;
            color: #ffffff;
            font-weight: 900;
            font-size: 19px;
            text-align: center;
            vertical-align: middle;
            width: 26%;
            letter-spacing: 0.1em;
        }

        /* PRINT MEDIA OVERRIDES */
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #ffffff !important;
            }
            .preview-viewport {
                padding: 0 !important;
            }
            .a4-page {
                box-shadow: none !important;
                margin: 0 !important;
                width: 210mm !important;
                height: 297mm !important;
                max-height: 297mm !important;
                border: none !important;
                border-radius: 0 !important;
                page-break-after: always;
            }
            .page-break {
                page-break-before: always !important;
            }
        }
    </style>
</head>
<body>

    <!-- TOP FLOATING ACTION BAR -->
    <div class="preview-topbar no-print">
        <div class="topbar-title">
            <i class="bi bi-file-earmark-pdf" style="font-size: 1.3rem; color: #38bdf8;"></i>
            <span>Salary Slip Preview &mdash; <span style="color: #60a5fa;">{{ $user['name'] }}</span> ({{ $period['month_year_label'] }})</span>
        </div>
        <div class="topbar-actions">
            <button type="button" onclick="window.print()" class="btn-print-action">
                <i class="bi bi-printer-fill"></i> Print / Save as PDF
            </button>
            <button type="button" onclick="window.close()" class="btn-close-action">
                <i class="bi bi-x-lg"></i> Close
            </button>
        </div>
    </div>

    <div class="preview-viewport">
        @if($include_salary_slip ?? true)
        <!-- PAGE 1: OFFICIAL SALARY SLIP WITH LETTERHEAD SPACING -->
        <div class="a4-page">
            @if(!empty($letterhead_url))
                <img src="{{ $letterhead_url }}" class="p1-bg-image" alt="Letterhead Background">
            @endif

            <div class="p1-content-wrapper">
                <!-- DYNAMIC SALARY SLIP HEADER (BLUE BOX) -->
                <div class="slip-title-box">
                    <span>SALARY SLIP &mdash; {{ strtoupper($period['month_year_label']) }}</span>
                </div>

                <!-- TOP CARDS: EMPLOYEE DETAILS & PAYMENT DETAILS -->
                <div style="display: flex; gap: 18px; align-items: stretch;">
                    
                    <!-- EMPLOYEE DETAILS CARD -->
                    <div class="slip-card" style="flex: 1; display: flex; flex-direction: column;">
                        <div class="slip-card-header">
                            <div class="slip-card-header-left">
                                <i class="bi bi-person"></i>
                                <span>EMPLOYEE DETAILS</span>
                            </div>
                        </div>
                        <table class="details-table" style="flex: 1;">
                            <tbody>
                                <tr>
                                    <td class="details-label">
                                        <i class="bi bi-person"></i>
                                        <span>Employee Name</span>
                                    </td>
                                    <td class="details-val">{{ $user['name'] }}</td>
                                </tr>
                                <tr>
                                    <td class="details-label">
                                        <i class="bi bi-briefcase"></i>
                                        <span>Designation</span>
                                    </td>
                                    <td class="details-val">{{ $user['designation'] }}</td>
                                </tr>
                                <tr>
                                    <td class="details-label">
                                        <i class="bi bi-people"></i>
                                        <span>Department</span>
                                    </td>
                                    <td class="details-val">{{ $user['department'] }}</td>
                                </tr>
                                <tr>
                                    <td class="details-label">
                                        <i class="bi bi-geo-alt"></i>
                                        <span>Address</span>
                                    </td>
                                    <td class="details-val">{{ $user['address'] }}</td>
                                </tr>
                                <tr>
                                    <td class="details-label">
                                        <i class="bi bi-calendar3"></i>
                                        <span>Joining Date</span>
                                    </td>
                                    <td class="details-val">{{ $user['joining_date'] }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAYMENT DETAILS CARD -->
                    <div class="slip-card" style="flex: 1; display: flex; flex-direction: column;">
                        <div class="slip-card-header">
                            <div class="slip-card-header-left">
                                <i class="bi bi-wallet2"></i>
                                <span>PAYMENT DETAILS</span>
                            </div>
                        </div>
                        <table class="details-table" style="flex: 1;">
                            <tbody>
                                <tr>
                                    <td class="details-label" style="padding-left: 14px;">
                                        <span>Bank Name</span>
                                    </td>
                                    <td class="details-val">{{ $bank_details['bank_name'] ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <td class="details-label" style="padding-left: 14px;">
                                        <span>Account Title</span>
                                    </td>
                                    <td class="details-val">{{ $bank_details['account_title'] ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <td class="details-label" style="padding-left: 14px;">
                                        <span>Account No.</span>
                                    </td>
                                    <td class="details-val">{{ $bank_details['account_no'] ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <td class="details-label" style="padding-left: 14px;">
                                        <span>IBAN</span>
                                    </td>
                                    <td class="details-val" style="word-break: break-all; font-size: 11px;">{{ $bank_details['iban'] ?: '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- EARNINGS CARD -->
                <div class="slip-card">
                    <div class="slip-card-header">
                        <div class="slip-card-header-left">
                            <i class="bi bi-coin"></i>
                            <span>EARNINGS</span>
                        </div>
                        <span class="per-month-badge">(Per Month)</span>
                    </div>
                    <table class="slip-table">
                        <thead>
                            <tr>
                                <th style="width: 65%;">DESCRIPTION</th>
                                <th style="width: 35%;" class="text-end">AMOUNT (PKR)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Basic Salary: 70%</td>
                                <td class="text-end">{{ $financial['basic_salary'] !== null ? 'PKR ' . number_format($financial['basic_salary']) : '—' }}</td>
                            </tr>
                            <tr>
                                <td>House Rent Allowance / Travel Allowance: 20%</td>
                                <td class="text-end">{{ $financial['house_rent_travel'] !== null ? 'PKR ' . number_format($financial['house_rent_travel']) : '—' }}</td>
                            </tr>
                            <tr>
                                <td>Medical Allowance: 10%</td>
                                <td class="text-end">{{ $financial['medical_allowance'] !== null ? 'PKR ' . number_format($financial['medical_allowance']) : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="gross-pay-container">
                        <div class="gross-pay-box">
                            <span class="gross-pay-label">GROSS PAY</span>
                            <span class="gross-pay-amount">{{ $financial['gross_pay'] !== null ? 'PKR ' . number_format($financial['gross_pay']) : '—' }}</span>
                        </div>
                        @if(!empty($financial['sales_commission']) && $financial['sales_commission'] > 0)
                            <div class="commission-box" style="margin-top: 6px; background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 7.5px 14px; display: flex; align-items: center; justify-content: space-between;">
                                <span style="color: #166534; font-weight: 800; font-size: 12px; letter-spacing: 0.04em;">SALES COMMISSION</span>
                                <span style="color: #166534; font-weight: 800; font-size: 15px;">+ PKR {{ number_format($financial['sales_commission']) }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- DEDUCTIONS CARD -->
                <div class="slip-card">
                    <div class="slip-card-header">
                        <div class="slip-card-header-left">
                            <i class="bi bi-receipt-cutoff"></i>
                            <span>DEDUCTIONS</span>
                        </div>
                        <span class="per-month-badge">(Per Month)</span>
                    </div>
                    <table class="slip-table">
                        <thead>
                            <tr>
                                <th style="width: 65%;">DESCRIPTION</th>
                                <th style="width: 35%;" class="text-end">AMOUNT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Salary Advance</td>
                                <td class="text-end">PKR {{ number_format($financial['salary_advance']) }}</td>
                            </tr>
                            <tr>
                                <td>NO. of working days</td>
                                <td class="text-end">{{ $financial['working_days'] }}</td>
                            </tr>
                            <tr>
                                <td>Leaves</td>
                                <td class="text-end">{{ $financial['leaves'] }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- AMOUNT TRANSFERRED TO SALARY ACCOUNT (BOTTOM BAR) -->
                <div class="transferred-outer-box">
                    <div class="transferred-left-badge">
                        <i class="bi bi-wallet2"></i>
                        <span>AMOUNT TRANSFERRED TO SALARY ACCOUNT</span>
                    </div>
                    <div class="transferred-right-val">
                        {{ $financial['take_away'] !== null ? 'PKR ' . number_format($financial['take_away']) : '—' }}
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($include_tracksheet ?? true)
        <!-- PAGE 2: MONTHLY TRACK RECORD SHEET (CURVED EDGES) -->
        <div class="a4-page {{ ($include_salary_slip ?? true) ? 'page-break' : '' }}">
            <div class="p2-content-wrapper">
                <!-- MONTH & YEAR TOP BANNER (BLUE THEME) -->
                <div style="background-color: #002D62; color: #ffffff; padding: 7px 14px; font-size: 15px; font-weight: 800; text-align: center; margin-bottom: 12px; border-radius: 5px; letter-spacing: 0.08em; text-transform: uppercase;">
                    {{ strtoupper($period['month_year_label']) }}
                </div>

                <!-- CURVED TIMESHEET TABLE CARD -->
                <div class="p2-table-card">
                    <table class="timesheet-table">
                        <thead>
                            <tr>
                                <th style="width: 14%;">Name</th>
                                <th style="width: 14%;">Date</th>
                                <th style="width: 12%;">Day</th>
                                <th style="width: 25%;" colspan="2">Weekly Summary</th>
                                <th style="width: 11%;">%</th>
                                <th style="width: 14%;">Total time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($records as $index => $row)
                                @php
                                    $dayNum = $row['day_of_week'] ?? 1;
                                    $isBeforeJoin = !empty($row['is_before_join']);
                                    $isWeekend = !empty($row['is_weekend']);
                                    $isLeave = !empty($row['is_leave']);
                                    $isUnpaidLeave = !empty($row['is_unpaid_leave']);
                                    $isAbsent = !empty($row['is_absent']);
                                    $isHoliday = !empty($row['is_holiday']);

                                    // Date cell: yellow background
                                    $dateBg = '#fef08a';

                                    // Day cell & Total Time cell styling
                                    $dayBg = '#ffffff';
                                    $dayColor = '#0f172a';
                                    $dayWeight = 'normal';

                                    $totalTimeBg = '#ffffff';
                                    $totalTimeColor = ($row['total_seconds'] ?? 0) > 0 ? '#0f172a' : '#FF0000';
                                    $totalTimeWeight = 'bold';

                                    $pctBg = '#FF0000';
                                    $pctColor = '#FFFFFF';

                                    if ($isBeforeJoin) {
                                        $dayBg = '#ffffff';
                                        $dayColor = '#0f172a';
                                        $dayWeight = 'normal';

                                        $totalTimeBg = '#ffffff';
                                        $totalTimeColor = '#0f172a';
                                        $totalTimeWeight = 'normal';

                                        $pctBg = '#ffffff';
                                        $pctColor = '#0f172a';
                                    } else {
                                        if ($isWeekend) {
                                            $dayBg = '#ef4444'; // Coral red for Weekend Day cell
                                            $dayColor = '#ffffff';
                                            $dayWeight = 'bold';
                                        }

                                        if ($isLeave) {
                                            $totalTimeBg = '#FFFF00'; // Bright Yellow for Leave Total Time
                                            $totalTimeColor = '#000000';
                                        } elseif ($isUnpaidLeave) {
                                            $totalTimeBg = '#4FA8FF'; // Bright Sky Blue for Unpaid Leave Total Time
                                            $totalTimeColor = '#000000';
                                        } elseif ($isAbsent) {
                                            $totalTimeBg = '#A64D79'; // Deep Purple / Magenta for Absent Total Time
                                            $totalTimeColor = '#FFFFFF';
                                        } elseif ($isHoliday) {
                                            $totalTimeBg = '#B45F06'; // Warm Bronze for Holiday Total Time
                                            $totalTimeColor = '#FFFFFF';
                                        } elseif ($isWeekend) {
                                            $totalTimeBg = '#FF0000'; // Bright Red for Weekend Total Time
                                            $totalTimeColor = '#FFFFFF';
                                        }

                                        // Percentage cell color coding matching Excel
                                        $pctValFloat = (float)str_replace('%', '', $row['pct_formatted'] ?? '0');
                                        if ($pctValFloat >= 80) {
                                            $pctBg = '#6AA84F'; // Forest Green
                                            $pctColor = '#FFFFFF';
                                        } elseif ($pctValFloat > 0 && $pctValFloat < 80) {
                                            $pctBg = '#F59E0B'; // Amber Yellow
                                        }
                                    }

                                    // Weekly summary label & value
                                    $wsLabel = '';
                                    $wsVal = '';
                                    $wsBg = '#dbeafe'; // Light blue default
                                    $wsColor = '#0f172a';
                                    $wsWeight = 'normal';

                                    if ($dayNum === 1) { $wsLabel = 'Leaves ='; $wsVal = $row['weekly_summary_value'] ?? '0'; }
                                    elseif ($dayNum === 2) { $wsLabel = 'Absent ='; $wsVal = $row['weekly_summary_value'] ?? '0'; }
                                    elseif ($dayNum === 3) { $wsLabel = 'Holiday ='; $wsVal = $row['weekly_summary_value'] ?? '0'; }
                                    elseif ($dayNum === 4) { $wsLabel = 'Working Hrs/Day'; $wsVal = $row['weekly_summary_value'] ?? '8:00:00'; }
                                    elseif ($dayNum === 5) { $wsLabel = 'Expected Time*'; $wsVal = $row['weekly_summary_value'] ?? '40:00:00'; }
                                    elseif ($dayNum === 6) { $wsLabel = 'Time Given*'; $wsVal = $row['weekly_summary_value'] ?? '0:00:00'; $wsWeight = 'bold'; }
                                    elseif ($dayNum === 7) { 
                                        $wsLabel = 'Difference*'; 
                                        $wsVal = $row['weekly_summary_value'] ?? '0:00:00'; 
                                        $isWsDiffPos = (strpos($wsVal, '-') === false);
                                        $wsBg = $isWsDiffPos ? '#6AA84F' : '#FF0000'; // Green if positive, Red if negative
                                        $wsColor = '#FFFFFF'; 
                                        $wsWeight = 'bold'; 
                                    }
                                @endphp
                                <tr>
                                    @if($index === 0)
                                        <td rowspan="{{ count($records) }}" style="vertical-align: middle; font-weight: bold; font-size: 14px; background-color: #ffffff; text-align: center; word-wrap: break-word; white-space: normal; line-height: 1.3;">
                                            {{ $user['name'] }}
                                        </td>
                                    @endif

                                    <!-- Date Column -->
                                    <td style="background-color: {{ $dateBg }}; color: #0f172a; font-weight: bold; font-family: monospace;">
                                        {{ $row['date_formatted'] }}
                                    </td>

                                    <!-- Day Column (WHITE for leaves/holidays, coral red for weekend) -->
                                    <td style="background-color: {{ $dayBg }}; color: {{ $dayColor }}; font-weight: {{ $dayWeight }};">
                                        {{ $row['day_name'] }}
                                    </td>

                                    <!-- Weekly Summary Label -->
                                    <td style="background-color: {{ $wsBg }}; color: {{ $wsColor }}; font-weight: {{ $wsWeight }}; text-align: left; padding-left: 6px;">
                                        {{ $wsLabel }}
                                    </td>

                                    <!-- Weekly Summary Value -->
                                    <td style="background-color: {{ $wsBg }}; color: {{ $wsColor }}; font-weight: bold; text-align: center;">
                                        {{ $wsVal }}
                                    </td>

                                    <!-- Percentage Column -->
                                    <td style="background-color: {{ $pctBg }}; color: {{ $pctColor }}; font-weight: bold;">
                                        {{ $row['pct_formatted'] }}
                                    </td>

                                    <!-- Total Session Time Column (Status Color Highlighted) -->
                                    <td style="background-color: {{ $totalTimeBg }}; color: {{ $totalTimeColor }}; font-family: monospace; font-weight: {{ $totalTimeWeight }};">
                                        {{ $row['total_time_formatted'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- CURVED SUMMARY CARD -->
                <div class="summary-card-wrapper">
                    <table class="summary-table">
                        <tr>
                            <td rowspan="6" class="summary-title-block">
                                SUMMARY
                            </td>
                            <td style="font-weight: 700; background-color: #ffffff; width: 24%; color: #334155;">Expected Time*</td>
                            <td style="text-align: right; background-color: #ffffff; width: 13%; font-weight: 800;">{{ $summary['expected_time_formatted'] }}</td>
                            <td style="font-weight: 700; background-color: #ffffff; width: 24%; color: #334155;">Actual Salary</td>
                            <td style="text-align: right; background-color: #ffffff; width: 13%; font-weight: 800;">{{ $financial['base_salary'] !== null ? number_format($financial['base_salary']) : '—' }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight: 700; background-color: #ffffff; color: #334155;">Time Given*</td>
                            <td style="text-align: right; background-color: #ffffff; font-weight: 800;">{{ $summary['time_given_formatted'] }}</td>
                            <td style="font-weight: 700; background-color: #ffffff; color: #334155;">Deductions*</td>
                            <td style="text-align: right; background-color: #ffffff; font-weight: 800;">{{ number_format($financial['deductions']) }}</td>
                        </tr>
                        @php $diffBg = $summary['is_difference_positive'] ? '#6AA84F' : '#FF0000'; @endphp
                        <tr>
                            <td style="font-weight: 700; background-color: {{ $diffBg }}; color: #ffffff;">Difference*</td>
                            <td style="text-align: right; background-color: {{ $diffBg }}; color: #ffffff; font-weight: 800;">{{ $summary['is_difference_positive'] ? '' : '-' }}{{ $summary['difference_formatted'] }}</td>
                            <td style="font-weight: 700; background-color: #ffffff; color: #334155;">Take Away*</td>
                            <td style="text-align: right; background-color: #ffffff; font-size: 13px; font-weight: 800;">{{ $financial['take_away'] !== null ? number_format($financial['take_away']) : '—' }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight: 700; background-color: #ffffff; color: #334155;">Leaves (Paid)</td>
                            <td style="text-align: right; background-color: #ffffff; font-weight: 800;">{{ $financial['leaves'] }}</td>
                            <td style="font-weight: 700; background-color: #ffffff; color: #334155;">Company Holidays</td>
                            <td style="text-align: right; background-color: #ffffff; font-weight: 800;">{{ $financial['holidays'] ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td style="font-weight: 700; background-color: #ffffff; color: #334155;">Unpaid Leave</td>
                            <td style="text-align: right; background-color: #ffffff; font-weight: 800;">{{ $financial['unpaid_leaves'] ?? 0 }}</td>
                            <td colspan="2" style="background-color: #ffffff;"></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 700; background-color: #ffffff; color: #334155;">Absents*</td>
                            <td style="text-align: right; background-color: #ffffff; font-weight: 800;">{{ $financial['absents'] }}</td>
                            <td colspan="2" style="background-color: #ffffff;"></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>

</body>
</html>
