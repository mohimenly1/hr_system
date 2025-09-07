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

class TeacherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $teachers = Teacher::with(['user', 'department'])->latest()->paginate(10);
        return Inertia::render('School/Teachers/Index', [
            'teachers' => $teachers,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
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
    public function show(Teacher $teacher)
     {
         // Eager load all necessary relationships for the profile view
         $teacher->load(['user', 'department', 'contracts', 'attachments', 'leaves', 'workExperiences', 'assignments.subject', 'assignments.section.grade']);
 
         // Add a downloadable URL to each attachment
         $teacher->attachments->each(function ($attachment) {
             $attachment->url = Storage::url($attachment->file_path);
         });
 
         // --- THIS IS THE FIX ---
         // Load grades for the active academic year to populate the assignments modal
         $activeYear = \App\Models\AcademicYear::where('is_active', true)->first();
 
         return Inertia::render('School/Teachers/Show', [
             'teacher' => $teacher,
             'grades' => $activeYear ? Grade::where('academic_year_id', $activeYear->id)
                                         ->with(['sections', 'subjects:id,name'])
                                         ->get() : [],
         ]);
     }
    
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Teacher $teacher)
    {
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
    public function storeLeave(Request $request, Teacher $teacher)
    {
        $request->validate([
            'leave_type' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
        ]);

        $teacher->leaves()->create([
            'leave_type' => $request->leave_type,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return Redirect::back()->with('success', 'تم تسجيل طلب الإجازة للمعلم بنجاح.');
    }
    public function storeWorkExperience(Request $request, Teacher $teacher)
    {
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
        if ($experience->experienceable_id !== $teacher->id || $experience->experienceable_type !== Teacher::class) {
            abort(403);
        }
        $experience->delete();
        return Redirect::back()->with('success', 'تم حذف الخبرة العملية بنجاح.');
    }

    public function updateAssignments(Request $request, Teacher $teacher)
    {
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
}

