<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Subject;
use App\Models\Grade;
use App\Models\Section;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class TeacherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $teachers = Teacher::with(['user', 'department', 'subjects', 'contracts'])->latest()->paginate(10);
        return Inertia::render('School/Teachers/Index', [
            'teachers' => $teachers,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Load all departments
        $departments = Department::all(['id', 'name']);
        
        // Load all grades with their sections
        $grades = Grade::with('sections')->get();
        
        // Load all subjects separately
        $allSubjects = Subject::all(['id', 'name']);
    
        return Inertia::render('School/Teachers/Create', [
            'departments' => $departments,
            'grades' => $grades,
            'allSubjects' => $allSubjects,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            // Personal Info
            'personal.phone_number' => 'nullable|string|max:20',
            'personal.address' => 'nullable|string',
            'personal.date_of_birth' => 'nullable|date',
            'personal.gender' => 'nullable|in:male,female',
            'personal.marital_status' => 'nullable|string',
            'personal.emergency_contact_name' => 'nullable|string|max:255',
            'personal.emergency_contact_phone' => 'nullable|string|max:20',

            // Employment & Contract Info
            'employment.department_id' => 'required|exists:departments,id',
            'employment.hire_date' => 'required|date',
            'employment.employment_status' => 'required|in:active,on_leave,terminated',
            'employment.specialization' => 'required|string|max:255',
            'employment.contract_type' => 'required|string',
            'employment.start_date' => 'required|date',
            'employment.salary_type' => 'required|string',
            'employment.salary_amount' => 'required_if:employment.salary_type,fixed|nullable|numeric|min:0',
            'employment.hourly_rate' => 'required_if:employment.salary_type,hourly|nullable|numeric|min:0',
            'employment.working_hours_per_week' => 'nullable|numeric|min:0',
            'employment.notes' => 'nullable|string',
            'employment.status' => 'required|string',

            // Subject Assignment Info
            'subjects' => 'present|array',
            'subjects.*.id' => 'required|exists:subjects,id',

            // Account Info
            'account.name' => 'required|string|max:255',
            'account.email' => 'required|string|email|max:255|unique:users,email',
            'account.password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        DB::beginTransaction();
        try {
            // 1. Create User
            $user = User::create([
                'name' => $validatedData['account']['name'],
                'email' => $validatedData['account']['email'],
                'password' => Hash::make($validatedData['account']['password']),
            ]);

            // 2. Assign Role
            $user->assignRole('teacher');

            // 3. Create Teacher
            $teacher = $user->teacher()->create([
                'department_id' => $validatedData['employment']['department_id'],
                'specialization' => $validatedData['employment']['specialization'],
                'hire_date' => $validatedData['employment']['hire_date'],
                'employment_status' => $validatedData['employment']['employment_status'],
                'phone_number' => $validatedData['personal']['phone_number'],
                'address' => $validatedData['personal']['address'],
                'date_of_birth' => $validatedData['personal']['date_of_birth'],
                'gender' => $validatedData['personal']['gender'],
                'marital_status' => $validatedData['personal']['marital_status'],
                'emergency_contact_name' => $validatedData['personal']['emergency_contact_name'],
                'emergency_contact_phone' => $validatedData['personal']['emergency_contact_phone'],
            ]);

            // 4. Create Contract
            $teacher->contracts()->create([
                'contract_type' => $validatedData['employment']['contract_type'],
                'start_date' => $validatedData['employment']['start_date'],
                'salary_type' => $validatedData['employment']['salary_type'],
                'salary_amount' => $validatedData['employment']['salary_amount'],
                'hourly_rate' => $validatedData['employment']['hourly_rate'],
                'working_hours_per_week' => $validatedData['employment']['working_hours_per_week'],
                'notes' => $validatedData['employment']['notes'],
                'status' => $validatedData['employment']['status'],
            ]);

            // 5. Sync Subjects
            $subjectIds = collect($validatedData['subjects'])->pluck('id')->toArray();
            $teacher->subjects()->sync($subjectIds);
            
            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            return Redirect::back()->with('error', 'حدث خطأ أثناء حفظ بيانات المعلم. يرجى المحاولة مرة أخرى.');
        }

        return Redirect::route('school.teachers.index')->with('success', 'تمت إضافة المعلم وعقده بنجاح.');
    }
}