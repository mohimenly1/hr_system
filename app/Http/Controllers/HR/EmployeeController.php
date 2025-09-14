<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use App\Models\Contract;
use App\Models\Department;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use App\Models\WorkExperience;
use App\Services\FingerprintService; // سنفترض وجود خدمة لسحب البصمة
use Illuminate\Support\Facades\Auth; // <-- إضافة مهمة
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\LeaveType;
use App\Services\LeaveBalanceService;
use Illuminate\Validation\ValidationException;
use App\Models\EvaluationCriterion;
use App\Models\ActivityLog;
use App\Models\DeductionLog;
use App\Models\PenaltyType;


class EmployeeController extends Controller
{
    use AuthorizesRequests;
    public function index(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $filters = $request->only('search', 'department_id');
        $user = Auth::user();

        $employeesQuery = Employee::with([
                'user.roles:id,name', 
                'department:id,name',
                'managedDepartments:id,name,manager_id' // <-- جلب الأقسام التي يديرها
            ])
            ->when($request->input('search'), function ($query, $search) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->input('department_id'), function ($query, $departmentId) {
                $query->where('department_id', $departmentId);
            });

        
        $departmentsForFilter = collect();

        if ($user->hasRole('department-manager')) {
            $managedDepartmentIds = Department::where('manager_id', $user->id)->pluck('id');
            if ($managedDepartmentIds->isNotEmpty()) {
                $employeesQuery->whereIn('department_id', $managedDepartmentIds);
                // مدير القسم سيرى فقط الأقسام التي يديرها في الفلتر
                $departmentsForFilter = Department::whereIn('id', $managedDepartmentIds)->get(['id', 'name']);
            } else {
                $employeesQuery->whereRaw('1 = 0'); 
            }
        } else {
            // الأدوار الأخرى سترى كل الأقسام
            $departmentsForFilter = Department::all(['id', 'name']);
        }
        
        $employees = $employeesQuery->latest()->paginate(10)->withQueryString();

        return Inertia::render('HR/Employees/Index', [
            'employees' => $employees,
            'filters' => $filters,
            'departments' => $departmentsForFilter,
        ]);
    }
    /**
     * Show the form for creating a new resource.
     */

