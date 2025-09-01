<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;

class ContractController extends Controller
{
    public function index()
    {
        $contracts = Contract::with('employee.user')->latest()->paginate(10);
        return Inertia::render('HR/Contracts/Index', [
            'contracts' => $contracts,
        ]);
    }

    public function create()
    {
        // Eager load the 'user' relationship to display employee names
        $employees = Employee::with('user')->get()->map(function ($employee) {
            return [
                'id' => $employee->id,
                'name' => $employee->user->name,
            ];
        });

        return Inertia::render('HR/Contracts/Create', [
            'employees' => $employees,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'contract_type' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'probation_end_date' => 'nullable|date|after_or_equal:start_date',
            'job_title' => 'required|string|max:255',
            'status' => 'required|in:active,pending,expired,terminated',
            'basic_salary' => 'required|numeric|min:0',
            'housing_allowance' => 'nullable|numeric|min:0',
            'transportation_allowance' => 'nullable|numeric|min:0',
            'other_allowances' => 'nullable|numeric|min:0',
            'working_hours_per_day' => 'nullable|integer|min:1',
            'annual_leave_days' => 'nullable|integer|min:0',
            'notice_period_days' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        Contract::create($request->all());

        return Redirect::route('hr.contracts.index')->with('success', 'تم إنشاء العقد بنجاح.');
    }
}

