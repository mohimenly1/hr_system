<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with live stats.
     */
    public function index(): Response
    {
        // Simple Stats
        $employeeCount = Employee::where('employment_status', 'active')->count();
        $departmentCount = Department::count();
        $pendingLeavesCount = Leave::where('status', 'pending')->count();
        $onLeaveTodayCount = Leave::where('status', 'approved')
            ->where('start_date', '<=', today())
            ->where('end_date', '>=', today())
            ->count();

        // Chart Data: Employees per Department
        $employeesPerDepartment = Department::withCount(['employees' => function ($query) {
            $query->where('employment_status', 'active');
        }])
        ->get(['name', 'employees_count'])
        ->map(fn ($dept) => ['name' => $dept->name, 'count' => $dept->employees_count]);

        // Chart Data: Attendance for the last 7 days
        $attendanceLastWeek = collect(range(6, 0))->map(function ($day) {
            $date = Carbon::today()->subDays($day);
            // --- FIX: Changed 'date' to 'attendance_date' ---
            $present = Attendance::where('attendance_date', $date)->where('status', 'present')->count();
            $absent = Attendance::where('attendance_date', $date)->where('status', 'absent')->count();
            return [
                'date' => $date->format('M d'),
                'present' => $present,
                'absent' => $absent,
            ];
        });

        return Inertia::render('Dashboard', [
            'stats' => [
                ['name' => 'الموظفين النشطين', 'value' => $employeeCount, 'icon' => 'fas fa-users'],
                ['name' => 'الأقسام', 'value' => $departmentCount, 'icon' => 'fas fa-building'],
                ['name' => 'طلبات إجازة معلقة', 'value' => $pendingLeavesCount, 'icon' => 'fas fa-clock'],
                ['name' => 'موظفين في إجازة اليوم', 'value' => $onLeaveTodayCount, 'icon' => 'fas fa-calendar-check'],
            ],
            'employeesPerDepartment' => $employeesPerDepartment,
            'attendanceLastWeek' => $attendanceLastWeek,
        ]);
    }
}

