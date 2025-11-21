<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Grade;
use App\Models\Teacher;
use App\Models\User;
use App\Models\WorkExperience;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use App\Services\FingerprintService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use App\Models\LeaveType;
use App\Services\LeaveBalanceService;
use Illuminate\Validation\ValidationException;
use App\Models\EvaluationCriterion;
use App\Models\PenaltyType;
use App\Models\ActivityLog;
use App\Models\DeductionLog;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TeacherAttendanceExport;
use App\Exports\TeacherAbsentDaysExport;


class TeacherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    use AuthorizesRequests;
    public function index(Request $request)
    {
        $this->authorize('viewAny', Teacher::class);

        $filters = $request->only('search', 'department_id');
        $user = Auth::user();

        $teachersQuery = Teacher::with([
                'user.roles:id,name',
                'department:id,name',
                'managedDepartments:id,name,manager_id' // جلب الأقسام التي يديرها
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

        // فلترة النتائج تلقائياً لمدير القسم
        $departmentsForFilter = collect();
        if ($user->hasRole('department-manager')) {
            $managedDepartmentIds = Department::where('manager_id', $user->id)->pluck('id');
            if ($managedDepartmentIds->isNotEmpty()) {
                $teachersQuery->whereIn('department_id', $managedDepartmentIds);
                $departmentsForFilter = Department::whereIn('id', $managedDepartmentIds)->get(['id', 'name']);
            } else {
                $teachersQuery->whereRaw('1 = 0');
            }
        } else {
            $departmentsForFilter = Department::all(['id', 'name']);
        }

        $teachers = $teachersQuery->latest()->paginate(10)->withQueryString();

        return Inertia::render('School/Teachers/Index', [
            'teachers' => $teachers,
            'filters' => $filters,
            'departments' => $departmentsForFilter,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Teacher::class);
        $activeYear = \App\Models\AcademicYear::where('is_active', true)->first();
        return Inertia::render('School/Teachers/Create', [
            'departments' => Department::all(['id', 'name']),
            'grades' => $activeYear ? Grade::where('academic_year_id', $activeYear->id)
                                        ->with(['sections', 'subjects:id,name'])
                                        ->get() : [],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Teacher::class);
        $validatedData = $request->validate([
            'personal.middle_name' => 'nullable|string|max:255',
            'personal.last_name' => 'nullable|string|max:255',
            'personal.mother_name' => 'nullable|string|max:255',
            'personal.marital_status' => 'nullable|string',
            'personal.nationality' => 'nullable|string|max:255',
            'personal.national_id_number' => 'nullable|string|max:255|unique:teachers,national_id_number',
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
            'employment.fingerprint_id' => 'nullable|numeric|unique:teachers,fingerprint_id',
            'employment.hire_date' => 'required|date',
            'employment.employment_status' => 'required|in:active,on_leave,terminated',
            'employment.specialization' => 'required|string|max:255',
            'employment.contract_type' => 'required|string',
            'employment.start_date' => 'required|date',
            'employment.salary_type' => 'required|string',
            'employment.salary_amount' => 'required_if:employment.salary_type,monthly|nullable|numeric|min:0',
            'employment.hourly_rate' => 'required_if:employment.salary_type,hourly|nullable|numeric|min:0',
            'employment.working_hours_per_week' => 'nullable|numeric|min:0',
            'employment.notes' => 'nullable|string',
            'employment.status' => 'required|string',
            'assignments' => 'present|array',
            'assignments.*.subject_id' => 'required|exists:subjects,id',
            'assignments.*.section_id' => 'required|exists:sections,id',
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
            $user->assignRole('teacher');

            $personalData = $validatedData['personal'];
            unset($personalData['attachments']);

            $teacherData = array_merge(
                $personalData,
                [
                    'department_id' => $validatedData['employment']['department_id'],
                    'specialization' => $validatedData['employment']['specialization'],
                    'hire_date' => $validatedData['employment']['hire_date'],
                    'employment_status' => $validatedData['employment']['employment_status'],
                ]
            );
            $teacher = $user->teacher()->create($teacherData);

            // --- THIS IS THE FIX ---
            // Prepare data specifically for the TeacherContract model
            $contractData = [
                'contract_type' => $validatedData['employment']['contract_type'],
                'start_date' => $validatedData['employment']['start_date'],
                'salary_type' => $validatedData['employment']['salary_type'],
                'salary_amount' => $validatedData['employment']['salary_amount'],
                'hourly_rate' => $validatedData['employment']['hourly_rate'],
                'working_hours_per_week' => $validatedData['employment']['working_hours_per_week'],
                'notes' => $validatedData['employment']['notes'],
                'status' => $validatedData['employment']['status'],
            ];
            $teacher->contracts()->create($contractData);

            if (!empty($validatedData['work_experiences'])) {
                $teacher->workExperiences()->createMany($validatedData['work_experiences']);
            }

            $teacher->assignments()->createMany($validatedData['assignments']);

            if ($request->hasFile('personal.attachments')) {
                foreach ($request->file('personal.attachments') as $file) {
                    $path = $file->store("teachers/{$teacher->id}/attachments", 'public');
                    $teacher->attachments()->create([
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'file_type' => $file->getClientMimeType(),
                    ]);
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Teacher creation failed: ' . $e->getMessage());
            return Redirect::back()->with('error', 'حدث خطأ أثناء حفظ بيانات المعلم. يرجى مراجعة سجل الأخطاء.');
        }

        return Redirect::route('school.teachers.index')->with('success', 'تمت إضافة المعلم بنجاح.');
    }


    /**
     * Display the specified resource.
     */
    public function show(Teacher $teacher, LeaveBalanceService $leaveBalanceService)
    {
        $this->authorize('view', $teacher);

        $teacher->load([
            'user.roles', 'department', 'contracts', 'attachments',
            'leaves.leaveType', 'workExperiences', 'assignments.subject',
            'assignments.section.grade',
            'evaluations.results.criterion', // نحتاجها لجلب سجلات الخصم
            'penalties.penaltyType', 'penalties.issuer:id,name' // نحتاجها لجلب سجلات العقوبات
        ]);

        $averageScore = $teacher->evaluations->avg('final_score_percentage');

        $activeYear = \App\Models\AcademicYear::where('is_active', true)->first();
        $activeCriteria = EvaluationCriterion::where('is_active', true)->get();

        $deductions = $teacher->penalties()
        ->whereYear('issued_at', now()->year)   // <-- فلترة حسب السنة الحالية
        ->whereMonth('issued_at', now()->month) // <-- فلترة حسب الشهر الحالي
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


        // --- تحديث منطق جلب سجلات الأداء ---

        // 1. جلب سجلات إنشاء العقوبات والتقييمات
        $creationLogs = ActivityLog::where(function ($query) use ($teacher) {
            $query->where(function($q) use ($teacher) {
                $q->where('subject_type', \App\Models\Penalty::class)
                  ->whereIn('subject_id', $teacher->penalties->pluck('id'));
            })->orWhere(function($q) use ($teacher) {
                $q->where('subject_type', \App\Models\PerformanceEvaluation::class)
                  ->whereIn('subject_id', $teacher->evaluations->pluck('id'));
            });
        })->with('user:id,name')->latest()->get()->map(function ($log) {
            // توحيد شكل البيانات
            return [
                'type' => $log->subject_type,
                'date' => $log->created_at,
                'user' => $log->user,
                'details' => $log->details,
            ];
        });

        // 2. جلب سجلات الخصم التفصيلية الجديدة
        $deductionLogs = DeductionLog::whereIn('performance_evaluation_id', $teacher->evaluations->pluck('id'))
            ->with(['logger:id,name', 'penalty.penaltyType', 'evaluation', 'criterion'])
            ->latest()->get()->map(function ($log) {
                // توحيد شكل البيانات
                return [
                    'type' => 'deduction_applied', // نوع مخصص للتعرف عليه في الواجهة
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

        // 3. دمج كل السجلات، ترتيبها حسب التاريخ، وأخذ أحدث 10 منها
        $activityLogs = $creationLogs->merge($deductionLogs)
                                    ->sortByDesc('date')
                                    ->values() // لإعادة بناء مفاتيح المصفوفة
                                    ->take(10);

        return Inertia::render('School/Teachers/Show', [
            'teacher' => $teacher,
            'departments' => Department::all(['id', 'name']),
            'leaveTypes' => LeaveType::where('is_active', true)->get(['id', 'name']),
            'leaveBalances' => $leaveBalanceService->getAllBalancesForPerson($teacher),
            'criteria' => $activeCriteria,
            'averageEvaluationScore' => $averageScore ? round($averageScore, 2) : 0,
            'penaltyTypes' => PenaltyType::where('is_active', true)->get(['id', 'name']),
            'deductions' => $deductions,
            'activityLogs' => $activityLogs, // إرسال السجل المدمج والنهائي
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Teacher $teacher)
    {
        $this->authorize('edit', Teacher::class);
        $teacher->load(['user', 'department', 'contracts' => fn($q) => $q->latest()->limit(1), 'assignments', 'workExperiences']);
        $activeYear = \App\Models\AcademicYear::where('is_active', true)->first();

        return Inertia::render('School/Teachers/Edit', [
            'teacher' => $teacher,
            'departments' => Department::all(['id', 'name']),
            'grades' => $activeYear ? Grade::where('academic_year_id', $activeYear->id)
                                        ->with(['sections', 'subjects:id,name'])
                                        ->get() : [],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Teacher $teacher)
    {
        $this->authorize('update', $teacher);
        $validatedData = $request->validate([
            'personal.middle_name' => 'nullable|string|max:255',
            'personal.last_name' => 'nullable|string|max:255',
            'personal.mother_name' => 'nullable|string|max:255',
            'personal.marital_status' => 'nullable|string',
            'personal.nationality' => 'nullable|string|max:255',
            'personal.national_id_number' => ['nullable','string','max:255', Rule::unique('teachers')->ignore($teacher->id)],
            'personal.phone_number' => 'nullable|string|max:20',
            'personal.address' => 'nullable|string',
            'personal.date_of_birth' => 'nullable|date',
            'personal.gender' => 'nullable|in:male,female',
            'work_experiences' => 'nullable|array',
            'work_experiences.*.company_name' => 'required_with:work_experiences|string|max:255',
            'work_experiences.*.job_title' => 'required_with:work_experiences|string|max:255',
            'work_experiences.*.start_date' => 'nullable|date',
            'work_experiences.*.end_date' => 'nullable|date|after_or_equal:work_experiences.*.start_date',
            'work_experiences.*.description' => 'nullable|string',
            'employment.department_id' => 'required|exists:departments,id',
            'employment.fingerprint_id' => ['nullable','numeric', Rule::unique('teachers', 'fingerprint_id')->ignore($teacher->id)],
            'employment.hire_date' => 'required|date',
            'employment.employment_status' => 'required|in:active,on_leave,terminated',
            'employment.specialization' => 'required|string|max:255',
            'employment.contract_type' => 'required|string',
            'employment.start_date' => 'required|date',
            'employment.salary_type' => 'required|string',
            'employment.salary_amount' => 'required_if:employment.salary_type,monthly|nullable|numeric|min:0',
            'employment.hourly_rate' => 'required_if:employment.salary_type,hourly|nullable|numeric|min:0',
            'employment.working_hours_per_week' => 'nullable|numeric|min:0',
            'employment.notes' => 'nullable|string',
            'employment.status' => 'required|string',
            'assignments' => 'present|array',
            'assignments.*.subject_id' => 'required|exists:subjects,id',
            'assignments.*.section_id' => 'required|exists:sections,id',
            'account.name' => 'required|string|max:255',
            'account.email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($teacher->user_id)],
            'account.password' => ['nullable', 'confirmed', Rules\Password::defaults()],
        ]);

        DB::beginTransaction();
        try {
            // 1. Update User
            $teacher->user->update([
                'name' => $validatedData['account']['name'],
                'email' => $validatedData['account']['email'],
            ]);
            if (!empty($validatedData['account']['password'])) {
                $teacher->user->update(['password' => Hash::make($validatedData['account']['password'])]);
            }

            // 2. Prepare and Update Teacher data
            $personalData = $validatedData['personal'];
            $teacherData = array_merge(
                $personalData,
                [
                    'department_id' => $validatedData['employment']['department_id'],
                    'specialization' => $validatedData['employment']['specialization'],
                    'hire_date' => $validatedData['employment']['hire_date'],
                    'employment_status' => $validatedData['employment']['employment_status'],
                ]
            );
            $teacher->update($teacherData);

            // 3. Prepare and Update Contract data
            $contractData = [
                'contract_type' => $validatedData['employment']['contract_type'],
                'start_date' => $validatedData['employment']['start_date'],
                'salary_type' => $validatedData['employment']['salary_type'],
                'salary_amount' => $validatedData['employment']['salary_amount'],
                'hourly_rate' => $validatedData['employment']['hourly_rate'],
                'working_hours_per_week' => $validatedData['employment']['working_hours_per_week'],
                'notes' => $validatedData['employment']['notes'],
                'status' => $validatedData['employment']['status'],
            ];
            if ($teacher->contracts()->exists()) {
                $teacher->contracts()->latest()->first()->update($contractData);
            } else {
                $teacher->contracts()->create($contractData);
            }

            // 4. Update Work Experiences (delete all and recreate)
            $teacher->workExperiences()->delete();
            if (!empty($validatedData['work_experiences'])) {
                $teacher->workExperiences()->createMany($validatedData['work_experiences']);
            }

            // 5. Update Assignments (delete all and recreate)
            $teacher->assignments()->delete();
            if (!empty($validatedData['assignments'])) {
                $teacher->assignments()->createMany($validatedData['assignments']);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Teacher update failed for ID ' . $teacher->id . ': ' . $e->getMessage());
            return Redirect::back()->with('error', 'حدث خطأ أثناء تحديث بيانات المعلم.');
        }

        return Redirect::route('school.teachers.index')->with('success', 'تم تحديث بيانات المعلم بنجاح.');
    }


    /**
     * Update the personal information for the specified teacher.
     */
    public function updatePersonalInfo(Request $request, Teacher $teacher)
    {
        $this->authorize('update', $teacher);
        $validatedData = $request->validate([
            'user.name' => 'required|string|max:255',
            'user.email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($teacher->user_id)],
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'mother_name' => 'nullable|string|max:255',
            'marital_status' => 'nullable|string',
            'nationality' => 'nullable|string|max:255',
            'national_id_number' => ['nullable','string','max:255', Rule::unique('teachers')->ignore($teacher->id)],
            'phone_number' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
            'department_id' => 'required|exists:departments,id', // <-- إضافة التحقق من القسم
            'gender' => 'nullable|in:male,female',
        ]);

        DB::beginTransaction();
        try {
            $teacher->user->update($validatedData['user']);
            unset($validatedData['user']);
            $teacher->update($validatedData);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return Redirect::back()->with('error', 'حدث خطأ أثناء تحديث البيانات.');
        }
        return Redirect::back()->with('success', 'تم تحديث البيانات الشخصية بنجاح.');
    }
    /**
     * Store a new attachment for the specified teacher.
     */
    public function storeAttachment(Request $request, Teacher $teacher)
    {
        $this->authorize('create', Teacher::class);
        $request->validate([
            'attachment_name' => 'required|string|max:255',
            'attachment_file' => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048',
        ]);

        $file = $request->file('attachment_file');
        $path = $file->store("teachers/{$teacher->id}/attachments", 'public');

        $teacher->attachments()->create([
            'file_path' => $path,
            'file_name' => $request->input('attachment_name'),
            'file_type' => $file->getClientMimeType(),
        ]);

        return Redirect::back()->with('success', 'تم رفع المرفق بنجاح.');
    }
    public function storeLeave(Request $request, Teacher $teacher, LeaveBalanceService $leaveBalanceService)
    {
        $this->authorize('update', $teacher);

        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
        ]);

        $leaveType = LeaveType::find($validated['leave_type_id']);
        $requestedDuration = $leaveBalanceService->calculateLeaveDuration($validated['start_date'], $validated['end_date']);
        $availableBalance = $leaveBalanceService->getAvailableBalance($teacher, $leaveType);

        // التحقق من الرصيد
        if ($requestedDuration > $availableBalance) {
            // إرجاع خطأ محدد للواجهة
            throw ValidationException::withMessages([
                'end_date' => "الرصيد المتاح لهذا النوع من الإجازات هو {$availableBalance} يوم فقط.",
            ]);
        }

        $teacher->leaves()->create($validated);

        return Redirect::back()->with('success', 'تم تسجيل طلب الإجازة للمعلم بنجاح.');
    }
    public function storeWorkExperience(Request $request, Teacher $teacher)
    {
        $this->authorize('create', Teacher::class);
        $request->validate([
            'company_name' => 'required|string|max:255',
            'job_title' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'description' => 'nullable|string',
        ]);

        $teacher->workExperiences()->create($request->all());

        return Redirect::back()->with('success', 'تمت إضافة الخبرة العملية بنجاح.');
    }

    public function updateWorkExperience(Request $request, Teacher $teacher, WorkExperience $experience)
    {
        $this->authorize('update', $teacher);
        if ($experience->experienceable_id !== $teacher->id || $experience->experienceable_type !== Teacher::class) {
            abort(403);
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
    public function destroyWorkExperience(Teacher $teacher, WorkExperience $experience)
    {
        $this->authorize('delete', Teacher::class);
        if ($experience->experienceable_id !== $teacher->id || $experience->experienceable_type !== Teacher::class) {
            abort(403);
        }
        $experience->delete();
        return Redirect::back()->with('success', 'تم حذف الخبرة العملية بنجاح.');
    }

    public function updateAssignments(Request $request, Teacher $teacher)
    {
        $this->authorize('update', $teacher);
        $validatedData = $request->validate([
            'assignments' => 'present|array',
            'assignments.*.subject_id' => 'required|exists:subjects,id',
            'assignments.*.section_id' => 'required|exists:sections,id',
        ]);

        DB::beginTransaction();
        try {
            // A simple approach: delete all existing assignments and create the new ones.
            $teacher->assignments()->delete();
            $teacher->assignments()->createMany($validatedData['assignments']);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Teacher assignment update failed: ' . $e->getMessage());
            return Redirect::back()->with('error', 'حدث خطأ أثناء تحديث الإسنادات.');
        }

        return Redirect::back()->with('success', 'تم تحديث الإسنادات الأكاديمية بنجاح.');
    }

    public function updateFingerprintId(Request $request, Teacher $teacher)
    {

        $this->authorize('updateFingerprint', $teacher);
        $validated = $request->validate([
            'fingerprint_id' => [
                'required',
                'numeric',
                Rule::unique('teachers')->ignore($teacher->id),
                // Also check against employees table to avoid conflicts on the device
                Rule::unique('employees', 'fingerprint_id')
            ],
        ]);

        $teacher->update(['fingerprint_id' => $validated['fingerprint_id']]);

        return Redirect::back()->with('success', 'تم تحديث رقم البصمة بنجاح.');
    }


    public function showAttendance(Request $request, Teacher $teacher)
    {
        $this->authorize('view', $teacher);
        // تحميل بيانات المستخدم المرتبطة بالمعلم
        $teacher->load('user', 'department');

        // جلب الأشهر المتاحة في سجلات الحضور
        // ترتيب من الأحدث للأقدم (آخر شهر موجود في قاعدة البيانات أولاً)
        $availableMonths = $teacher->attendances()
            ->selectRaw('YEAR(attendance_date) as year, MONTH(attendance_date) as month')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get()
            ->map(function ($item) {
                $date = \Carbon\Carbon::create($item->year, $item->month, 1);
                return [
                    'value' => $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT),
                    'label' => $date->locale('ar')->translatedFormat('F Y'),
                    'year' => $item->year,
                    'month' => $item->month,
                ];
            });

        // فلترة حسب نوع الفلترة
        $filterType = $request->input('filter_type', 'month'); // 'month' or 'date_range'
        $selectedMonth = $request->input('month');
        $startDateInput = $request->input('start_date');
        $endDateInput = $request->input('end_date');

        $startDate = null;
        $endDate = null;
        $selectedYear = null;
        $selectedMonthNum = null;

        if ($filterType === 'date_range' && $startDateInput && $endDateInput) {
            // فلترة حسب نطاق التاريخ
            $startDate = \Carbon\Carbon::parse($startDateInput);
            $endDate = \Carbon\Carbon::parse($endDateInput);

            // جلب سجلات الحضور لنطاق التاريخ
            $attendancesQuery = $teacher->attendances()
                ->whereBetween('attendance_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->orderBy('attendance_date', 'desc');
        } else {
            // فلترة حسب الشهر المحدد
            if ($selectedMonth) {
                // إذا تم تحديد شهر من المستخدم، استخدمه
                [$selectedYear, $selectedMonthNum] = explode('-', $selectedMonth);
            } else {
                // إذا لم يتم تحديد شهر، استخدم آخر شهر موجود في قاعدة البيانات
                if ($availableMonths->isNotEmpty()) {
                    // $availableMonths مرتبة من الأحدث للأقدم، لذا first() يعطي آخر شهر موجود
                    $latestMonth = $availableMonths->first();
                    $selectedYear = $latestMonth['year'];
                    $selectedMonthNum = $latestMonth['month'];
                    $selectedMonth = $latestMonth['value'];
                } else {
                    // إذا لم توجد أي سجلات حضور، استخدم الشهر الحالي (حالة نادرة)
                    $selectedYear = now()->year;
                    $selectedMonthNum = now()->month;
                    $selectedMonth = now()->format('Y-m');
                }
            }

            // جلب سجلات الحضور للشهر المحدد
            $attendancesQuery = $teacher->attendances()
                ->whereYear('attendance_date', $selectedYear)
                ->whereMonth('attendance_date', $selectedMonthNum)
                ->orderBy('attendance_date', 'desc');

            // حساب الإحصائيات للشهر المحدد
            $startDate = \Carbon\Carbon::create($selectedYear, $selectedMonthNum, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();
        }

        $attendances = $attendancesQuery->paginate(15)->withQueryString();

        // جلب جميع أيام الشهر (بما فيها أيام الجمعة والسبت)
        $allDaysInMonth = collect();
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $allDaysInMonth->push($currentDate->copy());
            $currentDate->addDay();
        }

        // حساب أيام الحضور الفعلية
        if ($filterType === 'date_range' && $startDateInput && $endDateInput) {
            $attendanceRecords = $teacher->attendances()
                ->whereBetween('attendance_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->get()
                ->keyBy(function ($record) {
                    return $record->attendance_date->format('Y-m-d');
                });
        } else {
            $attendanceRecords = $teacher->attendances()
                ->whereYear('attendance_date', $selectedYear)
                ->whereMonth('attendance_date', $selectedMonthNum)
                ->get()
                ->keyBy(function ($record) {
                    return $record->attendance_date->format('Y-m-d');
                });
        }

        // حساب الإحصائيات
        $totalDays = $allDaysInMonth->count();
        $fridays = $allDaysInMonth->filter(fn($date) => $date->dayOfWeek === \Carbon\Carbon::FRIDAY)->count();
        $saturdays = $allDaysInMonth->filter(fn($date) => $date->dayOfWeek === \Carbon\Carbon::SATURDAY)->count();
        $weekendDays = $fridays + $saturdays;
        $workingDays = $totalDays - $weekendDays;

        $presentDays = $attendanceRecords->where('status', 'present')->count();
        $absentDays = $attendanceRecords->where('status', 'absent')->count();
        $lateDays = $attendanceRecords->where('status', 'late')->count();
        $leaveDays = $attendanceRecords->where('status', 'on_leave')->count();
        $holidayDays = $attendanceRecords->where('status', 'holiday')->count();

        // حساب الغيابات الفعلية (استثناء أيام الجمعة والسبت)
        $actualAbsentDays = $allDaysInMonth
            ->filter(function ($date) use ($attendanceRecords) {
                $dateStr = $date->format('Y-m-d');
                $isWeekend = in_array($date->dayOfWeek, [\Carbon\Carbon::FRIDAY, \Carbon\Carbon::SATURDAY]);
                $hasAttendance = $attendanceRecords->has($dateStr);

                // إذا كان يوم عطلة نهاية الأسبوع، لا نحسبه
                if ($isWeekend) {
                    return false;
                }

                // إذا لم يكن هناك سجل حضور، يعتبر غياب
                return !$hasAttendance;
            })
            ->count();

        // حساب أيام الحضور الفعلية (استثناء أيام الجمعة والسبت)
        $actualPresentDays = $allDaysInMonth
            ->filter(function ($date) use ($attendanceRecords) {
                $dateStr = $date->format('Y-m-d');
                $isWeekend = in_array($date->dayOfWeek, [\Carbon\Carbon::FRIDAY, \Carbon\Carbon::SATURDAY]);

                if ($isWeekend) {
                    return false;
                }

                $record = $attendanceRecords->get($dateStr);
                return $record && $record->status === 'present';
            })
            ->count();

        $statistics = [
            'total_days' => $totalDays,
            'working_days' => $workingDays,
            'weekend_days' => $weekendDays,
            'fridays' => $fridays,
            'saturdays' => $saturdays,
            'present_days' => $presentDays,
            'actual_present_days' => $actualPresentDays,
            'absent_days' => $absentDays,
            'actual_absent_days' => $actualAbsentDays,
            'late_days' => $lateDays,
            'leave_days' => $leaveDays,
            'holiday_days' => $holidayDays,
            'attendance_rate' => $workingDays > 0 ? round(($actualPresentDays / $workingDays) * 100, 2) : 0,
        ];

        // حساب أيام الغياب الفعلية مع التفاصيل
        $absentDaysList = $allDaysInMonth
            ->filter(function ($date) use ($attendanceRecords) {
                $dateStr = $date->format('Y-m-d');
                $isWeekend = in_array($date->dayOfWeek, [\Carbon\Carbon::FRIDAY, \Carbon\Carbon::SATURDAY]);
                $hasAttendance = $attendanceRecords->has($dateStr);

                // استثناء أيام نهاية الأسبوع
                if ($isWeekend) {
                    return false;
                }

                // إذا لم يكن هناك سجل حضور، يعتبر غياب
                return !$hasAttendance;
            })
            ->map(function ($date) {
                return [
                    'date' => $date->format('Y-m-d'),
                    'date_formatted' => $date->locale('ar')->translatedFormat('l، d F Y'),
                    'day_name' => $date->locale('ar')->translatedFormat('l'),
                    'day_number' => $date->day,
                ];
            })
            ->values();

        // إرسال البيانات إلى واجهة العرض
        return Inertia::render('School/Teachers/Attendance/Show', [
            'teacher' => $teacher,
            'attendances' => $attendances,
            'availableMonths' => $availableMonths,
            'filterType' => $filterType,
            'selectedMonth' => $selectedMonth,
            'startDate' => $startDateInput,
            'endDate' => $endDateInput,
            'statistics' => $statistics,
            'absentDaysList' => $absentDaysList,
            'filters' => $request->only('filter_type', 'month', 'start_date', 'end_date'),
        ]);
    }

    /**
     * Export absent days report for a teacher
     */
    public function exportAbsentDaysReport(Request $request, Teacher $teacher)
    {
        $this->authorize('view', $teacher);

        $filterType = $request->input('filter_type', 'month');
        $selectedMonth = $request->input('month');
        $startDateInput = $request->input('start_date');
        $endDateInput = $request->input('end_date');

        if ($filterType === 'date_range') {
            if (!$startDateInput || !$endDateInput) {
                return Redirect::back()->with('error', 'يرجى تحديد تاريخ البداية والنهاية');
            }
            $startDate = \Carbon\Carbon::parse($startDateInput);
            $endDate = \Carbon\Carbon::parse($endDateInput);
        } else {
            if (!$selectedMonth) {
                return Redirect::back()->with('error', 'يرجى تحديد الشهر');
            }
            [$selectedYear, $selectedMonthNum] = explode('-', $selectedMonth);
            $startDate = \Carbon\Carbon::create($selectedYear, $selectedMonthNum, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();
        }

        $allDaysInMonth = collect();
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $allDaysInMonth->push($currentDate->copy());
            $currentDate->addDay();
        }

        if ($filterType === 'date_range') {
            $attendanceRecords = $teacher->attendances()
                ->whereBetween('attendance_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->get()
                ->keyBy(function ($record) {
                    return $record->attendance_date->format('Y-m-d');
                });
        } else {
            $attendanceRecords = $teacher->attendances()
                ->whereYear('attendance_date', $selectedYear)
                ->whereMonth('attendance_date', $selectedMonthNum)
                ->get()
                ->keyBy(function ($record) {
                    return $record->attendance_date->format('Y-m-d');
                });
        }

        $absentDaysList = $allDaysInMonth
            ->filter(function ($date) use ($attendanceRecords) {
                $dateStr = $date->format('Y-m-d');
                $isWeekend = in_array($date->dayOfWeek, [\Carbon\Carbon::FRIDAY, \Carbon\Carbon::SATURDAY]);
                if ($isWeekend) return false;
                return !$attendanceRecords->has($dateStr);
            })
            ->map(function ($date) {
                return [
                    'date' => $date->format('Y-m-d'),
                    'date_formatted' => $date->locale('ar')->translatedFormat('l، d F Y'),
                    'day_name' => $date->locale('ar')->translatedFormat('l'),
                    'day_number' => $date->day,
                ];
            })
            ->values();

        // حساب الإحصائيات
        $fridays = $allDaysInMonth->filter(fn($date) => $date->dayOfWeek === \Carbon\Carbon::FRIDAY)->count();
        $saturdays = $allDaysInMonth->filter(fn($date) => $date->dayOfWeek === \Carbon\Carbon::SATURDAY)->count();
        $weekendDays = $fridays + $saturdays;
        $workingDays = $allDaysInMonth->count() - $weekendDays;

        $statistics = [
            'working_days' => $workingDays,
            'weekend_days' => $weekendDays,
            'absent_days_count' => $absentDaysList->count(),
        ];

        if ($filterType === 'date_range') {
            $periodName = $startDate->locale('ar')->translatedFormat('d F Y') . ' - ' . $endDate->locale('ar')->translatedFormat('d F Y');
        } else {
            $periodName = \Carbon\Carbon::create($selectedYear, $selectedMonthNum, 1)->locale('ar')->translatedFormat('F Y');
        }

        // تحميل جميع العلاقات المطلوبة بشكل صريح
        $teacher->load(['user', 'department']);

        // الحصول على الاسم الكامل من User model
        $teacherFullName = $teacher->user ? ($teacher->user->full_name ?? $teacher->user->name) : 'غير محدد';
        $fileName = 'ايام_الغياب_' . $teacherFullName . '_' . ($filterType === 'date_range' ? $startDate->format('Y-m-d') . '_' . $endDate->format('Y-m-d') : $periodName) . '.xlsx';

        return Excel::download(
            new TeacherAbsentDaysExport($absentDaysList, $teacher, $periodName, $statistics),
            $fileName
        );
    }

    /**
     * Export attendance report for a teacher
     */
    public function exportAttendanceReport(Request $request, Teacher $teacher)
    {
        $this->authorize('view', $teacher);

        $filterType = $request->input('filter_type', 'month');
        $selectedMonth = $request->input('month');
        $startDateInput = $request->input('start_date');
        $endDateInput = $request->input('end_date');

        if ($filterType === 'date_range') {
            if (!$startDateInput || !$endDateInput) {
                return Redirect::back()->with('error', 'يرجى تحديد تاريخ البداية والنهاية');
            }
            $startDate = \Carbon\Carbon::parse($startDateInput);
            $endDate = \Carbon\Carbon::parse($endDateInput);
        } else {
            if (!$selectedMonth) {
                return Redirect::back()->with('error', 'يرجى تحديد الشهر');
            }
            [$selectedYear, $selectedMonthNum] = explode('-', $selectedMonth);
            $startDate = \Carbon\Carbon::create($selectedYear, $selectedMonthNum, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();
        }

        $allDaysInMonth = collect();
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $allDaysInMonth->push($currentDate->copy());
            $currentDate->addDay();
        }

        // جلب جميع سجلات الحضور للفترة المحددة بدون pagination
        if ($filterType === 'date_range') {
            $attendanceRecords = $teacher->attendances()
                ->whereBetween('attendance_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->get()
                ->keyBy(function ($record) {
                    return $record->attendance_date->format('Y-m-d');
                });
        } else {
            $attendanceRecords = $teacher->attendances()
                ->whereYear('attendance_date', $selectedYear)
                ->whereMonth('attendance_date', $selectedMonthNum)
                ->get()
                ->keyBy(function ($record) {
                    return $record->attendance_date->format('Y-m-d');
                });
        }

        // إنشاء بيانات لجميع أيام الشهر
        $allDaysData = $allDaysInMonth->map(function ($date) use ($attendanceRecords) {
            $dateStr = $date->format('Y-m-d');
            $record = $attendanceRecords->get($dateStr);

            return [
                'date' => $dateStr,
                'check_in' => $record ? $record->check_in_time : null,
                'check_out' => $record ? $record->check_out_time : null,
                'status' => $record ? $record->status : null,
                'notes' => $record ? $record->notes : null,
            ];
        })->values();

        // حساب الإحصائيات
        $fridays = $allDaysInMonth->filter(fn($date) => $date->dayOfWeek === \Carbon\Carbon::FRIDAY)->count();
        $saturdays = $allDaysInMonth->filter(fn($date) => $date->dayOfWeek === \Carbon\Carbon::SATURDAY)->count();
        $weekendDays = $fridays + $saturdays;
        $workingDays = $allDaysInMonth->count() - $weekendDays;

        $actualAbsentDays = $allDaysInMonth
            ->filter(function ($date) use ($attendanceRecords) {
                $dateStr = $date->format('Y-m-d');
                $isWeekend = in_array($date->dayOfWeek, [\Carbon\Carbon::FRIDAY, \Carbon\Carbon::SATURDAY]);
                if ($isWeekend) return false;
                return !$attendanceRecords->has($dateStr);
            })
            ->count();

        $actualPresentDays = $allDaysInMonth
            ->filter(function ($date) use ($attendanceRecords) {
                $dateStr = $date->format('Y-m-d');
                $isWeekend = in_array($date->dayOfWeek, [\Carbon\Carbon::FRIDAY, \Carbon\Carbon::SATURDAY]);
                if ($isWeekend) return false;
                $record = $attendanceRecords->get($dateStr);
                return $record && $record->status === 'present';
            })
            ->count();

        $statistics = [
            'actual_present_days' => $actualPresentDays,
            'actual_absent_days' => $actualAbsentDays,
            'working_days' => $workingDays,
            'weekend_days' => $weekendDays,
        ];

        if ($filterType === 'date_range') {
            $periodName = $startDate->locale('ar')->translatedFormat('d F Y') . ' - ' . $endDate->locale('ar')->translatedFormat('d F Y');
        } else {
            $periodName = \Carbon\Carbon::create($selectedYear, $selectedMonthNum, 1)->locale('ar')->translatedFormat('F Y');
        }

        // تحميل جميع العلاقات المطلوبة بشكل صريح
        $teacher->load(['user', 'department']);

        // الحصول على الاسم الكامل من User model
        $teacherFullName = $teacher->user ? ($teacher->user->full_name ?? $teacher->user->name) : 'غير محدد';
        $fileName = 'سجلات_الحضور_' . $teacherFullName . '_' . ($filterType === 'date_range' ? $startDate->format('Y-m-d') . '_' . $endDate->format('Y-m-d') : $periodName) . '.xlsx';

        return Excel::download(
            new TeacherAttendanceExport($allDaysData, $teacher, $periodName, $statistics),
            $fileName
        );
    }

    /**
     * Sync today's attendance for a single teacher from the fingerprint device.
     * مزامنة حضور اليوم لمعلم واحد من جهاز البصمة
     */
    public function syncSingleAttendance(Teacher $teacher, FingerprintService $fingerprintService)
    {
        $this->authorize('create', Teacher::class);
        if (!$teacher->fingerprint_id) {
            return back()->with('error', 'هذا المعلم لا يملك رقم بصمة.');
        }

        try {
            // استدعاء الخدمة لمزامنة مستخدم واحد فقط
            $result = $fingerprintService->syncSingleUser(
                $teacher->fingerprint_id,
                now()->toDateString()
            );

            if ($result) {
                return back()->with('success', 'تمت مزامنة حضور المعلم بنجاح.');
            } else {
                return back()->with('info', 'لا توجد سجلات بصمة جديدة للمعلم اليوم.');
            }
        } catch (\Exception $e) {
            // في حال حدوث أي خطأ
            return back()->with('error', 'حدث خطأ أثناء الاتصال بالجهاز: ' . $e->getMessage());
        }
    }
}

