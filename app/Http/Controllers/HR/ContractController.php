<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class ContractController extends Controller
{
    public function index()
    {
        $contracts = Contract::with('employee.user')->latest()->paginate(10);
        return Inertia::render('HR/Contracts/Index', [
            'contracts' => $contracts,
        ]);
    }

    /**
     * Store a newly created contract for an employee.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'contract_type' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'job_title' => 'required|string|max:255',
            'basic_salary' => 'required|numeric|min:0',
            'housing_allowance' => 'nullable|numeric|min:0',
            'transportation_allowance' => 'nullable|numeric|min:0',
            'other_allowances' => 'nullable|numeric|min:0',
            'status' => 'required|in:active,pending,expired,terminated',
        ]);

        Contract::create($validatedData);

        return Redirect::back()->with('success', 'تمت إضافة العقد الجديد بنجاح.');
    }

    /**
     * Update the specified contract.
     */
    public function update(Request $request, Contract $contract)
    {
        $validatedData = $request->validate([
            'contract_type' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'job_title' => 'required|string|max:255',
            'basic_salary' => 'required|numeric|min:0',
            'housing_allowance' => 'nullable|numeric|min:0',
            'transportation_allowance' => 'nullable|numeric|min:0',
            'other_allowances' => 'nullable|numeric|min:0',
            'status' => 'required|in:active,pending,expired,terminated',
        ]);

        $contract->update($validatedData);

        return Redirect::back()->with('success', 'تم تحديث بيانات العقد بنجاح.');
    }
    
    /**
     * Update only the status of a contract.
     */
    public function updateStatus(Request $request, Contract $contract)
    {
        $request->validate(['status' => 'required|in:active,expired,terminated']);
        
        $contract->update(['status' => $request->status]);

        return Redirect::back()->with('success', 'تم تحديث حالة العقد بنجاح.');
    }
}

