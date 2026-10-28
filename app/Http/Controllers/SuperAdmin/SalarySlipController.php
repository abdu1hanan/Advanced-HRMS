<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SlackMessage;
use App\Models\EmployeeSalarySlipDetail;
use App\Models\LeaveRequest;
use App\Models\AttendanceRecord;
use App\Models\CompanyHoliday;
use App\Models\RemoteWorkLog;
use App\Mail\SalarySlipMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Spatie\Browsershot\Browsershot;

class SalarySlipController extends Controller
{
    public function index(): View
    {
        $users = User::whereHas('role', fn ($q) => $q->where('name', '!=', 'super_admin'))->orderBy('name')->get();
        $letterheadPath = public_path('images/salary_slip_letterhead.png');
        $letterheadUrl = file_exists($letterheadPath) ? asset('images/salary_slip_letterhead.png') . '?v=' . filemtime($letterheadPath) : null;

        // Read salary deduction settings
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

        return view('super-admin.salary-slips.index', compact('users', 'letterheadUrl', 'settings'));
    }

    private function calculateSalarySlipData(User $user, int $month, int $year, ?float $salesCommission = null): array
    {
        $sessionController = new SessionManagementController();
        $trackData = $sessionController->calculateMonthlyTrackRecordData($user, $month, $year);

        $hasExpectedSalary = $trackData['hasExpectedSalary'] ?? (!is_null($user->expected_salary_amount) && (float)$user->expected_salary_amount > 0);
        $baseSalary = $hasExpectedSalary ? (float)$user->expected_salary_amount : null;
        $basicSalary = $hasExpectedSalary ? round($baseSalary * 0.70, 2) : null;
        $houseRentTravel = $hasExpectedSalary ? round($baseSalary * 0.20, 2) : null;
        $medicalAllowance = $hasExpectedSalary ? round($baseSalary * 0.10, 2) : null;
        $grossPay = $baseSalary;
        $deductions = $hasExpectedSalary ? $trackData['calculatedDeductions'] : 0.0;
        
        if ($salesCommission === null) {
            $salesCommission = (float)($user->sales_commission ?? 0);
        }

        if ($hasExpectedSalary) {
            $takeAway = max(0, ($grossPay - $deductions) + $salesCommission);
        } else {
            $takeAway = $salesCommission > 0 ? $salesCommission : null;
        }

        $bankDetail = EmployeeSalarySlipDetail::where('user_id', $user->id)->first();
        $carbonMonth = Carbon::createFromDate($year, $month, 1, 'Asia/Karachi');

        $user->loadMissing(['designation', 'department', 'role']);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'salary_slip_email' => $user->salary_slip_email,
                'effective_email' => $user->salary_slip_email ?: $user->email,
                'designation' => $user->designation?->name ?: ($user->job_title ?: 'Employee'),
                'department' => $user->department ? $user->department->name : 'Development',
                'joining_date' => $user->join_date ? $user->join_date->format('j-F-Y') : ($user->created_at ? $user->created_at->format('j-F-Y') : '—'),
                'address' => $user->address ?: '—',
            ],
            'period' => [
                'month_num' => $month,
                'month_name' => $carbonMonth->format('F'),
                'year' => $year,
                'month_year_label' => $carbonMonth->format('F Y'),
            ],
            'bank_details' => [
                'bank_name' => $bankDetail ? $bankDetail->bank_name : '',
                'account_title' => $bankDetail ? $bankDetail->account_title : '',
                'account_no' => $bankDetail ? $bankDetail->account_no : '',
                'iban' => $bankDetail ? $bankDetail->iban : '',
            ],
            'financial' => [
                'has_expected_salary' => $hasExpectedSalary,
                'base_salary' => $baseSalary,
                'basic_salary' => $basicSalary,
                'house_rent_travel' => $houseRentTravel,
                'medical_allowance' => $medicalAllowance,
                'gross_pay' => $grossPay,
                'sales_commission' => $salesCommission,
                'salary_advance' => 0.0,
                'working_days' => $trackData['totalWorkingDays'],
                'days_before_join' => $trackData['totalDaysBeforeJoin'] ?? 0,
                'leaves' => $trackData['totalLeaves'],
                'absents' => $trackData['totalAbsents'],
                'unpaid_leaves' => $trackData['totalUnpaidLeaves'],
                'holidays' => $trackData['totalHolidays'],
                'remotes' => $trackData['totalRemotes'] ?? 0,
                'deductions' => $deductions,
                'take_away' => $takeAway,
            ],
            'summary' => [
                'expected_time_formatted' => $sessionController->formatSecondsToTime($trackData['totalExpectedSeconds']),
                'time_given_formatted' => $sessionController->formatSecondsToTime($trackData['totalTimeGivenSeconds']),
                'difference_formatted' => $sessionController->formatSecondsToTime(abs($trackData['diffSeconds'])),
                'is_difference_positive' => ($trackData['diffSeconds'] >= 0),
            ],
            'records' => $trackData['records'],
            'letterhead_url' => file_exists(public_path('images/salary_slip_letterhead.png')) ? asset('images/salary_slip_letterhead.png') . '?v=' . filemtime(public_path('images/salary_slip_letterhead.png')) : null,
        ];
    }

    private function formatSecondsToTime(int $totalSeconds): string
    {
        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }

    public function getDetailsApi(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2020|max:2035',
            'sales_commission' => 'nullable|numeric|min:0',
        ]);

        $user = User::findOrFail($request->user_id);
        $salesCommission = $request->filled('sales_commission') ? (float)$request->input('sales_commission') : null;
        $data = $this->calculateSalarySlipData($user, (int)$request->month, (int)$request->year, $salesCommission);

        return response()->json($data);
    }

    public function saveBankDetails(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'salary_slip_email' => 'nullable|email|max:255',
            'bank_name' => 'nullable|string|max:255',
            'account_title' => 'nullable|string|max:255',
            'account_no' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:255',
        ]);

        $user = User::findOrFail($request->user_id);
        if ($request->has('salary_slip_email')) {
            $user->salary_slip_email = $request->salary_slip_email ? strtolower(trim($request->salary_slip_email)) : null;
            $user->save();
        }

        $detail = EmployeeSalarySlipDetail::updateOrCreate(
            ['user_id' => $request->user_id],
            [
                'bank_name' => $request->bank_name,
                'account_title' => $request->account_title,
                'account_no' => $request->account_no,
                'iban' => $request->iban,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Bank details saved successfully!',
            'bank_details' => $detail,
            'salary_slip_email' => $user->salary_slip_email,
        ]);
    }

    public function saveRecipientEmail(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'salary_slip_email' => 'nullable|email|max:255',
        ]);

        $user = User::findOrFail($request->user_id);
        $oldSalarySlipEmail = $user->salary_slip_email;
        $newSalarySlipEmail = $request->salary_slip_email ? strtolower(trim($request->salary_slip_email)) : null;

        $changed = ($oldSalarySlipEmail !== $newSalarySlipEmail);

        $user->salary_slip_email = $newSalarySlipEmail;
        $user->save();

        if ($changed) {
            $user->notify(new \App\Notifications\SalarySlipEmailUpdatedNotification(
                $user->name,
                $oldSalarySlipEmail,
                $newSalarySlipEmail,
                auth()->user()->name,
                true,
                $user->id,
                $user->email
            ));
        }

        $effectiveEmail = $user->salary_slip_email ?: $user->email;

        return response()->json([
            'status' => 'success',
            'message' => 'Salary slip recipient email updated successfully!',
            'salary_slip_email' => $user->salary_slip_email,
            'effective_email' => $effectiveEmail,
        ]);
    }

    public function previewPdf(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2020|max:2035',
            'sales_commission' => 'nullable|numeric|min:0',
        ]);

        $user = User::findOrFail($request->user_id);
        $salesCommission = $request->filled('sales_commission') ? (float)$request->input('sales_commission') : null;
        $data = $this->calculateSalarySlipData($user, (int)$request->month, (int)$request->year, $salesCommission);

        if (!$data['financial']['has_expected_salary']) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Salary is not configured for this employee.'
                ], 422);
            }
            return redirect()->back()->with('error', 'Salary is not configured for this employee.');
        }

        // Validate bank details are filled before showing preview
        $bank = $data['bank_details'];
        if (empty($bank['bank_name']) || empty($bank['account_title']) || (empty($bank['account_no']) && empty($bank['iban']))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kindly fill in the bank details (Bank Name, Account Title, Account No / IBAN) before generating or previewing the salary slip.'
            ], 422);
        }

        $includeSalarySlip = $request->boolean('include_salary_slip', true);
        $includeTracksheet = $request->boolean('include_tracksheet', true);
        $data['include_salary_slip'] = $includeSalarySlip;
        $data['include_tracksheet'] = $includeTracksheet;

        return view('super-admin.salary-slips.preview-page', $data);
    }

    public function sendEmail(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2020|max:2035',
            'sales_commission' => 'nullable|numeric|min:0',
            'include_salary_slip' => 'nullable|boolean',
            'include_tracksheet' => 'nullable|boolean',
        ]);

        $user = User::findOrFail($request->user_id);
        $salesCommission = $request->filled('sales_commission') ? (float)$request->input('sales_commission') : null;
        $includeSalarySlip = $request->boolean('include_salary_slip', true);
        $includeTracksheet = $request->boolean('include_tracksheet', true);

        if (!$includeSalarySlip && !$includeTracksheet) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please select at least one page to send (Salary Slip or Track Sheet).'
            ], 422);
        }

        $data = $this->calculateSalarySlipData($user, (int)$request->month, (int)$request->year, $salesCommission);
        $data['include_salary_slip'] = $includeSalarySlip;
        $data['include_tracksheet'] = $includeTracksheet;

        if (!$data['financial']['has_expected_salary']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Salary is not configured for this employee.'
            ], 422);
        }

        // Validate bank details
        $bank = $data['bank_details'];
        if (empty($bank['bank_name']) || empty($bank['account_title']) || (empty($bank['account_no']) && empty($bank['iban']))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kindly fill in the bank details (Bank Name, Account Title, Account No / IBAN) before sending the salary slip.'
            ], 422);
        }

        try {
            // Render the EXACT same preview Blade as HTML
            $html = view('super-admin.salary-slips.preview-page', $data)->render();

            // Headless Chrome prints the preview HTML to PDF — pixel-perfect, no second template needed
            $browsershot = Browsershot::html($html)
                ->noSandbox()
                ->waitUntilNetworkIdle()
                ->format('A4')
                ->showBackground()
                ->margins(0, 0, 0, 0);

            if ($nodePath = config('services.salary_slip.node_path')) {
                $browsershot->setNodeBinary($nodePath);
            }
            if ($npmPath = config('services.salary_slip.npm_path')) {
                $browsershot->setNpmBinary($npmPath);
            }
            if ($chromePath = config('services.salary_slip.chrome_path')) {
                $browsershot->setChromePath($chromePath);
            }
            if ($nodeModulesPath = config('services.salary_slip.node_modules_path')) {
                $browsershot->setNodeModulePath($nodeModulesPath);
            }

            $pdfContent = $browsershot->pdf();

            $recipientEmail = $user->salary_slip_email ?: $user->email;

            Mail::to($recipientEmail)->send(new SalarySlipMail(
                $user,
                $data['period']['month_name'],
                (string)$data['period']['year'],
                $pdfContent,
                $includeSalarySlip,
                $includeTracksheet
            ));

            // Dispatch in-app notification to employee linking to their Track Record page
            $user->notify(new \App\Notifications\SalarySlipIssuedNotification(
                $data['period']['month_name'],
                (int)$data['period']['year'],
                $recipientEmail,
                (int)$request->month
            ));

            return response()->json([
                'status' => 'success',
                'message' => 'Salary slip email dispatched successfully to ' . $recipientEmail . '!'
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Salary slip email generation failed: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'exception' => $e
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate PDF or send email: ' . $e->getMessage()
            ], 500);
        }
    }

    public function uploadLetterhead(Request $request): JsonResponse
    {
        $request->validate([
            'letterhead_image' => 'required|image|mimes:png,jpg,jpeg|max:2048',
        ]);

        $file = $request->file('letterhead_image');
        $destinationPath = public_path('images');
        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        $file->move($destinationPath, 'salary_slip_letterhead.png');

        $storageDir = storage_path('app/public/salary_slips');
        if (!file_exists($storageDir)) {
            mkdir($storageDir, 0755, true);
        }
        copy(public_path('images/salary_slip_letterhead.png'), $storageDir . '/letterhead.png');

        $letterheadPath = public_path('images/salary_slip_letterhead.png');
        $letterheadUrl = file_exists($letterheadPath) ? asset('images/salary_slip_letterhead.png') . '?v=' . filemtime($letterheadPath) : null;

        return response()->json([
            'status' => 'success',
            'message' => 'Salary slip letterhead background image uploaded and saved successfully!',
            'letterhead_url' => $letterheadUrl
        ]);
    }
}
