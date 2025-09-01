<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $attendances = Attendance::with('employee.user')
            ->latest('attendance_date')
            ->paginate(20);

        return Inertia::render('HR/Attendances/Index', [
            'attendances' => $attendances
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

        return Inertia::render('HR/Attendances/Create', [
            'employees' => $employees,
            'today' => Carbon::now()->format('Y-m-d')
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'attendance_date' => 'required|date',
            'check_in_time' => 'nullable|date_format:H:i',
            'check_out_time' => 'nullable|date_format:H:i|after:check_in_time',
            'status' => 'required|in:present,absent,late,on_leave,holiday',
        ], [
            'employee_id.unique' => 'تم تسجيل حضور لهذا الموظف في هذا اليوم مسبقاً.'
        ]);

        // Prevent duplicate entry for the same employee on the same day
        Attendance::updateOrCreate(
            [
                'employee_id' => $request->employee_id,
                'attendance_date' => $request->attendance_date,
            ],
            $request->only(['check_in_time', 'check_out_time', 'status', 'notes'])
        );
        
        return Redirect::route('hr.attendances.index')->with('success', 'تم تسجيل الحضور بنجاح.');
    }
}
