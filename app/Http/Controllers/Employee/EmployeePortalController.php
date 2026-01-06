<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Leave;
use App\Models\Payslip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;

class EmployeePortalController extends Controller
{
    /**
     * Display the employee's payslips.
     */
    public function myPayslips()
    {
        $employeeId = Auth::user()->employee->id;

        // Soft deleted payslips are automatically excluded by Laravel's SoftDeletes
        $payslips = Payslip::where('employee_id', $employeeId)
            ->latest()
            ->paginate(10);

        return Inertia::render('Employee/MyPayslips/Index', [
            'payslips' => $payslips,
        ]);
    }

    /**
     * Show the details of a specific payslip for the employee.
     */
    public function showMyPayslip(Payslip $payslip)
    {
        // Authorization: Ensure the payslip belongs to the logged-in employee
        if ($payslip->employee_id !== Auth::user()->employee->id) {
            abort(403);
        }

        $payslip->load(['employee.user', 'employee.department', 'contract', 'items']);

        return Inertia::render('Employee/MyPayslips/Show', [
            'payslip' => $payslip
        ]);
    }

    /**
     * Display the employee's leave requests.
     */
    public function myLeaves()
    {
        $employeeId = Auth::user()->employee->id;

        $leaves = Leave::where('employee_id', $employeeId)
            ->latest()
            ->paginate(10);

        return Inertia::render('Employee/MyLeaves/Index', [
            'leaves' => $leaves
        ]);
    }

    /**
     * Show the form for creating a new leave request.
     */
    public function createMyLeave()
    {
        return Inertia::render('Employee/MyLeaves/Create');
    }

    /**
     * Store a newly created leave request in storage.
     */
    public function storeMyLeave(Request $request)
    {
        $request->validate([
            'leave_type' => 'required|in:annual,sick,unpaid,emergency',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:1000',
        ]);

        $employeeId = Auth::user()->employee->id;

        Leave::create([
            'employee_id' => $employeeId,
            'leave_type' => $request->leave_type,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'reason' => $request->reason,
            'status' => 'pending', // Always pending when created by employee
        ]);

        return Redirect::route('employee.leaves.index')->with('success', 'تم تقديم طلب الإجازة بنجاح.');
    }
}
