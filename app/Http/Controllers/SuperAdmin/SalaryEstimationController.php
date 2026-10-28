<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EmployeeSalarySlipDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalaryEstimationController extends Controller
{
    /**
     * Display the Salary Estimation & Payroll Overview page.
     */
    public function index(Request $request): View
    {
        $tz = 'Asia/Karachi';
        $now = Carbon::now($tz);
        
        $selectedMonth  = (int) $request->input('month', $now->month);
        $selectedYear   = (int) $request->input('year', $now->year);
        $selectedUserId = $request->input('user_id');
        $selectedSalaryStatus = $request->input('salary_status', 'all');

        $users = User::whereHas('role', fn ($q) => $q->where('name', '!=', 'super_admin'))->orderBy('name')->get();

        // Read deduction settings
        $settingsFile = storage_path('app/salary_deduction_settings.json');
        $settings = [
            'rule' => 'calendar_days',
            'recalculate_all' => true,
            'effective_month' => null,
            'previous_rule' => null
        ];
        if (file_exists($settingsFile)) {
            $settings = array_merge($settings, json_decode(file_get_contents($settingsFile), true) ?: []);
        }

        $payrollData = $this->computeCompanyPayroll($selectedMonth, $selectedYear, $selectedUserId ? (int)$selectedUserId : null, $selectedSalaryStatus);

        return view('super-admin.salary.estimations', array_merge($payrollData, [
            'users'                => $users,
            'settings'             => $settings,
            'selectedMonth'        => $selectedMonth,
            'selectedYear'         => $selectedYear,
            'selectedUserId'       => $selectedUserId,
            'selectedSalaryStatus' => $selectedSalaryStatus,
        ]));
    }

    /**
     * API endpoint to get salary estimation data for a given month/year via AJAX.
     */
    public function getDataApi(Request $request): JsonResponse
    {
        $request->validate([
            'month'         => 'required|integer|between:1,12',
            'year'          => 'required|integer|min:2020|max:2035',
            'user_id'       => 'nullable|exists:users,id',
            'salary_status' => 'nullable|in:all,configured,not_configured',
        ]);

        $month        = (int) $request->month;
        $year         = (int) $request->year;
        $userId       = $request->user_id ? (int) $request->user_id : null;
        $salaryStatus = $request->salary_status ?: 'all';

        $payrollData = $this->computeCompanyPayroll($month, $year, $userId, $salaryStatus);

        return response()->json([
            'status' => 'success',
            'data'   => $payrollData,
        ]);
    }

    /**
     * Update employee expected gross salary directly from estimations table and return recalculated data.
     */
    public function updateSalary(Request $request): JsonResponse
    {
        $request->validate([
            'user_id'                => 'required|exists:users,id',
            'expected_salary_amount' => ['nullable', 'regex:/^(0|[1-9][0-9]*)$/', 'integer', 'min:0', 'max:9999999999'],
            'month'                  => 'required|integer|between:1,12',
            'year'                   => 'required|integer|min:2020|max:2035',
            'filter_user_id'         => 'nullable|exists:users,id',
            'salary_status'          => 'nullable|in:all,configured,not_configured',
        ], [
            'expected_salary_amount.regex' => 'The expected salary amount cannot have leading zeros (e.g. 001122) and must be 0 or greater.',
            'expected_salary_amount.min'   => 'The expected salary amount cannot be less than 0.',
        ]);

        $user = User::findOrFail($request->user_id);

        if ($user->isSuperAdmin()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Super Admin salary cannot be modified.',
            ], 422);
        }

        $salaryAmount = $request->filled('expected_salary_amount')
            ? (int) round($request->expected_salary_amount)
            : null;

        $user->expected_salary_amount = $salaryAmount;
        $user->save();

        $month         = (int) $request->month;
        $year          = (int) $request->year;
        $filterUserId  = $request->filter_user_id ? (int) $request->filter_user_id : null;
        $salaryStatus  = $request->salary_status ?: 'all';

        // Compute updated payroll with active filters
        $payrollData = $this->computeCompanyPayroll($month, $year, $filterUserId, $salaryStatus);

        // Find employee in filtered payroll or compute specifically for this employee
        $updatedEmployee = null;
        foreach ($payrollData['employees'] as $emp) {
            if ($emp['user_id'] === $user->id) {
                $updatedEmployee = $emp;
                break;
            }
        }

        if (!$updatedEmployee) {
            $singlePayroll = $this->computeCompanyPayroll($month, $year, $user->id, 'all');
            $updatedEmployee = $singlePayroll['employees'][0] ?? null;
        }

        return response()->json([
            'status'   => 'success',
            'message'  => 'Gross salary updated successfully.',
            'employee' => $updatedEmployee,
            'data'     => $payrollData,
        ]);
    }

    /**
     * Update employee sales commission directly from estimations table and return recalculated data.
     */
    public function updateCommission(Request $request): JsonResponse
    {
        $request->validate([
            'user_id'          => 'required|exists:users,id',
            'sales_commission' => ['nullable', 'regex:/^(0|[1-9][0-9]*)$/', 'integer', 'min:0', 'max:9999999999'],
            'month'            => 'required|integer|between:1,12',
            'year'             => 'required|integer|min:2020|max:2035',
            'filter_user_id'   => 'nullable|exists:users,id',
            'salary_status'    => 'nullable|in:all,configured,not_configured',
        ], [
            'sales_commission.regex' => 'The sales commission cannot have leading zeros (e.g. 001122) and must be 0 or greater.',
            'sales_commission.min'   => 'The sales commission cannot be less than 0.',
        ]);

        $user = User::findOrFail($request->user_id);

        if ($user->isSuperAdmin()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Super Admin commission cannot be modified.',
            ], 422);
        }

        $commissionAmount = $request->filled('sales_commission')
            ? (int) round($request->sales_commission)
            : null;

        $user->sales_commission = $commissionAmount;
        $user->save();

        $month         = (int) $request->month;
        $year          = (int) $request->year;
        $filterUserId  = $request->filter_user_id ? (int) $request->filter_user_id : null;
        $salaryStatus  = $request->salary_status ?: 'all';

        // Compute updated payroll with active filters
        $payrollData = $this->computeCompanyPayroll($month, $year, $filterUserId, $salaryStatus);

        // Find employee in filtered payroll or compute specifically for this employee
        $updatedEmployee = null;
        foreach ($payrollData['employees'] as $emp) {
            if ($emp['user_id'] === $user->id) {
                $updatedEmployee = $emp;
                break;
            }
        }

        if (!$updatedEmployee) {
            $singlePayroll = $this->computeCompanyPayroll($month, $year, $user->id, 'all');
            $updatedEmployee = $singlePayroll['employees'][0] ?? null;
        }

        return response()->json([
            'status'   => 'success',
            'message'  => 'Sales commission updated successfully.',
            'employee' => $updatedEmployee,
            'data'     => $payrollData,
        ]);
    }

    /**
     * Export Salary Estimations & Payroll Overview to Excel.
     */
    public function exportExcel(Request $request): StreamedResponse
    {
        $request->validate([
            'month'         => 'nullable|integer|between:1,12',
            'year'          => 'nullable|integer|min:2020|max:2035',
            'user_id'       => 'nullable|exists:users,id',
            'salary_status' => 'nullable|in:all,configured,not_configured',
        ]);

        $tz = 'Asia/Karachi';
        $now = Carbon::now($tz);

        $month        = (int) $request->input('month', $now->month);
        $year         = (int) $request->input('year', $now->year);
        $userId       = $request->user_id ? (int) $request->user_id : null;
        $salaryStatus = $request->salary_status ?: 'all';

        $payrollData = $this->computeCompanyPayroll($month, $year, $userId, $salaryStatus);
        $monthName   = Carbon::createFromDate($year, $month, 1)->format('F');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Salary Estimations');
        $sheet->setShowGridlines(true);
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        // Column widths
        $columnWidths = [
            'A' => 28, // Employee Name
            'B' => 18, // Base Gross
            'C' => 18, // Sales Commission
            'D' => 20, // No. of Working Days
            'E' => 14, // Total Leaves
            'F' => 14, // Total Absents
            'G' => 20, // Total Unpaid Leaves
            'H' => 18, // No. of Remote Days
            'I' => 18, // Total Deductions
            'J' => 18, // Net Salary
        ];
        foreach ($columnWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // Title Block (Row 1)
        $sheet->mergeCells('A1:J1');
        $sheet->setCellValue('A1', "SALARY ESTIMATIONS & PAYROLL OVERVIEW - {$monthName} {$year}");
        $sheet->getRowDimension(1)->setRowHeight(36);
        $sheet->getStyle('A1:J1')->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size'  => 13,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],
            ],
        ]);

        // Blank spacer row 2
        $sheet->getRowDimension(2)->setRowHeight(8);

        // Table Headers (Row 3)
        $headers = [
            'A' => 'Employee Name',
            'B' => 'Base Gross',
            'C' => 'Sales Commission',
            'D' => 'No. of Working Days',
            'E' => 'Total Leaves',
            'F' => 'Total Absents',
            'G' => 'Total Unpaid Leaves',
            'H' => 'No. of Remote Days',
            'I' => 'Total Deductions',
            'J' => 'Net Salary',
        ];

        $headerRow = 3;
        $sheet->getRowDimension($headerRow)->setRowHeight(26);
        foreach ($headers as $col => $label) {
            $sheet->setCellValue($col . $headerRow, $label);
        }

        $sheet->getStyle('A3:J3')->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size'  => 10,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '334155'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '1E293B'],
                ],
            ],
        ]);

        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('B3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('C3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('I3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('J3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Data Rows
        $currentRow = 4;
        $firstDataRow = $currentRow;

        foreach ($payrollData['employees'] as $emp) {
            $sheet->getRowDimension($currentRow)->setRowHeight(22);

            $sheet->setCellValue('A' . $currentRow, $emp['name']);
            $sheet->setCellValue('B' . $currentRow, $emp['base_salary']);
            $sheet->setCellValue('C' . $currentRow, $emp['sales_commission']);
            $sheet->setCellValue('D' . $currentRow, $emp['working_days']);
            $sheet->setCellValue('E' . $currentRow, $emp['leaves']);
            $sheet->setCellValue('F' . $currentRow, $emp['absents']);
            $sheet->setCellValue('G' . $currentRow, $emp['unpaid_leaves']);
            $sheet->setCellValue('H' . $currentRow, $emp['remotes']);
            $sheet->setCellValue('I' . $currentRow, $emp['deductions']);
            $sheet->setCellValue('J' . $currentRow, $emp['net_salary']);

            $bgColor = ($currentRow % 2 === 0) ? 'F8FAFC' : 'FFFFFF';
            $sheet->getStyle("A{$currentRow}:J{$currentRow}")->applyFromArray([
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $bgColor],
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['rgb' => 'E2E8F0'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("B{$currentRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sheet->getStyle("C{$currentRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("C{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sheet->getStyle("D{$currentRow}:H{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$currentRow}:H{$currentRow}")->getNumberFormat()->setFormatCode('#,##0');

            $sheet->getStyle("I{$currentRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("I{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sheet->getStyle("J{$currentRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("J{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("J{$currentRow}")->getFont()->setBold(true);

            $currentRow++;
        }

        $lastDataRow = max($firstDataRow, $currentRow - 1);

        if (empty($payrollData['employees'])) {
            $sheet->mergeCells("A{$currentRow}:J{$currentRow}");
            $sheet->setCellValue("A{$currentRow}", 'No employee data found matching criteria.');
            $sheet->getRowDimension($currentRow)->setRowHeight(25);
            $sheet->getStyle("A{$currentRow}:J{$currentRow}")->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ],
                'font' => ['italic' => true, 'color' => ['rgb' => '64748B']],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['rgb' => 'E2E8F0'],
                    ],
                ],
            ]);
            $currentRow++;
        } else {
            // Table Totals Row
            $sheet->getRowDimension($currentRow)->setRowHeight(24);
            $sheet->setCellValue('A' . $currentRow, 'Total');
            $sheet->setCellValue('B' . $currentRow, "=SUM(B{$firstDataRow}:B{$lastDataRow})");
            $sheet->setCellValue('C' . $currentRow, "=SUM(C{$firstDataRow}:C{$lastDataRow})");
            $sheet->setCellValue('D' . $currentRow, "=SUM(D{$firstDataRow}:D{$lastDataRow})");
            $sheet->setCellValue('E' . $currentRow, "=SUM(E{$firstDataRow}:E{$lastDataRow})");
            $sheet->setCellValue('F' . $currentRow, "=SUM(F{$firstDataRow}:F{$lastDataRow})");
            $sheet->setCellValue('G' . $currentRow, "=SUM(G{$firstDataRow}:G{$lastDataRow})");
            $sheet->setCellValue('H' . $currentRow, "=SUM(H{$firstDataRow}:H{$lastDataRow})");
            $sheet->setCellValue('I' . $currentRow, "=SUM(I{$firstDataRow}:I{$lastDataRow})");
            $sheet->setCellValue('J' . $currentRow, "=SUM(J{$firstDataRow}:J{$lastDataRow})");

            $sheet->getStyle("A{$currentRow}:J{$currentRow}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0'],
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['rgb' => 'CBD5E1'],
                    ],
                    'top' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color'       => ['rgb' => '64748B'],
                    ],
                    'bottom' => [
                        'borderStyle' => Border::BORDER_DOUBLE,
                        'color'       => ['rgb' => '64748B'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("B{$currentRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("C{$currentRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("C{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("D{$currentRow}:H{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I{$currentRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("I{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("J{$currentRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("J{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $currentRow++;
        }

        // Spacing before summary block
        $currentRow += 2;

        // Bottom Summary Block:
        // Total Gross Payroll
        // Total Sales Commissions
        // Total Payroll Deductions
        // Total Net Payout
        $sheet->mergeCells("H{$currentRow}:J{$currentRow}");
        $sheet->setCellValue("H{$currentRow}", 'PAYROLL SUMMARY');
        $sheet->getRowDimension($currentRow)->setRowHeight(26);
        $sheet->getStyle("H{$currentRow}:J{$currentRow}")->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size'  => 11,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => '0F172A'],
                ],
            ],
        ]);

        $currentRow++;

        // 1. Total Gross Payroll
        $sheet->getRowDimension($currentRow)->setRowHeight(22);
        $sheet->mergeCells("H{$currentRow}:I{$currentRow}");
        $sheet->setCellValue("H{$currentRow}", 'Total Gross Payroll');
        $sheet->setCellValue("J{$currentRow}", $payrollData['total_base_payroll']);
        $sheet->getStyle("H{$currentRow}:I{$currentRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
        ]);
        $sheet->getStyle("J{$currentRow}")->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
            'numberFormat' => ['formatCode' => '#,##0.00'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
        ]);

        $currentRow++;

        // 2. Total Sales Commissions
        $sheet->getRowDimension($currentRow)->setRowHeight(22);
        $sheet->mergeCells("H{$currentRow}:I{$currentRow}");
        $sheet->setCellValue("H{$currentRow}", 'Total Sales Commissions');
        $sheet->setCellValue("J{$currentRow}", $payrollData['total_commissions']);
        $sheet->getStyle("H{$currentRow}:I{$currentRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
        ]);
        $sheet->getStyle("J{$currentRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '16A34A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
            'numberFormat' => ['formatCode' => '#,##0.00'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
        ]);

        $currentRow++;

        // 3. Total Payroll Deductions
        $sheet->getRowDimension($currentRow)->setRowHeight(22);
        $sheet->mergeCells("H{$currentRow}:I{$currentRow}");
        $sheet->setCellValue("H{$currentRow}", 'Total Payroll Deductions');
        $sheet->setCellValue("J{$currentRow}", $payrollData['total_deductions']);
        $sheet->getStyle("H{$currentRow}:I{$currentRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'DC2626']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF2F2']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
        ]);
        $sheet->getStyle("J{$currentRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'DC2626']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
            'numberFormat' => ['formatCode' => '#,##0.00'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
        ]);

        $currentRow++;

        // 4. Total Net Payout
        $sheet->getRowDimension($currentRow)->setRowHeight(24);
        $sheet->mergeCells("H{$currentRow}:I{$currentRow}");
        $sheet->setCellValue("H{$currentRow}", 'Total Net Payout');
        $sheet->setCellValue("J{$currentRow}", $payrollData['total_net_payroll']);
        $sheet->getStyle("H{$currentRow}:I{$currentRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '16A34A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0FDF4']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '16A34A']]],
        ]);
        $sheet->getStyle("J{$currentRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '16A34A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
            'numberFormat' => ['formatCode' => '#,##0.00'],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0FDF4']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '16A34A']]],
        ]);

        $fileName = "Salary_Estimations_{$monthName}_{$year}.xlsx";

        return response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    /**
     * Core computation of all employee salary estimates and grand totals.
     */
    private function computeCompanyPayroll(int $month, int $year, ?int $filterUserId = null, string $salaryStatus = 'all'): array
    {
        $sessionController = new SessionManagementController();
        $query = User::whereHas('role', fn ($q) => $q->where('name', '!=', 'super_admin'))
            ->with(['designation', 'department', 'role'])
            ->orderBy('name');

        if ($filterUserId) {
            $query->where('id', $filterUserId);
        }

        $users = $query->get();

        $employeeEstimations = [];
        $totalBasePayroll    = 0.0;
        $totalDeductions     = 0.0;
        $totalCommissions    = 0.0;
        $totalNetPayroll     = 0.0;

        foreach ($users as $user) {
            $trackData = $sessionController->calculateMonthlyTrackRecordData($user, $month, $year);

            $hasExpectedSalary = $trackData['hasExpectedSalary'] ?? (!is_null($user->expected_salary_amount) && (float)$user->expected_salary_amount > 0);

            if ($salaryStatus === 'configured' && !$hasExpectedSalary) {
                continue;
            }
            if ($salaryStatus === 'not_configured' && $hasExpectedSalary) {
                continue;
            }
            $baseSalary        = $hasExpectedSalary ? (float)$user->expected_salary_amount : 0.0;
            $deductions        = $hasExpectedSalary ? (float)($trackData['calculatedDeductions'] ?? 0.0) : 0.0;
            $salesCommission   = !is_null($user->sales_commission) ? (float)$user->sales_commission : 0.0;

            if ($hasExpectedSalary) {
                $netSalary = max(0, ($baseSalary - $deductions) + $salesCommission);
            } else {
                $netSalary = $salesCommission > 0 ? $salesCommission : 0.0;
            }

            $totalBasePayroll += $baseSalary;
            $totalDeductions  += $deductions;
            $totalCommissions += $salesCommission;
            $totalNetPayroll  += $netSalary;

            $employeeEstimations[] = [
                'user_id'            => $user->id,
                'name'               => $user->name,
                'email'              => $user->email,
                'designation'        => $user->designation?->name ?: ($user->job_title ?: 'Employee'),
                'department'         => $user->department ? $user->department->name : 'Development',
                'has_expected_salary'=> $hasExpectedSalary,
                'base_salary'        => $baseSalary,
                'working_days'       => $trackData['totalWorkingDays'] ?? 0,
                'days_before_join'   => $trackData['totalDaysBeforeJoin'] ?? 0,
                'leaves'             => $trackData['totalLeaves'] ?? 0,
                'absents'            => $trackData['totalAbsents'] ?? 0,
                'unpaid_leaves'      => $trackData['totalUnpaidLeaves'] ?? 0,
                'holidays'           => $trackData['totalHolidays'] ?? 0,
                'remotes'            => $trackData['totalRemotes'] ?? 0,
                'deductions'         => $deductions,
                'sales_commission'   => $salesCommission,
                'net_salary'         => $netSalary,
                'salary_slip_url'    => route('super-admin.salary-slips.index', [
                    'user_id' => $user->id,
                    'month'   => $month,
                    'year'    => $year,
                ]),
            ];
        }

        return [
            'employees'          => $employeeEstimations,
            'total_base_payroll' => $totalBasePayroll,
            'total_deductions'   => $totalDeductions,
            'total_commissions'  => $totalCommissions,
            'total_net_payroll'  => $totalNetPayroll,
            'employee_count'     => count($employeeEstimations),
        ];
    }
}