     public function show(Employee $employee, LeaveBalanceService $leaveBalanceService)
     {
         $this->authorize('view', $employee);
         
         // --- 1. تحديث تحميل العلاقات لتشمل العقوبات ---
         $employee->load([
             'user.roles', 
             'department', 
             'contracts', 
             'attachments', 
             'leaves.leaveType',
             'workExperiences',
             'managedDepartments:id,name,manager_id',
             'evaluations.results.criterion',
             'penalties.penaltyType', // <-- إضافة جديدة
             'penalties.issuer:id,name' // <-- إضافة جديدة
         ]);
  
         $employee->attachments->each(function ($attachment) {
             $attachment->url = Storage::url($attachment->file_path);
         });
         
         $averageScore = $employee->evaluations->avg('final_score_percentage');
         $activeCriteria = EvaluationCriterion::where('is_active', true)->get();
 
         // --- 2. إضافة منطق حساب الخصومات الشهرية ---
         $deductions = $employee->penalties()
             ->whereYear('issued_at', now()->year)
             ->whereMonth('issued_at', now()->month)
             ->with('penaltyType.criteria')
             ->get()
             ->flatMap(fn($penalty) => $penalty->penaltyType->criteria->map(fn($criterion) => [
                 'criterion_id' => $criterion->id,
                 'points' => $criterion->pivot->deduction_points,
                 'reason' => $penalty->penaltyType->name,
             ]))
             ->groupBy('criterion_id')
             ->map(function ($group, $criterionId) use ($activeCriteria) {
                 $criterion = $activeCriteria->firstWhere('id', $criterionId);
                 if (!$criterion) return null;
 
                 $totalDeduction = $group->sum('points');
                 $cappedDeduction = min($totalDeduction, $criterion->max_score);
 
                 return [
                     'total_deduction' => $cappedDeduction,
                     'reasons' => $group->pluck('reason')->unique()->implode(', '),
                 ];
             })->filter();
 
         // --- 3. إضافة منطق جلب ودمج مؤشر الأداء الكامل ---
         $creationLogs = ActivityLog::where(function ($query) use ($employee) {
             $query->where(function($q) use ($employee) {
                 $q->where('subject_type', \App\Models\Penalty::class)
                   ->whereIn('subject_id', $employee->penalties->pluck('id'));
             })->orWhere(function($q) use ($employee) {
                 $q->where('subject_type', \App\Models\PerformanceEvaluation::class)
                   ->whereIn('subject_id', $employee->evaluations->pluck('id'));
             });
         })->with('user:id,name')->latest()->get()->map(function ($log) {
             return [
                 'type' => $log->subject_type,
                 'date' => $log->created_at,
                 'user' => $log->user,
                 'details' => $log->details,
             ];
         });
 
         $deductionLogs = DeductionLog::whereIn('performance_evaluation_id', $employee->evaluations->pluck('id'))
             ->with(['logger:id,name', 'penalty.penaltyType', 'evaluation', 'criterion'])
             ->latest()->get()->map(function ($log) {
                 return [
                     'type' => 'deduction_applied',
                     'date' => $log->created_at,
                     'user' => $log->logger,
                     'details' => [
                         'penalty_name' => $log->penalty->penaltyType->name,
                         'evaluation_title' => $log->evaluation->title,
                         'criterion_name' => $log->criterion->name,
                         'points' => $log->points_deducted,
                         'affects_salary' => $log->penalty->penaltyType->affects_salary,
                         'deduction_amount' => $log->penalty->penaltyType->deduction_amount,
                         'deduction_type' => $log->penalty->penaltyType->deduction_type,
                     ],
                 ];
             });
 
         $activityLogs = $creationLogs->merge($deductionLogs)
                                     ->sortByDesc('date')
                                     ->values()
                                     ->take(10);
  
         // --- 4. تحديث البيانات المرسلة إلى الواجهة ---
         return Inertia::render('HR/Employees/Show', [
             'employee' => $employee,
             'departments' => Department::all(['id', 'name']),
             'leaveTypes' => LeaveType::where('is_active', true)->get(['id', 'name']),
             'leaveBalances' => $leaveBalanceService->getAllBalancesForPerson($employee),
             'criteria' => $activeCriteria,
             'averageEvaluationScore' => $averageScore ? round($averageScore, 2) : 0,
             // -- الإضافات الجديدة --
             'penaltyTypes' => PenaltyType::where('is_active', true)->get(['id', 'name']),
             'deductions' => $deductions,
             'activityLogs' => $activityLogs,
         ]);
     }
 
