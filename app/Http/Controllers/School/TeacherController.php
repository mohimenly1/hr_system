<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Grade;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TeacherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $teachers = Teacher::with(['user', 'department', 'assignments.subject', 'assignments.section.grade'])->latest()->paginate(10);
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
            'personal.phone_number' => 'nullable|string|max:20',
            'personal.address' => 'nullable|string',
            'personal.date_of_birth' => 'nullable|date',
            'personal.gender' => 'nullable|in:male,female',
            'personal.marital_status' => 'nullable|string',
            'personal.emergency_contact_name' => 'nullable|string|max:255',
            'personal.emergency_contact_phone' => 'nullable|string|max:20',
            'personal.attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048',
            'employment.department_id' => 'required|exists:departments,id',
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

            // --- THIS IS THE FIX ---
            // 1. Prepare data specifically for the Teacher model
            $teacherData = $validatedData['personal'];
            $teacherData['department_id'] = $validatedData['employment']['department_id'];
            $teacherData['specialization'] = $validatedData['employment']['specialization'];
            $teacherData['hire_date'] = $validatedData['employment']['hire_date'];
            $teacherData['employment_status'] = $validatedData['employment']['employment_status'];

            // 2. Create the teacher with only its own data
            $teacher = $user->teacher()->create($teacherData);
            
            // 3. Prepare and create the contract with its own data
            $contractData = $validatedData['employment'];
            $teacher->contracts()->create($contractData);
            
            // 4. Create assignments
            $teacher->assignments()->createMany($validatedData['assignments']);
            
            // 5. Handle attachments
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
            Log::error('Error creating teacher: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return Redirect::back()->with('error', 'حدث خطأ أثناء حفظ بيانات المعلم. يرجى مراجعة سجل الأخطاء.');
        }

        return Redirect::route('school.teachers.index')->with('success', 'تمت إضافة المعلم وعقده بنجاح.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Teacher $teacher)
    {
        // Eager load all relationships including attachments
        $teacher->load(['user', 'department', 'contracts' => fn($q) => $q->latest(), 'assignments.subject', 'assignments.section.grade', 'attachments']);

        // Add a downloadable URL to each attachment
        $teacher->attachments->each(function ($attachment) {
            $attachment->url = Storage::url($attachment->file_path);
        });

        return Inertia::render('School/Teachers/Show', [
            'teacher' => $teacher
        ]);
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
}

