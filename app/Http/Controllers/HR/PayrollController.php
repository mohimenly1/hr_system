<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\TeacherContract;
use App\Models\Employee;
use App\Models\Teacher;
use App\Models\Department;
use App\Models\Payslip;
use App\Models\PayslipItem;
use App\Models\PayrollExpense;
use App\Models\ShiftAssignment;
use App\Models\TimetableEntry;
use App\Models\DefaultShiftSetting;
use App\Services\AttendanceComparisonService;
use App\Services\DeductionApplicationService;
use App\Exports\PayrollPreviewExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class PayrollController extends Controller
{
    protected $comparisonService;
    protected $deductionService;

    public function __construct(
        AttendanceComparisonService $comparisonService,
        DeductionApplicationService $deductionService
    ) {
        $this->comparisonService = $comparisonService;
        $this->deductionService = $deductionService;
    }

    /**
     * Display a listing of the payslips.
     */
    public function index()
    {
        $payslips = Payslip::with(['employee.user', 'teacher.user'])
            ->withTrashed()
            ->latest()
            ->paginate(15);
        return Inertia::render('HR/Payroll/Index', [
            'payslips' => $payslips
        ]);
    }

    /**
     * Soft delete a payslip
     */
    public function destroy($id)
    {
        $payslip = Payslip::findOrFail($id);

        // Soft delete the payslip
        $payslip->delete();

        return Redirect::route('hr.payroll.index')
            ->with('success', 'تم حذف قسيمة الراتب بنجاح. يمكن استرجاعها لاحقاً من سلة المحذوفات.');
    }

    /**
     * Restore a soft deleted payslip
     */
    public function restore($id)
    {
        $payslip = Payslip::withTrashed()->findOrFail($id);

        // Restore the payslip
        $payslip->restore();

        return Redirect::route('hr.payroll.index')
            ->with('success', 'تم استرجاع قسيمة الراتب بنجاح.');
    }

    /**
     * Permanently delete a payslip
     */
    public function forceDelete($id)
    {
        $payslip = Payslip::withTrashed()->findOrFail($id);

        // Permanently delete the payslip
        $payslip->forceDelete();

        return Redirect::route('hr.payroll.index')
            ->with('success', 'تم حذف قسيمة الراتب نهائياً.');
    }

    /**
     * Show the form for creating a new payroll run.
     */
    public function create()
    {
        return Inertia::render('HR/Payroll/Generate');
    }

    public function show($id)
    {
        // Allow viewing soft deleted payslips in HR view
        $payslip = Payslip::withTrashed()->findOrFail($id);

        // Eager load all related data for the view
        $payslip->load([
            'employee.user',
            'employee.department',
            'teacher.user',
            'teacher.department',
            'contract',
            'teacherContract',
            'items'
        ]);

        return Inertia::render('HR/Payroll/Show', [
            'payslip' => $payslip
        ]);
    }
    /**
     * Store a newly created payroll run in storage.
     */
    public function store(Request $request)
    {
        // 1. التحقق من صحة المدخلات (الشهر والسنة)
        // إذا فشل هذا التحقق، سيقوم Laravel بإعادة توجيهك تلقائياً مع إرسال أخطاء التحقق
        // والتي يجب أن تظهر الآن في صفحة Generate.vue بفضل التحديث الأخير.
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2020',
        ]);

        $month = $request->input('month');
        $year = $request->input('year');
        $generationDate = Carbon::create($year, $month);

        // 2. البحث عن الموظفين الذين لديهم عقود سارية
        $activeContracts = Contract::where('status', 'active')
            ->where('start_date', '<=', $generationDate->endOfMonth())
            ->where(function ($query) use ($generationDate) {
                $query->whereNull('end_date')
                      ->orWhere('end_date', '>=', $generationDate->startOfMonth());
            })
            ->get();

        // 3. هذا هو السبب الأكثر احتمالاً للمشكلة!
        // إذا لم يتم العثور على أي عقود سارية، سيتم إعادة توجيهك إلى صفحة الإنشاء
        // مع رسالة خطأ من نوع "flash message".
        if ($activeContracts->isEmpty()) {
            return Redirect::route('hr.payroll.create')->with('error', 'لا يوجد موظفين بعقود سارية لهذا الشهر.');
        }

        $generatedCount = 0;
        foreach ($activeContracts as $contract) {
            $existingPayslip = Payslip::where('employee_id', $contract->employee_id)
                ->where('month', $month)
                ->where('year', $year)
                ->exists();

            if ($existingPayslip) {
                continue;
            }

            $grossSalary = $contract->total_salary;

            // Calculate period dates
            $startDate = $generationDate->copy()->startOfMonth();
            $endDate = $generationDate->copy()->endOfMonth();

            // Get attendance comparison
            $comparison = $this->comparisonService->compareAttendanceWithTimetable(
                $contract->employee_id,
                'App\Models\Employee',
                $startDate,
                $endDate
            );

            // Apply deduction rules
            $deductions = $this->deductionService->applyDeductionRules(
                $comparison,
                $contract->employee_id,
                'App\Models\Employee',
                $startDate,
                $endDate
            );

            $totalDeductions = $deductions['total_deduction'] ?? 0;
            $netSalary = $grossSalary - $totalDeductions;

            $payslip = Payslip::create([
                'employee_id' => $contract->employee_id,
                'contract_id' => $contract->id,
                'month' => $month,
                'year' => $year,
                'issue_date' => now(),
                'gross_salary' => $grossSalary,
                'total_deductions' => $totalDeductions,
                'net_salary' => $netSalary,
                'status' => 'pending',
            ]);

            // Add earnings
            $payslip->items()->create(['type' => 'earning', 'description' => 'الراتب الأساسي', 'amount' => $contract->basic_salary]);
            if ($contract->housing_allowance > 0) {
                $payslip->items()->create(['type' => 'earning', 'description' => 'بدل سكن', 'amount' => $contract->housing_allowance]);
            }
            if ($contract->transportation_allowance > 0) {
                $payslip->items()->create(['type' => 'earning', 'description' => 'بدل مواصلات', 'amount' => $contract->transportation_allowance]);
            }
            if ($contract->other_allowances > 0) {
                 $payslip->items()->create(['type' => 'earning', 'description' => 'بدلات أخرى', 'amount' => $contract->other_allowances]);
            }

            // Add deductions from rules
            if (isset($deductions['applied_deductions']) && count($deductions['applied_deductions']) > 0) {
                foreach ($deductions['applied_deductions'] as $deduction) {
                    $payslip->items()->create([
                        'type' => 'deduction',
                        'description' => $deduction['rule']['name'] . ($deduction['rule']['description'] ? ' - ' . $deduction['rule']['description'] : ''),
                        'amount' => $deduction['deduction_amount'],
                    ]);
                }
            }

            $generatedCount++;
        }

        if ($generatedCount > 0) {
            return Redirect::route('hr.payroll.index')->with('success', "تم إنشاء {$generatedCount} قسيمة راتب بنجاح.");
        } else {
            return Redirect::route('hr.payroll.create')->with('info', 'تم إنشاء الرواتب لهذا الشهر مسبقاً.');
        }
    }

    /**
     * Show the new payroll processing interface
     */
    public function process(Request $request)
    {
        $departments = Department::orderBy('name')->get(['id', 'name']);

        return Inertia::render('HR/Payroll/Process', [
            'departments' => $departments,
            'filters' => [
                'month' => $request->get('month', Carbon::now()->month),
                'year' => $request->get('year', Carbon::now()->year),
                'department_id' => $request->get('department_id'),
            ],
            'preview' => null, // Initialize preview as null
        ]);
    }

    /**
     * Get available personnel for payroll processing
     */
    public function getPersonnel(Request $request)
    {
        try {
            $month = (int) $request->input('month', Carbon::now()->month);
            $year = (int) $request->input('year', Carbon::now()->year);
            $departmentId = $request->input('department_id');
            $search = $request->input('search', '');
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', 15);

            // Handle boolean conversion properly
            $includeEmployeesInput = $request->input('include_employees', '1');
            $includeTeachersInput = $request->input('include_teachers', '1');
            $includeEmployees = in_array(strtolower($includeEmployeesInput), ['1', 'true', 'yes', 'on'], true);
            $includeTeachers = in_array(strtolower($includeTeachersInput), ['1', 'true', 'yes', 'on'], true);

            $generationDate = Carbon::create($year, $month, 1);
            $personnel = collect();

        // Get employees
        if ($includeEmployees) {
            $employeeQuery = Contract::where('status', 'active')
                ->where('start_date', '<=', $generationDate->copy()->endOfMonth())
                ->where(function ($query) use ($generationDate) {
                    $query->whereNull('end_date')
                          ->orWhere('end_date', '>=', $generationDate->copy()->startOfMonth());
                })
                ->with(['employee.user', 'employee.department']);

            if ($departmentId) {
                $employeeQuery->whereHas('employee', function ($q) use ($departmentId) {
                    $q->where('department_id', $departmentId);
                });
            }

            $employeeContracts = $employeeQuery->get();

            foreach ($employeeContracts as $contract) {
                // Check if payslip already exists
                $existingPayslip = Payslip::where('employee_id', $contract->employee_id)
                    ->where('month', $month)
                    ->where('year', $year)
                    ->exists();

                if (!$existingPayslip && $contract->employee && $contract->employee->user) {
                    // Check if employee has shift assignment
                    $hasSchedule = \App\Models\ShiftAssignment::where('shiftable_id', $contract->employee_id)
                        ->where('shiftable_type', 'App\Models\Employee')
                        ->exists();

                    // Check if employee has timetable entries
                    $hasTimetable = \App\Models\TimetableEntry::where('schedulable_id', $contract->employee_id)
                        ->where('schedulable_type', 'App\Models\Employee')
                        ->exists();

                    $hasScheduleAssigned = $hasSchedule || $hasTimetable;

                    $personnel->push([
                        'id' => $contract->employee_id,
                        'type' => 'employee',
                        'type_label' => 'موظف',
                        'name' => $contract->employee->user->full_name ?? $contract->employee->user->name ?? 'غير محدد',
                        'department' => $contract->employee->department ? $contract->employee->department->name : 'غير محدد',
                        'salary' => $contract->total_salary ?? 0,
                        'basic_salary' => $contract->basic_salary ?? 0,
                        'contract_type' => $contract->contract_type ?? 'غير محدد',
                        'work_type' => 'شهري', // Employees are always monthly
                        'contract_id' => $contract->id,
                        'has_schedule' => $hasScheduleAssigned,
                    ]);
                }
            }
        }

        // Get teachers
        if ($includeTeachers) {
            $teacherQuery = TeacherContract::where('status', 'active')
                ->where('start_date', '<=', $generationDate->copy()->endOfMonth())
                ->where(function ($query) use ($generationDate) {
                    $query->whereNull('end_date')
                          ->orWhere('end_date', '>=', $generationDate->copy()->startOfMonth());
                })
                ->with(['teacher.user', 'teacher.department']);

            if ($departmentId) {
                $teacherQuery->whereHas('teacher', function ($q) use ($departmentId) {
                    $q->where('department_id', $departmentId);
                });
            }

            $teacherContracts = $teacherQuery->get();

            foreach ($teacherContracts as $contract) {
                // Check if payslip already exists
                // Check if teacher_id column exists in the payslips table
                $existingPayslip = false;
                if (Schema::hasColumn('payslips', 'teacher_id')) {
                    $existingPayslip = Payslip::where('teacher_id', $contract->teacher_id)
                        ->where('month', $month)
                        ->where('year', $year)
                        ->exists();
                } else {
                    // If teacher_id column doesn't exist, skip the check
                    // This means migration hasn't been run yet
                    Log::warning('teacher_id column not found in payslips table. Migration may need to be run.');
                }

                if (!$existingPayslip && $contract->teacher && $contract->teacher->user) {
                    $salary = $contract->salary_type === 'monthly'
                        ? ($contract->salary_amount ?? 0)
                        : (($contract->hourly_rate ?? 0) * (($contract->working_hours_per_week ?? 0) * 4.33));

                    // Check if teacher has shift assignment
                    $hasSchedule = ShiftAssignment::where('shiftable_id', $contract->teacher_id)
                        ->where('shiftable_type', 'App\Models\Teacher')
                        ->exists();

                    // Check if teacher has timetable entries
                    $hasTimetable = TimetableEntry::where('schedulable_id', $contract->teacher_id)
                        ->where('schedulable_type', 'App\Models\Teacher')
                        ->exists();

                    $hasScheduleAssigned = $hasSchedule || $hasTimetable;

                    $personnel->push([
                        'id' => $contract->teacher_id,
                        'type' => 'teacher',
                        'type_label' => 'معلم',
                        'name' => $contract->teacher->user->full_name ?? $contract->teacher->user->name ?? 'غير محدد',
                        'department' => $contract->teacher->department ? $contract->teacher->department->name : 'غير محدد',
                        'salary' => $salary,
                        'basic_salary' => $contract->salary_type === 'monthly' ? ($contract->salary_amount ?? 0) : ($contract->hourly_rate ?? 0),
                        'contract_type' => $contract->contract_type ?? 'غير محدد',
                        'work_type' => $contract->salary_type === 'monthly' ? 'شهري' : 'بالساعات',
                        'contract_id' => $contract->id,
                        'has_schedule' => $hasScheduleAssigned,
                    ]);
                }
            }
            }

            // Apply search filter
            if (!empty($search)) {
                $personnel = $personnel->filter(function ($person) use ($search) {
                    return stripos($person['name'], $search) !== false ||
                           stripos($person['department'], $search) !== false;
                });
            }

            // Paginate results
            $total = $personnel->count();
            $personnel = $personnel->values();
            $offset = ($page - 1) * $perPage;
            $paginatedPersonnel = $personnel->slice($offset, $perPage)->values()->all();
            $lastPage = ceil($total / $perPage);

            // Return JSON response with pagination
            return response()->json([
                'personnel' => $paginatedPersonnel,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => $lastPage,
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $total),
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching personnel: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json([
                'error' => 'حدث خطأ أثناء جلب الموظفين والمعلمين: ' . $e->getMessage(),
                'personnel' => []
            ], 500);
        }
    }

    /**
     * Preview payroll before processing
     */
    public function preview(Request $request)
    {
        $month = (int) $request->input('month');
        $year = (int) $request->input('year');
        $personnel = $request->input('personnel', []);
        $mode = $request->input('mode', 'with_review');

        // Handle different data formats that might come from frontend
        // Sometimes data comes as: [{"id":"21"},{"type":"employee"},{"contract_id":"22"}]
        // We need to normalize it to: [{"id":21,"type":"employee","contract_id":22}]
        $normalizedPersonnel = [];
        $tempPerson = [];
        $originalPersonnel = $personnel;

        // First, check if data is already in correct format
        $isCorrectFormat = true;
        foreach ($personnel as $item) {
            if (!is_array($item) || !isset($item['id']) || !isset($item['type'])) {
                $isCorrectFormat = false;
                break;
            }
        }

        if (!$isCorrectFormat) {
            // Data is in wrong format, try to normalize it
            // Collect all fields from all items
            $allIds = [];
            $allTypes = [];
            $allContractIds = [];

            foreach ($personnel as $item) {
                if (is_array($item)) {
                    if (isset($item['id'])) {
                        $allIds[] = (int) $item['id'];
                    }
                    if (isset($item['type'])) {
                        $allTypes[] = $item['type'];
                    }
                    if (isset($item['contract_id'])) {
                        $allContractIds[] = (int) $item['contract_id'];
                    }
                }
            }

            // If we have matching counts, create normalized array
            if (count($allIds) === count($allTypes) && count($allIds) === count($allContractIds) && count($allIds) > 0) {
                for ($i = 0; $i < count($allIds); $i++) {
                    $normalizedPersonnel[] = [
                        'id' => $allIds[$i],
                        'type' => $allTypes[$i],
                        'contract_id' => $allContractIds[$i]
                    ];
                }
                $personnel = $normalizedPersonnel;
            } else {
                // Try to match by grouping consecutive items
                for ($i = 0; $i < count($personnel); $i += 3) {
                    if ($i + 2 < count($personnel)) {
                        $idItem = $personnel[$i];
                        $typeItem = $personnel[$i + 1];
                        $contractItem = $personnel[$i + 2];

                        $personId = null;
                        $personType = null;
                        $contractId = null;

                        if (is_array($idItem) && isset($idItem['id'])) {
                            $personId = (int) $idItem['id'];
                        }
                        if (is_array($typeItem) && isset($typeItem['type'])) {
                            $personType = $typeItem['type'];
                        }
                        if (is_array($contractItem) && isset($contractItem['contract_id'])) {
                            $contractId = (int) $contractItem['contract_id'];
                        }

                        if ($personId && $personType && $contractId) {
                            $normalizedPersonnel[] = [
                                'id' => $personId,
                                'type' => $personType,
                                'contract_id' => $contractId
                            ];
                        }
                    }
                }

                if (!empty($normalizedPersonnel)) {
                    $personnel = $normalizedPersonnel;
                }
            }
        }

        Log::info('Preview request received', [
            'month' => $month,
            'year' => $year,
            'mode' => $mode,
            'original_personnel_count' => count($request->input('personnel', [])),
            'normalized_personnel_count' => count($normalizedPersonnel),
            'final_personnel_count' => count($personnel),
            'personnel' => $personnel
        ]);

        $generationDate = Carbon::create($year, $month);
        $startDate = $generationDate->copy()->startOfMonth();
        $endDate = $generationDate->copy()->endOfMonth();

        $preview = [];
        $totalGross = 0;
        $totalDeductions = 0;
        $totalNet = 0;

        foreach ($personnel as $index => $person) {
            // Validate required fields - expect proper array format: [{"id":21,"type":"employee","contract_id":22}]
            if (!is_array($person)) {
                Log::warning('Invalid personnel data format in preview - not an array', [
                    'index' => $index,
                    'person' => $person,
                    'type' => gettype($person)
                ]);
                continue;
            }

            // Validate required fields exist
            if (!isset($person['id']) || !isset($person['type'])) {
                Log::warning('Invalid personnel data in preview - missing required fields', [
                    'index' => $index,
                    'person' => $person,
                    'has_id' => isset($person['id']),
                    'has_type' => isset($person['type']),
                    'has_contract_id' => isset($person['contract_id']),
                ]);
                continue;
            }

            $personId = (int) $person['id'];
            $personType = $person['type'];
            $contractId = isset($person['contract_id']) ? (int) $person['contract_id'] : null;

            Log::info('Processing personnel for preview', [
                'person_id' => $personId,
                'person_type' => $personType,
                'contract_id' => $contractId,
                'person_data' => $person
            ]);

            // If contract_id is not provided, try to find the active contract
            if (!$contractId) {
                if ($personType === 'employee') {
                    $contract = Contract::where('employee_id', $personId)
                        ->where('status', 'active')
                        ->latest()
                        ->first();
                    if ($contract) {
                        $contractId = $contract->id;
                        Log::info('Found active contract for employee', ['employee_id' => $personId, 'contract_id' => $contractId]);
                    }
                } else {
                    $contract = TeacherContract::where('teacher_id', $personId)
                        ->where('status', 'active')
                        ->latest()
                        ->first();
                    if ($contract) {
                        $contractId = $contract->id;
                        Log::info('Found active contract for teacher', ['teacher_id' => $personId, 'contract_id' => $contractId]);
                    }
                }
            }

            if (!$contractId) {
                Log::warning('Missing contract_id for personnel', ['person_id' => $personId, 'type' => $personType]);
                continue;
            }

            $modelClass = $personType === 'employee' ? 'App\Models\Employee' : 'App\Models\Teacher';

            // Get contract
            if ($personType === 'employee') {
                $contract = Contract::with('employee.user')->find($contractId);
                if (!$contract) {
                    Log::warning('Contract not found for employee', ['contract_id' => $contractId, 'employee_id' => $personId]);
                    continue;
                }
                if (!$contract->employee || !$contract->employee->user) {
                    Log::warning('Contract employee or user not found', ['contract_id' => $contractId]);
                    continue;
                }
                $grossSalary = $contract->total_salary;
                // Build full name from employee data
                $firstName = $contract->employee->user->name ?? '';
                $middleName = $contract->employee->middle_name ?? '';
                $lastName = $contract->employee->last_name ?? '';
                $personName = trim("{$firstName} {$middleName} {$lastName}") ?: $firstName;
            } else {
                $contract = TeacherContract::with('teacher.user')->find($contractId);
                if (!$contract) {
                    Log::warning('Contract not found for teacher', ['contract_id' => $contractId, 'teacher_id' => $personId]);
                    continue;
                }
                if (!$contract->teacher || !$contract->teacher->user) {
                    Log::warning('Contract teacher or user not found', ['contract_id' => $contractId]);
                    continue;
                }
                $grossSalary = $contract->salary_type === 'monthly'
                    ? $contract->salary_amount
                    : ($contract->hourly_rate * ($contract->working_hours_per_week * 4.33));
                // Build full name from teacher data
                $firstName = $contract->teacher->user->name ?? '';
                $middleName = $contract->teacher->middle_name ?? '';
                $lastName = $contract->teacher->last_name ?? '';
                $personName = trim("{$firstName} {$middleName} {$lastName}") ?: $firstName;
            }

            Log::info('Contract found and processed', [
                'person_id' => $personId,
                'person_type' => $personType,
                'contract_id' => $contractId,
                'gross_salary' => $grossSalary,
                'person_name' => $personName
            ]);

            $deductions = ['total_deduction' => 0, 'applied_deductions' => [], 'not_applied_rules' => []];
            $comparison = null;
            $attendanceSummary = null;
            $scheduleInfo = null;
            $constraintsInfo = null;
            $leavesInfo = null;

            if ($mode === 'with_review') {
                // Get comparison and deductions
                $comparison = $this->comparisonService->compareAttendanceWithTimetable(
                    $personId,
                    $modelClass,
                    $startDate,
                    $endDate
                );

                $deductions = $this->deductionService->applyDeductionRules(
                    $comparison,
                    $personId,
                    $modelClass,
                    $startDate,
                    $endDate
                );

                // Extract attendance summary from comparison
                $attendanceSummary = $comparison['summary'] ?? null;

                // Get schedule information
                $scheduleInfo = $this->getScheduleInfo($personId, $personType, $startDate, $endDate);

                Log::info('Schedule info retrieved', [
                    'person_id' => $personId,
                    'person_type' => $personType,
                    'schedule_info' => $scheduleInfo,
                ]);

                // Get constraints information
                $constraintsInfo = $this->getConstraintsInfo($personId, $personType);

                // Get leaves information
                $leavesInfo = $this->getLeavesInfo($personId, $personType, $startDate, $endDate);
            }

            $netSalary = $grossSalary - ($deductions['total_deduction'] ?? 0);

            // Full name is already built in personName above
            $fullName = $personName;

            $preview[] = [
                'id' => $personId,
                'type' => $personType,
                'name' => $personName,
                'full_name' => $fullName ?? $personName,
                'contract_id' => $contractId,
                'gross_salary' => $grossSalary,
                'deductions' => $deductions,
                'attendance_summary' => $attendanceSummary,
                'attendance_comparison' => $comparison['comparisons'] ?? [],
                'schedule_info' => $scheduleInfo,
                'constraints_info' => $constraintsInfo,
                'leaves_info' => $leavesInfo,
                'net_salary' => $netSalary,
                'apply_deductions' => true, // Default to applying deductions
            ];

            $totalGross += $grossSalary;
            $totalDeductions += ($deductions['total_deduction'] ?? 0);
            $totalNet += $netSalary;
        }

        Log::info('Preview processing completed', [
            'preview_count' => count($preview),
            'total_gross' => $totalGross,
            'total_deductions' => $totalDeductions,
            'total_net' => $totalNet,
        ]);

        $departments = Department::orderBy('name')->get(['id', 'name']);

        return Inertia::render('HR/Payroll/Process', [
            'departments' => $departments,
            'filters' => [
                'month' => $month,
                'year' => $year,
                'department_id' => null,
            ],
            'preview' => [
                'preview' => $preview,
                'summary' => [
                    'total_gross' => $totalGross,
                    'total_deductions' => $totalDeductions,
                    'total_net' => $totalNet,
                    'count' => count($preview),
                ],
            ],
        ]);
    }

    /**
     * Store processed payroll
     */
    public function processStore(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2020',
            'personnel' => 'required|array|min:1',
            'personnel.*.id' => 'required',
            'personnel.*.type' => 'required|in:employee,teacher',
            'personnel.*.contract_id' => 'required',
            'personnel.*.apply_deductions' => 'boolean',
            'mode' => 'required|in:manual,with_review',
            'deductions_overridden' => 'boolean',
            'override_reason' => 'nullable|string',
            'additional_earnings' => 'nullable|array',
        ]);

        $month = $request->input('month');
        $year = $request->input('year');
        $mode = $request->input('mode');
        $personnel = $request->input('personnel');
        $deductionsOverridden = $request->input('deductions_overridden', false);
        $overrideReason = $request->input('override_reason');
        $additionalEarnings = $request->input('additional_earnings', []);

        $generationDate = Carbon::create($year, $month);
        $startDate = $generationDate->copy()->startOfMonth();
        $endDate = $generationDate->copy()->endOfMonth();

        // Check if payroll expense already exists for this month/year
        $payrollExpense = PayrollExpense::where('month', $month)
            ->where('year', $year)
            ->first();

        if (!$payrollExpense) {
            // Create new payroll expense record
            $payrollExpense = PayrollExpense::create([
                'month' => $month,
                'year' => $year,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);
        } else {
            // Update existing record
            $payrollExpense->update([
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);
        }

        $employeesCount = 0;
        $teachersCount = 0;
        $totalAmount = 0;

        DB::beginTransaction();
        try {
        foreach ($personnel as $person) {
            // Validate required fields
            if (!isset($person['id']) || !isset($person['type'])) {
                Log::warning('Invalid personnel data in processStore', ['person' => $person]);
                continue;
            }

            $personId = $person['id'];
            $personType = $person['type'];
            $contractId = $person['contract_id'] ?? null;

            if (!$contractId) {
                Log::warning('Missing contract_id for personnel in processStore', ['person_id' => $personId, 'type' => $personType]);
                continue;
            }

            $modelClass = $personType === 'employee' ? 'App\Models\Employee' : 'App\Models\Teacher';

            // Get contract
            if ($personType === 'employee') {
                $contract = Contract::find($contractId);
                if (!$contract) {
                    Log::warning('Contract not found for employee', ['contract_id' => $contractId, 'employee_id' => $personId]);
                    continue;
                }
                $grossSalary = $contract->total_salary;
                $employeesCount++;
            } else {
                $contract = TeacherContract::find($contractId);
                if (!$contract) {
                    Log::warning('Contract not found for teacher', ['contract_id' => $contractId, 'teacher_id' => $personId]);
                    continue;
                }
                $grossSalary = $contract->salary_type === 'monthly'
                    ? $contract->salary_amount
                    : ($contract->hourly_rate * ($contract->working_hours_per_week * 4.33));
                $teachersCount++;
            }

                $deductions = ['total_deduction' => 0, 'applied_deductions' => []];

                // Check if deductions should be applied for this person
                $applyDeductions = $person['apply_deductions'] ?? true;

                if ($mode === 'with_review' && !$deductionsOverridden && $applyDeductions) {
                    // Get comparison and deductions
                    $comparison = $this->comparisonService->compareAttendanceWithTimetable(
                        $personId,
                        $modelClass,
                        $startDate,
                        $endDate
                    );

                    $deductions = $this->deductionService->applyDeductionRules(
                        $comparison,
                        $personId,
                        $modelClass,
                        $startDate,
                        $endDate
                    );
                }

                // Calculate additional earnings for this person
                $personAdditionalEarnings = collect($additionalEarnings)
                    ->where('person_id', $personId)
                    ->where('person_type', $personType)
                    ->sum('amount');

                $totalEarnings = $personAdditionalEarnings;
                // Only apply deductions if apply_deductions is true
                $totalDeductions = ($applyDeductions && isset($deductions['total_deduction'])) ? $deductions['total_deduction'] : 0;
                $netSalary = $grossSalary + $totalEarnings - $totalDeductions;

                // Create payslip
                $payslipData = [
                    'month' => $month,
                    'year' => $year,
                    'issue_date' => now(),
                    'gross_salary' => $grossSalary,
                    'total_earnings' => $totalEarnings,
                    'total_deductions' => $totalDeductions,
                    'net_salary' => $netSalary,
                    'status' => 'pending',
                    'is_manual' => $mode === 'manual',
                    'deductions_overridden' => $deductionsOverridden,
                    'override_reason' => $overrideReason,
                    'payroll_expense_id' => $payrollExpense->id,
                ];

                if ($personType === 'employee') {
                    $payslipData['employee_id'] = $personId;
                    $payslipData['contract_id'] = $contract->id;
                } else {
                    $payslipData['teacher_id'] = $personId;
                    $payslipData['teacher_contract_id'] = $contract->id;
                }

                $payslip = Payslip::create($payslipData);

                // Add earnings from contract
                if ($personType === 'employee') {
                    $payslip->items()->create(['type' => 'earning', 'description' => 'الراتب الأساسي', 'amount' => $contract->basic_salary]);
                    if ($contract->housing_allowance > 0) {
                        $payslip->items()->create(['type' => 'earning', 'description' => 'بدل سكن', 'amount' => $contract->housing_allowance]);
                    }
                    if ($contract->transportation_allowance > 0) {
                        $payslip->items()->create(['type' => 'earning', 'description' => 'بدل مواصلات', 'amount' => $contract->transportation_allowance]);
                    }
                    if ($contract->other_allowances > 0) {
                        $payslip->items()->create(['type' => 'earning', 'description' => 'بدلات أخرى', 'amount' => $contract->other_allowances]);
                    }
                } else {
                    $payslip->items()->create(['type' => 'earning', 'description' => 'الراتب الأساسي', 'amount' => $grossSalary]);
                }

                // Add additional earnings
                collect($additionalEarnings)
                    ->where('person_id', $personId)
                    ->where('person_type', $personType)
                    ->each(function ($earning) use ($payslip) {
                        $payslip->items()->create([
                            'type' => 'earning',
                            'description' => $earning['description'] ?? 'إضافي',
                            'amount' => $earning['amount'],
                        ]);
                    });

                // Add deductions from rules
                if ($mode === 'with_review' && !$deductionsOverridden && isset($deductions['applied_deductions'])) {
                    foreach ($deductions['applied_deductions'] as $deduction) {
                        $payslip->items()->create([
                            'type' => 'deduction',
                            'description' => $deduction['rule']['name'] . ($deduction['rule']['description'] ? ' - ' . $deduction['rule']['description'] : ''),
                            'amount' => $deduction['deduction_amount'],
                        ]);
                    }
                }

                $totalAmount += $netSalary;
            }

            // Update payroll expense
            $payrollExpense->update([
                'total_amount' => $totalAmount,
                'total_payslips' => count($personnel),
                'employees_count' => $employeesCount,
                'teachers_count' => $teachersCount,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            DB::commit();

            return Redirect::route('hr.payroll.index')->with('success', "تم صرف رواتب {$employeesCount} موظف و {$teachersCount} معلم بنجاح.");
        } catch (\Exception $e) {
            DB::rollBack();
            return Redirect::back()->with('error', 'حدث خطأ أثناء صرف الرواتب: ' . $e->getMessage());
        }
    }

    /**
     * Display payroll expenses (treasury)
     */
    public function expenses(Request $request)
    {
        $month = $request->get('month');
        $year = $request->get('year', Carbon::now()->year);

        $query = PayrollExpense::query();

        if ($month) {
            $query->where('month', $month);
        }
        if ($year) {
            $query->where('year', $year);
        }

        $expenses = $query->with('creator')->orderBy('year', 'desc')->orderBy('month', 'desc')->paginate(15);

        // Calculate summary
        $summary = [
            'total_amount' => PayrollExpense::where('status', 'completed')
                ->when($month, fn($q) => $q->where('month', $month))
                ->when($year, fn($q) => $q->where('year', $year))
                ->sum('total_amount'),
            'total_payslips' => PayrollExpense::where('status', 'completed')
                ->when($month, fn($q) => $q->where('month', $month))
                ->when($year, fn($q) => $q->where('year', $year))
                ->sum('total_payslips'),
            'total_employees' => PayrollExpense::where('status', 'completed')
                ->when($month, fn($q) => $q->where('month', $month))
                ->when($year, fn($q) => $q->where('year', $year))
                ->sum('employees_count'),
            'total_teachers' => PayrollExpense::where('status', 'completed')
                ->when($month, fn($q) => $q->where('month', $month))
                ->when($year, fn($q) => $q->where('year', $year))
                ->sum('teachers_count'),
        ];

        return Inertia::render('HR/Payroll/Expenses', [
            'expenses' => $expenses,
            'summary' => $summary,
            'filters' => [
                'month' => $month,
                'year' => $year,
            ],
        ]);
    }

    /**
     * Show expense details
     */
    public function showExpense(PayrollExpense $expense)
    {
        $expense->load(['payslips.employee.user', 'payslips.teacher.user', 'creator']);

        return Inertia::render('HR/Payroll/ExpenseShow', [
            'expense' => $expense,
        ]);
    }

    /**
     * Get schedule information for a person
     */
    private function getScheduleInfo($personId, string $personType, Carbon $startDate, Carbon $endDate): array
    {
        // Convert personType to full model class name
        $modelClass = $personType === 'employee' ? 'App\Models\Employee' : ($personType === 'teacher' ? 'App\Models\Teacher' : $personType);

        // Priority order for getting working days:
        // 1. Department default shift settings (DefaultShiftSetting for department) - PRIMARY SOURCE
        // 2. Organization default shift settings (DefaultShiftSetting general)
        // 3. Individual constraints (SchedulingConstraint with required_days) - only if no shift settings
        // 4. Timetable entries (as fallback only)

        // Initialize timetable entries variable (will be used later for has_timetable check)
        $timetableEntries = \App\Models\TimetableEntry::where('schedulable_id', $personId)
            ->where('schedulable_type', $modelClass)
            ->where('is_break', false)
            ->get();

        $workingDaysOfWeek = [];
        $dataSource = null;

        // Load person first to get department info
        $person = null;
        if ($modelClass === 'App\Models\Employee') {
            $person = \App\Models\Employee::with(['constraints', 'department'])->find($personId);
        } elseif ($modelClass === 'App\Models\Teacher') {
            $person = \App\Models\Teacher::with(['constraints', 'department'])->find($personId);
        }

        // 1. FIRST PRIORITY: Try to get from department default shift settings
        if ($person && $person->department) {
            $departmentShiftSetting = DefaultShiftSetting::where('is_active', true)
                ->where('department_id', $person->department_id)
                ->first();

            if ($departmentShiftSetting && !empty($departmentShiftSetting->work_days)) {
                Log::info('PayrollController: Using department shift setting (PRIMARY)', [
                    'person_id' => $personId,
                    'department_id' => $person->department_id,
                    'work_days' => $departmentShiftSetting->work_days,
                ]);

                // Convert from DefaultShiftSetting format (0-6) to TimetableEntry format (1-7)
                $workingDaysOfWeek = array_map(function($day) {
                    return ($day === 0) ? 2 : (($day === 6) ? 1 : ($day + 2));
                }, $departmentShiftSetting->work_days);

                $workingDaysOfWeek = array_unique($workingDaysOfWeek);
                sort($workingDaysOfWeek);
                $dataSource = 'department_shift_setting';
            }
        }

        // 2. SECOND PRIORITY: If no department setting, try organization-wide default shift settings
        if (empty($workingDaysOfWeek)) {
            $organizationShiftSetting = DefaultShiftSetting::where('is_active', true)
                ->whereNull('department_id')
                ->first();

            if ($organizationShiftSetting && !empty($organizationShiftSetting->work_days)) {
                Log::info('PayrollController: Using organization shift setting', [
                    'person_id' => $personId,
                    'work_days' => $organizationShiftSetting->work_days,
                ]);

                // Convert from DefaultShiftSetting format (0-6) to TimetableEntry format (1-7)
                $workingDaysOfWeek = array_map(function($day) {
                    return ($day === 0) ? 2 : (($day === 6) ? 1 : ($day + 2));
                }, $organizationShiftSetting->work_days);

                $workingDaysOfWeek = array_unique($workingDaysOfWeek);
                sort($workingDaysOfWeek);
                $dataSource = 'organization_shift_setting';
            }
        }

        // 3. THIRD PRIORITY: If no shift settings, try individual constraints
        if (empty($workingDaysOfWeek) && $person) {
            $requiredDaysConstraint = $person->constraints->firstWhere('constraint_type', 'required_days');
            if ($requiredDaysConstraint) {
                $constraintValue = $requiredDaysConstraint->value;

                // Handle different formats: array, JSON string, or direct value
                if (is_string($constraintValue)) {
                    $parsed = json_decode($constraintValue, true);
                    if (is_array($parsed)) {
                        $constraintValue = $parsed;
                    }
                }

                if (is_array($constraintValue) && !empty($constraintValue)) {
                    Log::info('PayrollController: Using individual constraints (required_days) - fallback', [
                        'person_id' => $personId,
                        'person_type' => $personType,
                        'constraint_value' => $constraintValue,
                    ]);

                    // Convert from constraints format (0-6) to TimetableEntry format (1-7)
                    $workingDaysOfWeek = array_map(function($day) {
                        return ($day === 0) ? 2 : (($day === 6) ? 1 : ($day + 2));
                    }, $constraintValue);

                    $workingDaysOfWeek = array_unique($workingDaysOfWeek);
                    sort($workingDaysOfWeek);
                    $dataSource = 'individual_constraints';
                }
            }
        }

        // 4. Last resort: use timetable entries (but these might be outdated)
        // Note: $timetableEntries already loaded at the beginning of the function
        if (empty($workingDaysOfWeek) && $timetableEntries->isNotEmpty()) {
            Log::info('PayrollController: Using timetable entries as fallback', [
                'person_id' => $personId,
                'timetable_entries_count' => $timetableEntries->count(),
            ]);

            $rawDays = $timetableEntries->pluck('day_of_week')->unique()->sort()->values()->toArray();

            // Convert to TimetableEntry format (1-7) if needed
            if (in_array(0, $rawDays) || (count($rawDays) > 0 && max($rawDays) <= 6 && min($rawDays) >= 0)) {
                // This is JavaScript format (0-6), convert to TimetableEntry format (1-7)
                $workingDaysOfWeek = array_map(function($day) {
                    return ($day === 0) ? 2 : (($day === 6) ? 1 : ($day + 2));
                }, $rawDays);
                $workingDaysOfWeek = array_unique($workingDaysOfWeek);
                sort($workingDaysOfWeek);
            } else {
                // Already in TimetableEntry format (1-7)
                $workingDaysOfWeek = $rawDays;
            }
            $dataSource = 'timetable_entries';
        }

        Log::info('PayrollController: Final working days', [
            'person_id' => $personId,
            'person_type' => $personType,
            'working_days_of_week' => $workingDaysOfWeek,
            'count' => count($workingDaysOfWeek),
            'data_source' => $dataSource,
        ]);

        // If still no working days found, log warning
        if (empty($workingDaysOfWeek)) {
            Log::warning('PayrollController: No working days found from any source', [
                'person_id' => $personId,
                'person_type' => $personType,
            ]);
        }

        // Convert day numbers to day names
        $dayNames = [
            1 => 'السبت',
            2 => 'الأحد',
            3 => 'الإثنين',
            4 => 'الثلاثاء',
            5 => 'الأربعاء',
            6 => 'الخميس',
            7 => 'الجمعة',
        ];

        Log::info('PayrollController: Converting to day names', [
            'working_days_of_week' => $workingDaysOfWeek,
            'count' => count($workingDaysOfWeek),
        ]);

        $workingDaysNames = array_map(function($day) use ($dayNames) {
            // Filter out invalid day numbers (like 0) and return proper day names
            if ($day < 1 || $day > 7) {
                Log::warning("PayrollController: Invalid day number found: $day");
                return null; // Skip invalid days
            }
            $name = $dayNames[$day] ?? "يوم $day";
            Log::info("PayrollController: Day $day -> $name");
            return $name;
        }, $workingDaysOfWeek);

        Log::info('PayrollController: After mapping to names', [
            'working_days_names' => $workingDaysNames,
            'count' => count($workingDaysNames),
        ]);

        // Remove null values from array
        $workingDaysNames = array_filter($workingDaysNames, function($name) {
            return $name !== null;
        });

        Log::info('PayrollController: After filtering nulls', [
            'working_days_names' => $workingDaysNames,
            'count' => count($workingDaysNames),
        ]);

        // Re-index array
        $workingDaysNames = array_values($workingDaysNames);

        Log::info('PayrollController: Final working days names', [
            'working_days_names' => $workingDaysNames,
            'count' => count($workingDaysNames),
            'working_days_per_week' => count($workingDaysOfWeek),
        ]);

        // Calculate actual working days in the period
        $actualWorkingDays = 0;
        if (!empty($workingDaysOfWeek)) {
            $currentDate = $startDate->copy();
            while ($currentDate->lte($endDate)) {
                $carbonDayOfWeek = $currentDate->dayOfWeek;
                $timetableDayOfWeek = ($carbonDayOfWeek === 0) ? 2 : (($carbonDayOfWeek === 6) ? 1 : ($carbonDayOfWeek + 2));
                if (in_array($timetableDayOfWeek, $workingDaysOfWeek)) {
                    $actualWorkingDays++;
                }
                $currentDate->addDay();
            }
        }

        // Get shift assignment first
        $shiftAssignment = \App\Models\ShiftAssignment::where('shiftable_id', $personId)
            ->where('shiftable_type', $modelClass)
            ->with('shift')
            ->first();

        // If no shift assignment, get default shift setting from department or organization
        $shiftInfo = null;
        if ($shiftAssignment?->shift) {
            // Use assigned shift
            $shiftInfo = [
                'id' => $shiftAssignment->shift->id,
                'name' => $shiftAssignment->shift->name,
                'start_time' => $this->formatTimeTo12HourArabic($shiftAssignment->shift->start_time),
                'end_time' => $this->formatTimeTo12HourArabic($shiftAssignment->shift->end_time),
                'source' => 'assigned',
            ];
        } else {
            // Get person to find department
            $person = null;
            if ($modelClass === 'App\Models\Employee') {
                $person = \App\Models\Employee::with('department')->find($personId);
            } elseif ($modelClass === 'App\Models\Teacher') {
                $person = \App\Models\Teacher::with('department')->find($personId);
            }

            if ($person && $person->department) {
                // Try to get department shift setting
                $departmentShiftSetting = DefaultShiftSetting::where('is_active', true)
                    ->where('department_id', $person->department_id)
                    ->first();

                if ($departmentShiftSetting) {
                    $shiftInfo = [
                        'id' => null,
                        'name' => $departmentShiftSetting->name,
                        'start_time' => $this->formatTimeTo12HourArabic($departmentShiftSetting->start_time),
                        'end_time' => $this->formatTimeTo12HourArabic($departmentShiftSetting->end_time),
                        'source' => 'department',
                    ];
                }
            }

            // If no department setting, get organization-wide setting
            if (!$shiftInfo) {
                $organizationShiftSetting = DefaultShiftSetting::where('is_active', true)
                    ->whereNull('department_id')
                    ->first();

                if ($organizationShiftSetting) {
                    $shiftInfo = [
                        'id' => null,
                        'name' => $organizationShiftSetting->name,
                        'start_time' => $this->formatTimeTo12HourArabic($organizationShiftSetting->start_time),
                        'end_time' => $this->formatTimeTo12HourArabic($organizationShiftSetting->end_time),
                        'source' => 'organization',
                    ];
                }
            }
        }

        return [
            'working_days_of_week' => $workingDaysOfWeek,
            'working_days_names' => $workingDaysNames,
            'working_days_per_week' => count($workingDaysOfWeek),
            'actual_working_days_in_period' => $actualWorkingDays,
            'has_timetable' => $timetableEntries->isNotEmpty(),
            'has_constraints' => !empty($workingDaysOfWeek) && $timetableEntries->isEmpty(),
            'shift' => $shiftInfo,
        ];
    }

    /**
     * Format time to 12-hour format with Arabic period (صباحاً/مساءً)
     */
    private function formatTimeTo12HourArabic($time)
    {
        if (!$time) return null;

        $carbon = $time instanceof \DateTime
            ? Carbon::instance($time)
            : Carbon::parse($time);

        $hours = (int)$carbon->format('H');
        $minutes = $carbon->format('i');

        $period = $hours >= 12 ? 'مساءً' : 'صباحاً';

        $hours12 = $hours % 12;
        if ($hours12 === 0) $hours12 = 12;

        return sprintf('%d:%s %s', $hours12, $minutes, $period);
    }

    /**
     * Get constraints information for a person
     */
    private function getConstraintsInfo($personId, string $personType): array
    {
        // Convert personType to full model class name
        $modelClass = $personType === 'employee' ? 'App\Models\Employee' : ($personType === 'teacher' ? 'App\Models\Teacher' : $personType);

        $person = null;
        if ($modelClass === 'App\Models\Employee') {
            $person = \App\Models\Employee::with('constraints')->find($personId);
        } elseif ($modelClass === 'App\Models\Teacher') {
            $person = \App\Models\Teacher::with('constraints')->find($personId);
        }

        if (!$person) {
            return ['has_constraints' => false, 'constraints' => []];
        }

        $constraints = $person->constraints->map(function($constraint) {
            return [
                'type' => $constraint->constraint_type,
                'value' => $constraint->value,
                'employment_type' => $constraint->employment_type,
            ];
        })->toArray();

        return [
            'has_constraints' => $person->constraints->isNotEmpty(),
            'constraints' => $constraints,
        ];
    }

    /**
     * Get leaves information for a person
     */
    private function getLeavesInfo($personId, string $personType, Carbon $startDate, Carbon $endDate): array
    {
        // Convert personType to full model class name
        $modelClass = $personType === 'employee' ? 'App\Models\Employee' : ($personType === 'teacher' ? 'App\Models\Teacher' : $personType);

        $leaves = \App\Models\Leave::where('leavable_id', $personId)
            ->where('leavable_type', $modelClass)
            ->where('status', 'approved')
            ->where(function($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate])
                      ->orWhere(function($q) use ($startDate, $endDate) {
                          $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                      });
            })
            ->with('leaveType')
            ->get();

        $paidLeaves = [];
        $unpaidLeaves = [];

        foreach ($leaves as $leave) {
            $startDateFormatted = $leave->start_date instanceof Carbon
                ? $leave->start_date->format('Y-m-d')
                : (is_string($leave->start_date) ? $leave->start_date : Carbon::parse($leave->start_date)->format('Y-m-d'));

            $endDateFormatted = $leave->end_date instanceof Carbon
                ? $leave->end_date->format('Y-m-d')
                : (is_string($leave->end_date) ? $leave->end_date : Carbon::parse($leave->end_date)->format('Y-m-d'));

            $leaveInfo = [
                'id' => $leave->id,
                'start_date' => $startDateFormatted,
                'end_date' => $endDateFormatted,
                'leave_type' => $leave->leaveType ? [
                    'id' => $leave->leaveType->id,
                    'name' => $leave->leaveType->name,
                    'is_paid' => $leave->leaveType->is_paid,
                ] : null,
                'reason' => $leave->reason,
            ];

            if ($leave->leaveType && $leave->leaveType->is_paid) {
                $paidLeaves[] = $leaveInfo;
            } else {
                $unpaidLeaves[] = $leaveInfo;
            }
        }

        return [
            'total_leaves' => $leaves->count(),
            'paid_leaves' => $paidLeaves,
            'unpaid_leaves' => $unpaidLeaves,
            'paid_leaves_count' => count($paidLeaves),
            'unpaid_leaves_count' => count($unpaidLeaves),
        ];
    }

    /**
     * Export payroll preview to Excel
     */
    public function exportPreview(Request $request)
    {
        $month = (int) $request->input('month');
        $year = (int) $request->input('year');
        $personnelJson = $request->input('personnel');
        $mode = $request->input('mode', 'with_review');

        Log::info('ExportPreview called', [
            'month' => $month,
            'year' => $year,
            'personnel_json' => $personnelJson,
            'mode' => $mode,
            'all_inputs' => $request->all(),
        ]);

        // Parse personnel JSON if it's a string
        $personnel = [];
        if (is_string($personnelJson)) {
            $personnel = json_decode($personnelJson, true) ?? [];
        } elseif (is_array($personnelJson)) {
            $personnel = $personnelJson;
        }

        Log::info('Parsed personnel', [
            'personnel' => $personnel,
            'count' => count($personnel),
        ]);

        if (empty($personnel) || !$month || !$year) {
            Log::warning('ExportPreview validation failed', [
                'personnel_empty' => empty($personnel),
                'month' => $month,
                'year' => $year,
            ]);
            return redirect()->back()->with('error', 'يرجى تحديد الموظفين/المعلمين والفترة أولاً');
        }

        // Get preview data (reuse the preview method logic)
        $previewData = $this->generatePreviewData($personnel, $month, $year, $mode);

        $months = [
            1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
            5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
            9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
        ];
        $monthName = $months[$month] ?? '';

        $fileName = 'معاينة_الرواتب_' . $monthName . '_' . $year . '_' . date('Y-m-d') . '.xlsx';

        // Prepare export data
        $exportData = [
            'preview' => $previewData['preview'] ?? [],
            'month' => $month,
            'year' => $year,
        ];

        return Excel::download(
            new PayrollPreviewExport($exportData),
            $fileName
        );
    }

    /**
     * Generate preview data (extracted from preview method for reuse)
     */
    private function generatePreviewData($personnel, $month, $year, $mode)
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $preview = [];

        foreach ($personnel as $personData) {
            $personId = $personData['id'];
            $personType = $personData['type'];
            $contractId = $personData['contract_id'] ?? null;

            $modelClass = $personType === 'employee' ? 'App\Models\Employee' : 'App\Models\Teacher';
            $person = $modelClass::with('user')->find($personId);

            if (!$person) {
                continue;
            }

            // Get contract and salary
            $contract = null;
            $grossSalary = 0;

            if ($personType === 'employee') {
                $contract = Contract::find($contractId);
                $grossSalary = $contract ? (float)($contract->total_salary ?? $contract->basic_salary ?? 0) : 0;
            } else {
                $contract = TeacherContract::find($contractId);
                $grossSalary = $contract ? (float)($contract->salary_amount ?? 0) : 0;
            }

            if (!$contract || $grossSalary <= 0) {
                continue;
            }

            // Get deductions if mode is with_review
            $deductions = ['applied_deductions' => [], 'not_applied_rules' => [], 'total_deduction' => 0];
            if ($mode === 'with_review') {
                $comparisonData = $this->comparisonService->compareAttendanceWithTimetable(
                    $personId,
                    $modelClass,
                    $startDate,
                    $endDate
                );

                $deductions = $this->deductionService->applyDeductionRules(
                    $comparisonData,
                    $personId,
                    $modelClass,
                    $startDate,
                    $endDate
                );
            }

            $totalDeduction = $deductions['total_deduction'] ?? 0;
            $netSalary = $grossSalary - $totalDeduction;

            // Get schedule info
            $scheduleInfo = $this->getScheduleInfo($personId, $personType, $startDate, $endDate);
            $constraintsInfo = $this->getConstraintsInfo($personId, $personType);
            $leavesInfo = $this->getLeavesInfo($personId, $personType, $startDate, $endDate);

            // Build full name from person data
            $firstName = $person->user->name ?? '';
            $middleName = $person->middle_name ?? '';
            $lastName = $person->last_name ?? '';
            $fullName = trim("{$firstName} {$middleName} {$lastName}") ?: $firstName;
            $personName = $firstName; // Keep first name for backward compatibility

            $preview[] = [
                'id' => $personId,
                'type' => $personType,
                'name' => $personName,
                'full_name' => $fullName,
                'contract_id' => $contractId,
                'gross_salary' => $grossSalary,
                'deductions' => $deductions,
                'net_salary' => $netSalary,
                'apply_deductions' => true,
                'schedule_info' => $scheduleInfo,
                'constraints_info' => $constraintsInfo,
                'leaves_info' => $leavesInfo,
            ];
        }

        return ['preview' => $preview];
    }
}