    public function create()
    {
        $this->authorize('create', Employee::class);
        $departments = Department::all(['id', 'name']);
        return Inertia::render('HR/Employees/Create', [
            'departments' => $departments,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Employee::class);
        // Note: Full validation rules from your example are assumed here for brevity
        $validatedData = $request->validate([
            'personal.middle_name' => 'nullable|string|max:255',
            'personal.last_name' => 'nullable|string|max:255',
            'personal.mother_name' => 'nullable|string|max:255',
            'personal.marital_status' => 'nullable|string',
            'personal.nationality' => 'nullable|string|max:255',
            'personal.national_id_number' => 'nullable|string|max:255|unique:employees,national_id_number',
            'personal.phone_number' => 'nullable|string|max:20',
            'personal.address' => 'nullable|string',
            'personal.date_of_birth' => 'nullable|date',
            'personal.gender' => 'nullable|in:male,female',
            'personal.attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048',
            'work_experiences' => 'nullable|array',
            'work_experiences.*.company_name' => 'required_with:work_experiences|string|max:255',
            'work_experiences.*.job_title' => 'required_with:work_experiences|string|max:255',
            'work_experiences.*.start_date' => 'nullable|date',
            'work_experiences.*.end_date' => 'nullable|date|after_or_equal:work_experiences.*.start_date',
            'work_experiences.*.description' => 'nullable|string',
            'employment.department_id' => 'required|exists:departments,id',
            'employment.employee_id' => 'required|string|max:255|unique:employees,employee_id',
            'employment.fingerprint_id' => 'nullable|numeric|unique:employees,fingerprint_id',
            'employment.hire_date' => 'required|date',
            'employment.employment_status' => 'required|in:active,on_leave,terminated',
            'employment.job_title' => 'required|string|max:255',
            'employment.contract_type' => 'required|string',
            'employment.start_date' => 'required|date',
            'employment.basic_salary' => 'required|numeric|min:0',
            'employment.housing_allowance' => 'nullable|numeric|min:0',
            'employment.transportation_allowance' => 'nullable|numeric|min:0',
            'employment.other_allowances' => 'nullable|numeric|min:0',
            'employment.status' => 'required|string',
            'employment.notice_period_days' => 'nullable|integer|min:0',
            'employment.annual_leave_days' => 'nullable|integer|min:0',
            'employment.notes' => 'nullable|string',
            'account.name' => 'required|string|max:255',
            'account.email' => 'required|string|email|max:255|unique:users,email',
            'account.password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        DB::beginTransaction();
        try {
            $user = User::create([
                'name' => $validatedData['account']['name'],
                'email' => $validatedData['account']['email'],
                'password' => Hash::make($validatedData['account']['password']),
            ]);
            $user->assignRole('employee');

            // --- FIX: Prepare data correctly for each model ---
            $personalData = $validatedData['personal'];
            unset($personalData['attachments']); // Exclude files from mass assignment

            $employeeData = array_merge(
                $personalData,
                [
                    'department_id' => $validatedData['employment']['department_id'],
                    'employee_id' => $validatedData['employment']['employee_id'],
                    'job_title' => $validatedData['employment']['job_title'],
                    'hire_date' => $validatedData['employment']['hire_date'],
                    'employment_status' => $validatedData['employment']['employment_status'],
                ]
            );
            $employee = $user->employee()->create($employeeData);

            // Prepare data specifically for the contract
            $contractData = $validatedData['employment'];
            $employee->contracts()->create($contractData);

            // Create Work Experiences
            if (!empty($validatedData['work_experiences'])) {
                $employee->workExperiences()->createMany($validatedData['work_experiences']);
            }

            // Handle Attachments
            if ($request->hasFile('personal.attachments')) {
                foreach ($request->file('personal.attachments') as $file) {
                    $path = $file->store("employees/{$employee->id}/attachments", 'public');
                    $employee->attachments()->create([
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'file_type' => $file->getClientMimeType(),
                    ]);
                }
            }
            
            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Employee creation failed: ' . $e->getMessage());
            return Redirect::back()->with('error', 'حدث خطأ أثناء حفظ بيانات الموظف. يرجى مراجعة سجل الأخطاء.');
        }

        return Redirect::route('hr.employees.index')->with('success', 'تمت إضافة الموظف بنجاح.');
    }

    public function storeAttachment(Request $request, Employee $employee)
    {
        $this->authorize('create', Employee::class);
        $request->validate([
            'attachment_name' => 'required|string|max:255',
            'attachment_file' => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048',
        ]);

        $file = $request->file('attachment_file');
        $path = $file->store("employees/{$employee->id}/attachments", 'public');

        $employee->attachments()->create([
            'file_path' => $path,
            'file_name' => $request->input('attachment_name'), // Use the user-provided name
            'file_type' => $file->getClientMimeType(),
        ]);

        return Redirect::back()->with('success', 'تم رفع المرفق بنجاح.');
    }


    public function edit(Employee $employee)
    {
        $this->authorize('update', $employee);
        $employee->load(['user', 'department', 'contracts' => fn($q) => $q->latest()->limit(1), 'workExperiences']);
        $departments = Department::all(['id', 'name']);
        
        return Inertia::render('HR/Employees/Edit', [
            'employee' => $employee,
            'departments' => $departments,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Employee $employee)
    {
        $this->authorize('update', $employee);
        $validatedData = $request->validate([
            // Personal Info
            'personal.middle_name' => 'nullable|string|max:255',
            'personal.last_name' => 'nullable|string|max:255',
            'personal.mother_name' => 'nullable|string|max:255',
            'personal.marital_status' => 'nullable|string',
            'personal.nationality' => 'nullable|string|max:255',
            'personal.national_id_number' => ['nullable','string','max:255', Rule::unique('employees')->ignore($employee->id)],
            'personal.phone_number' => 'nullable|string|max:20',
            'personal.address' => 'nullable|string',
            'personal.date_of_birth' => 'nullable|date',
            'personal.gender' => 'nullable|in:male,female',
            
            // Work Experiences
            'work_experiences' => 'nullable|array',
            'work_experiences.*.company_name' => 'required_with:work_experiences|string|max:255',
            'work_experiences.*.job_title' => 'required_with:work_experiences|string|max:255',
            'work_experiences.*.start_date' => 'nullable|date',
            'work_experiences.*.end_date' => 'nullable|date|after_or_equal:work_experiences.*.start_date',
            'work_experiences.*.description' => 'nullable|string',
            
            // Employment & Contract Info
            'employment.department_id' => 'required|exists:departments,id',
            'employment.employee_id' => ['required', 'string', 'max:255', Rule::unique('employees', 'employee_id')->ignore($employee->id)],
            'employment.fingerprint_id' => ['nullable','numeric', Rule::unique('employees')->ignore($employee->id)],
            'employment.hire_date' => 'required|date',
            'employment.employment_status' => 'required|in:active,on_leave,terminated',
            'employment.job_title' => 'required|string|max:255',
            'employment.contract_type' => 'required|string',
            'employment.start_date' => 'required|date',
            'employment.basic_salary' => 'required|numeric|min:0',
            'employment.housing_allowance' => 'nullable|numeric|min:0',
            'employment.transportation_allowance' => 'nullable|numeric|min:0',
            'employment.other_allowances' => 'nullable|numeric|min:0',
            'employment.status' => 'required|string',
            'employment.notice_period_days' => 'nullable|integer|min:0',
            'employment.annual_leave_days' => 'nullable|integer|min:0',
            'employment.notes' => 'nullable|string',

            // Account Info
            'account.name' => 'required|string|max:255',
            'account.email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($employee->user_id)],
            'account.password' => ['nullable', 'confirmed', Rules\Password::defaults()],
        ]);

        DB::beginTransaction();
        try {
            // 1. Update User
            $employee->user->update([
                'name' => $validatedData['account']['name'],
                'email' => $validatedData['account']['email'],
            ]);
            if (!empty($validatedData['account']['password'])) {
                $employee->user->update(['password' => Hash::make($validatedData['account']['password'])]);
            }

            // 2. Prepare and Update Employee data
            $personalData = $validatedData['personal'];
            $employeeData = array_merge(
                $personalData,
                 [
                    'department_id' => $validatedData['employment']['department_id'],
                    'employee_id' => $validatedData['employment']['employee_id'],
                    'job_title' => $validatedData['employment']['job_title'],
                    'hire_date' => $validatedData['employment']['hire_date'],
                    'employment_status' => $validatedData['employment']['employment_status'],
                ]
            );
            $employee->update($employeeData);

            // 3. Prepare and Update Contract data
            $contractData = $validatedData['employment'];
            if ($employee->contracts()->exists()) {
                $employee->contracts()->latest()->first()->update($contractData);
            } else {
                $employee->contracts()->create($contractData);
            }

            // 4. Update Work Experiences (delete all and recreate)
            $employee->workExperiences()->delete();
            if (!empty($validatedData['work_experiences'])) {
                $employee->workExperiences()->createMany($validatedData['work_experiences']);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Employee update failed for ID ' . $employee->id . ': ' . $e->getMessage());
            return Redirect::back()->with('error', 'حدث خطأ أثناء تحديث بيانات الموظف.');
        }

        return Redirect::route('hr.employees.index')->with('success', 'تم تحديث بيانات الموظف بنجاح.');
    }

    public function storeLeave(Request $request, Employee $employee, LeaveBalanceService $leaveBalanceService)
    {
        $this->authorize('update', $employee);
        
        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
        ]);

        $leaveType = LeaveType::find($validated['leave_type_id']);
        $requestedDuration = $leaveBalanceService->calculateLeaveDuration($validated['start_date'], $validated['end_date']);
        $availableBalance = $leaveBalanceService->getAvailableBalance($employee, $leaveType);

        // التحقق من الرصيد
        if ($requestedDuration > $availableBalance) {
            // إرجاع خطأ محدد للواجهة
            throw ValidationException::withMessages([
                'end_date' => "الرصيد المتاح لهذا النوع من الإجازات هو {$availableBalance} يوم فقط.",
            ]);
        }

        $employee->leaves()->create($validated);

        return Redirect::back()->with('success', 'تم تسجيل طلب الإجازة للموظف بنجاح.');
    }

    public function storeWorkExperience(Request $request, Employee $employee)
    {
        $this->authorize('create', Employee::class);
        $request->validate([
            'company_name' => 'required|string|max:255',
            'job_title' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'description' => 'nullable|string',
        ]);

        $employee->workExperiences()->create($request->all());

        return Redirect::back()->with('success', 'تمت إضافة الخبرة العملية بنجاح.');
    }


    public function updateWorkExperience(Request $request, Employee $employee, WorkExperience $experience)
    {
        $this->authorize('update', $employee);

        // --- THE FIX: Correct ownership check for polymorphic relationship ---
        if ($experience->experienceable_id !== $employee->id || $experience->experienceable_type !== Employee::class) {
            abort(403, 'This work experience does not belong to the specified employee.');
        }

        $validatedData = $request->validate([
            'company_name' => 'required|string|max:255',
            'job_title' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'description' => 'nullable|string',
        ]);
        $experience->update($validatedData);
        return Redirect::back()->with('success', 'تم تحديث الخبرة العملية بنجاح.');
    }

    /**
     * Remove the specified work experience from storage.
     * --- NEW FUNCTION ---
     */
    public function destroyWorkExperience(Employee $employee, WorkExperience $experience)
    {
        $this->authorize('delete', $employee);
        if ($experience->employee_id !== $employee->id) {
            abort(403);
        }
        $experience->delete();
        return Redirect::back()->with('success', 'تم حذف الخبرة العملية بنجاح.');
    }

        /**
     * Update the personal information for the specified employee.
     * --- NEW FUNCTION FOR INLINE EDITING ---
     */
    public function updatePersonalInfo(Request $request, Employee $employee)
    {
        $this->authorize('update', $employee);
        $validatedData = $request->validate([
            'user.name' => 'required|string|max:255',
            'user.email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee->user_id)],
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'department_id' => 'required|exists:departments,id', // <-- التحديث هنا
            'mother_name' => 'nullable|string|max:255',
            'marital_status' => 'nullable|string',
            'nationality' => 'nullable|string|max:255',
            'national_id_number' => ['nullable','string','max:255', Rule::unique('employees')->ignore($employee->id)],
            'phone_number' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
        ]);

        DB::beginTransaction();
        try {
            $employee->user->update($validatedData['user']);
            
            // Unset the user data before updating the employee
            unset($validatedData['user']);
            $employee->update($validatedData);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Personal info update failed for employee ID ' . $employee->id . ': ' . $e->getMessage());
            return Redirect::back()->with('error', 'حدث خطأ أثناء تحديث البيانات الشخصية.');
        }

        return Redirect::back()->with('success', 'تم تحديث البيانات الشخصية بنجاح.');
    }

    public function updateFingerprintId(Request $request, Employee $employee)
    {
        $this->authorize('updateFingerprint', $employee);
        $validated = $request->validate([
            'fingerprint_id' => [
                'required',
                'numeric',
                Rule::unique('employees')->ignore($employee->id),
                // Also check against teachers table to avoid conflicts on the device
                Rule::unique('teachers', 'fingerprint_id') 
            ],
        ]);

        $employee->update(['fingerprint_id' => $validated['fingerprint_id']]);

        return Redirect::back()->with('success', 'تم تحديث رقم البصمة بنجاح.');
    }

    public function showAttendance(Employee $employee)
    {
        $this->authorize('view', $employee);
        // تحميل بيانات المستخدم المرتبطة بالموظف
        $employee->load('user');

        // جلب سجلات الحضور الخاصة بالموظف مع ترتيبها من الأحدث للأقدم وتقسيمها لصفحات
        $attendances = $employee->attendances()
                                ->orderBy('attendance_date', 'desc')
                                ->paginate(15);

        // إرسال البيانات إلى واجهة العرض
        return Inertia::render('HR/Employees/Attendance/Show', [
            'employee' => $employee,
            'attendances' => $attendances,
        ]);
    }

    /**
     * Sync today's attendance for a single employee from the fingerprint device.
     * مزامنة حضور اليوم لموظف واحد من جهاز البصمة
     */
    public function syncSingleAttendance(Employee $employee, FingerprintService $fingerprintService)
    {
        $this->authorize('create', Employee::class);
        if (!$employee->fingerprint_id) {
            return back()->with('error', 'هذا الموظف لا يملك رقم بصمة.');
        }

        try {
            // استدعاء الخدمة لمزامنة موظف واحد فقط
            $result = $fingerprintService->syncSingleUser(
                $employee->fingerprint_id,
                now()->toDateString()
            );

            if ($result) {
                return back()->with('success', 'تمت مزامنة حضور الموظف بنجاح.');
            } else {
                return back()->with('info', 'لا توجد سجلات بصمة جديدة للموظف اليوم.');
            }
        } catch (\Exception $e) {
            // في حال حدوث أي خطأ
            return back()->with('error', 'حدث خطأ أثناء الاتصال بالجهاز: ' . $e->getMessage());
        }
    }
}

