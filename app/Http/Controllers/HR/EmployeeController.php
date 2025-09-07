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

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $employees = Employee::with(['user', 'department'])->latest()->paginate(10);
        return Inertia::render('HR/Employees/Index', [
            'employees' => $employees,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */

     public function show(Employee $employee)
     {
         // Eager load all necessary relationships for the profile view
         $employee->load(['user', 'department', 'contracts', 'attachments', 'leaves', 'workExperiences']);
 
         // Add a downloadable URL to each attachment
         $employee->attachments->each(function ($attachment) {
             $attachment->url = Storage::url($attachment->file_path);
         });
 
         return Inertia::render('HR/Employees/Show', [
             'employee' => $employee
         ]);
     }
    public function create()
    {
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

    public function storeLeave(Request $request, Employee $employee)
    {
        $request->validate([
            'leave_type' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
        ]);

        $employee->leaves()->create([
            'leave_type' => $request->leave_type,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'reason' => $request->reason,
            'status' => 'pending', // Default status
        ]);

        return Redirect::back()->with('success', 'تم تسجيل طلب الإجازة للموظف بنجاح.');
    }

    public function storeWorkExperience(Request $request, Employee $employee)
    {
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
        if ($experience->employee_id !== $employee->id) {
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
    public function destroyWorkExperience(Employee $employee, WorkExperience $experience)
    {
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
        $validatedData = $request->validate([
            'user.name' => 'required|string|max:255',
            'user.email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee->user_id)],
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
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
}

