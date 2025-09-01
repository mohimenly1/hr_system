<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Leave;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;

class LeaveController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $leaves = Leave::with('employee.user')->latest()->paginate(15);
        return Inertia::render('HR/Leaves/Index', [
            'leaves' => $leaves
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $employees = Employee::with('user')->get()->map(fn ($e) => [
            'id' => $e->id, 
            'name' => $e->user->name
        ]);

        return Inertia::render('HR/Leaves/Create', [
            'employees' => $employees,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
            'status' => 'required|in:pending,approved,rejected',
        ]);

        Leave::create($request->all());

        return Redirect::route('hr.leaves.index')->with('success', 'تم تسجيل طلب الإجازة بنجاح.');
    }
    public function update(Request $request, Leave $leave)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        $leave->update([
            'status' => $request->status,
            'approved_by' => auth()->id(), // Record who took the action
        ]);

        return Redirect::back()->with('success', 'تم تحديث حالة الطلب بنجاح.');
    }
}
