<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();

        if ($user->hasAnyRole(['admin', 'hr-manager'])) {
            return $this->adminDashboard();
        }

        if ($user->hasRole('department-manager')) {
            return $this->departmentManagerDashboard($user, $request);
        }

        return Inertia::render('Dashboard', ['stats' => [], 'userRole' => 'other']);
    }

    private function adminDashboard(): Response
    {
        $employeeCount = Employee::where('employment_status', 'active')->count();
        $departmentCount = Department::count();
        $pendingLeavesCount = Leave::where('status', 'pending')->count();
        $onLeaveTodayCount = Leave::where('status', 'approved')
            ->where('start_date', '<=', today())
            ->where('end_date', '>=', today())
            ->count();

        $employeesPerDepartment = Department::withCount(['employees' => fn ($q) => $q->where('employment_status', 'active')])
            ->get(['name', 'employees_count'])
            ->map(fn ($dept) => ['name' => $dept->name, 'count' => $dept->employees_count]);

        $attendanceLastWeek = collect(range(6, 0))->map(function ($day) {
            $date = Carbon::today()->subDays($day);
            $present = Attendance::where('attendance_date', $date)->whereIn('status', ['present', 'late'])->count();
            $absent = Attendance::where('attendance_date', $date)->where('status', 'absent')->count();
            return ['date' => $date->format('M d'), 'present' => $present, 'absent' => $absent];
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
            'userRole' => 'admin',
        ]);
    }

    private function departmentManagerDashboard(User $user, Request $request): Response
    {
        $managedDepartments = Department::where('manager_id', $user->id)->get();

        if ($managedDepartments->isEmpty()) {
            return Inertia::render('Dashboard', [
                'stats' => [], 
                'managedDepartments' => [],
                'activeDepartment' => null,
                'userRole' => 'department-manager'
            ]);
        }

        $activeDepartmentId = $request->input('department_id', $managedDepartments->first()->id);
        $activeDepartment = $managedDepartments->firstWhere('id', $activeDepartmentId) ?? $managedDepartments->first();

        $personnelIds = Employee::where('department_id', $activeDepartment->id)->pluck('id')
            ->concat(
                \App\Models\Teacher::where('department_id', $activeDepartment->id)->pluck('id')
            );

        $personnelCount = $personnelIds->count();
        $pendingLeavesCount = Leave::whereIn('leavable_id', $personnelIds)->where('status', 'pending')->count();
        $onLeaveTodayCount = Leave::whereIn('leavable_id', $personnelIds)
                                    ->where('status', 'approved')
                                    ->where('start_date', '<=', today())
                                    ->where('end_date', '>=', today())
                                    ->count();

        $attendanceLastWeek = collect(range(6, 0))->map(function ($day) use ($activeDepartment) {
            $date = Carbon::today()->subDays($day);
            $present = Attendance::where(function ($q) use ($activeDepartment) {
                $q->whereHasMorph('attendable', [Employee::class], fn($sub) => $sub->where('department_id', $activeDepartment->id))
                  ->orWhereHasMorph('attendable', [\App\Models\Teacher::class], fn($sub) => $sub->where('department_id', $activeDepartment->id));
            })->where('attendance_date', $date)->whereIn('status', ['present', 'late'])->count();
            
            $absent = Attendance::where(function ($q) use ($activeDepartment) {
                $q->whereHasMorph('attendable', [Employee::class], fn($sub) => $sub->where('department_id', $activeDepartment->id))
                  ->orWhereHasMorph('attendable', [\App\Models\Teacher::class], fn($sub) => $sub->where('department_id', $activeDepartment->id));
            })->where('attendance_date', $date)->where('status', 'absent')->count();

            return ['date' => $date->format('M d'), 'present' => $present, 'absent' => $absent];
        });

        return Inertia::render('Dashboard', [
            'stats' => [
                ['name' => 'الأعضاء في القسم', 'value' => $personnelCount, 'icon' => 'fas fa-users'],
                ['name' => 'طلبات إجازة معلقة', 'value' => $pendingLeavesCount, 'icon' => 'fas fa-clock'],
                ['name' => 'أعضاء في إجازة اليوم', 'value' => $onLeaveTodayCount, 'icon' => 'fas fa-calendar-check'],
            ],
            'attendanceLastWeek' => $attendanceLastWeek,
            'managedDepartments' => $managedDepartments,
            'activeDepartment' => $activeDepartment,
            'userRole' => 'department-manager',
        ]);
    }
}

