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
         $employee->load(['user', 'department', 'contracts', 'attachments']);
 
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
        // Merge nested data for validation
        $validatedData = $request->validate([
            // Personal Info
            'personal.phone_number' => 'nullable|string|max:20',
            'personal.address' => 'nullable|string',
            'personal.date_of_birth' => 'nullable|date',
            'personal.gender' => 'nullable|in:male,female',
            'personal.attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048',

            // Employment & Contract Info
            'employment.department_id' => 'required|exists:departments,id',
            'employment.employee_id' => 'required|string|max:255|unique:employees,employee_id',
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
            'account.email' => 'required|string|email|max:255|unique:users,email',
            'account.password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Use a database transaction to ensure all or nothing is saved
        DB::beginTransaction();
        try {
            // 1. Create User
            $user = User::create([
                'name' => $validatedData['account']['name'],
                'email' => $validatedData['account']['email'],
                'password' => Hash::make($validatedData['account']['password']),
            ]);

            // 2. Assign Role
            $user->assignRole('employee');

            // 3. Create Employee
            $employee = $user->employee()->create([
                'department_id' => $validatedData['employment']['department_id'],
                'employee_id' => $validatedData['employment']['employee_id'],
                'job_title' => $validatedData['employment']['job_title'],
                'hire_date' => $validatedData['employment']['hire_date'],
                'employment_status' => $validatedData['employment']['employment_status'],
                'phone_number' => $validatedData['personal']['phone_number'],
                'address' => $validatedData['personal']['address'],
                'date_of_birth' => $validatedData['personal']['date_of_birth'],
                'gender' => $validatedData['personal']['gender'],
            ]);

            // 4. Create Contract
            $employee->contracts()->create([
                 'contract_type' => $validatedData['employment']['contract_type'],
                 'start_date' => $validatedData['employment']['hire_date'], // Often the same as hire date
                 'job_title' => $validatedData['employment']['job_title'],
                 'status' => $validatedData['employment']['status'],
                 'basic_salary' => $validatedData['employment']['basic_salary'],
                 'housing_allowance' => $validatedData['employment']['housing_allowance'],
                 'transportation_allowance' => $validatedData['employment']['transportation_allowance'],
                 'other_allowances' => $validatedData['employment']['other_allowances'],
                 'annual_leave_days' => $validatedData['employment']['annual_leave_days'],
                 'notice_period_days' => $validatedData['employment']['notice_period_days'],
                 'notes' => $validatedData['employment']['notes'],
            ]);

            // 5. Handle Attachments
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
            
            DB::commit(); // All good, save the changes

        } catch (\Exception $e) {
            DB::rollBack(); // Something went wrong, revert changes
            // Optionally, log the error and return a specific error message
            return Redirect::back()->with('error', 'حدث خطأ أثناء حفظ بيانات الموظف. يرجى المحاولة مرة أخرى.');
        }

        return Redirect::route('hr.employees.index')->with('success', 'تمت إضافة الموظف وعقده بنجاح.');
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
        // Load the employee with their user, department, and the latest contract
        $employee->load(['user', 'department', 'contracts' => function ($query) {
            $query->latest()->limit(1);
        }]);

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
            'personal.phone_number' => 'nullable|string|max:20',
            'personal.address' => 'nullable|string',
            'personal.date_of_birth' => 'nullable|date',
            'personal.gender' => 'nullable|in:male,female',
            'employment.department_id' => 'required|exists:departments,id',
            // --- FIX: Explicitly define the column name for the unique rule ---
            'employment.employee_id' => ['required', 'string', 'max:255', Rule::unique('employees', 'employee_id')->ignore($employee->id)],
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
             // --- FIX: Explicitly define the column name for the unique rule ---
            'account.email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee->user_id)],
            'account.password' => ['nullable', 'confirmed', Rules\Password::defaults()],
        ]);

        DB::beginTransaction();
        try {
            $employee->user->update([
                'name' => $validatedData['account']['name'],
                'email' => $validatedData['account']['email'],
            ]);
            if (!empty($validatedData['account']['password'])) {
                $employee->user->update(['password' => Hash::make($validatedData['account']['password'])]);
            }

            $employee->update([
                'department_id' => $validatedData['employment']['department_id'],
                'employee_id' => $validatedData['employment']['employee_id'],
                'job_title' => $validatedData['employment']['job_title'],
                'hire_date' => $validatedData['employment']['hire_date'],
                'employment_status' => $validatedData['employment']['employment_status'],
                'phone_number' => $validatedData['personal']['phone_number'],
                'address' => $validatedData['personal']['address'],
                'date_of_birth' => $validatedData['personal']['date_of_birth'],
                'gender' => $validatedData['personal']['gender'],
            ]);

            if ($employee->contracts()->exists()) {
                $employee->contracts()->latest()->first()->update([
                    'contract_type' => $validatedData['employment']['contract_type'],
                    'start_date' => $validatedData['employment']['start_date'],
                    'job_title' => $validatedData['employment']['job_title'],
                    'status' => $validatedData['employment']['status'],
                    'basic_salary' => $validatedData['employment']['basic_salary'],
                    'housing_allowance' => $validatedData['employment']['housing_allowance'] ?? 0,
                    'transportation_allowance' => $validatedData['employment']['transportation_allowance'] ?? 0,
                    'other_allowances' => $validatedData['employment']['other_allowances'] ?? 0,
                    'annual_leave_days' => $validatedData['employment']['annual_leave_days'],
                    'notice_period_days' => $validatedData['employment']['notice_period_days'],
                    'notes' => $validatedData['employment']['notes'],
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return Redirect::back()->with('error', 'حدث خطأ أثناء تحديث بيانات الموظف.');
        }

        return Redirect::route('hr.employees.index')->with('success', 'تم تحديث بيانات الموظف بنجاح.');
    }

}

