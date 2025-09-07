<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Leave;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;

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
    public function update(Request $request, Leave $leaf)
    {
        Log::info("Attempting to update leave request ID: {$leaf->id}");
        Log::info("Incoming data:", $request->all());

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);
        
        Log::info("Validation passed. New status will be: {$validated['status']}");

        try {
            $leaf->update([
                'status' => $validated['status'],
                'approved_by' => auth()->id(), // Record who took the action
            ]);

            Log::info("Successfully updated leave ID: {$leaf->id}");

        } catch (\Exception $e) {
            Log::error("Failed to update leave ID: {$leaf->id}. Error: " . $e->getMessage());
            return Redirect::back()->with('error', 'حدث خطأ فني أثناء تحديث الطلب.');
        }

        return Redirect::back()->with('success', 'تم تحديث حالة الطلب بنجاح.');
    }
}
