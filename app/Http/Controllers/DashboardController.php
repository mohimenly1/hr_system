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
use Illuminate\Support\Facades\DB;
use App\Models\PerformanceEvaluation;
use App\Models\Penalty;
use App\Models\Teacher;
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
        // --- الإحصائيات العامة ---
        $employeeCount = Employee::where('employment_status', 'active')->count();
        $departmentCount = Department::count();
        $pendingLeavesCount = Leave::where('status', 'pending')->count();
        $onLeaveTodayCount = Leave::where('status', 'approved')
            ->where('start_date', '<=', today())
            ->where('end_date', '>=', today())
            ->count();

        // --- بيانات المخططات البيانية ---
        $employeesPerDepartment = Department::withCount(['employees' => fn ($q) => $q->where('employment_status', 'active')])
            ->get(['name', 'employees_count'])
            ->map(fn ($dept) => ['name' => $dept->name, 'count' => $dept->employees_count]);

        $attendanceLastWeek = collect(range(6, 0))->map(function ($day) {
            $date = Carbon::today()->subDays($day);
            $present = Attendance::where('attendance_date', $date)->whereIn('status', ['present', 'late'])->count();
            $absent = Attendance::where('attendance_date', $date)->where('status', 'absent')->count();
            return ['date' => $date->format('M d'), 'present' => $present, 'absent' => $absent];
        });

        // --- إحصائيات الأداء ---
        $recentEvaluationsScope = PerformanceEvaluation::with(['evaluable.user', 'evaluable.department'])
                                ->where('evaluation_date', '>=', now()->subMonths(6));

        $topPerformers = (clone $recentEvaluationsScope)->orderByDesc('final_score_percentage')->limit(5)->get();
        $lowestPerformers = (clone $recentEvaluationsScope)->orderBy('final_score_percentage')->limit(5)->get();

        $penaltyStats = Penalty::join('penalty_types', 'penalties.penalty_type_id', '=', 'penalty_types.id')
            ->select('penalty_types.name', DB::raw('count(penalties.id) as count'))
            ->groupBy('penalty_types.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        // --- التحقق إذا كان المدير العام هو أيضاً مدير قسم ---
        $user = Auth::user();
        $managedDepartments = [];
        $activeDepartment = null;

        if ($user->hasRole('department-manager')) {
            $managedDepartments = Department::where('manager_id', $user->id)->get();
            if ($managedDepartments->isNotEmpty()) {
                // لا نحتاج لفلترة بيانات المدير العام، فقط نرسل الأقسام للفلتر في الواجهة
                $activeDepartment = $managedDepartments->first(); 
            }
        }

        return Inertia::render('Dashboard', [
            'stats' => [
                ['name' => 'الموظفين النشطين', 'value' => $employeeCount, 'icon' => 'fas fa-users'],
                ['name' => 'الأقسام', 'value' => $departmentCount, 'icon' => 'fas fa-building'],
                ['name' => 'طلبات إجازة معلقة', 'value' => $pendingLeavesCount, 'icon' => 'fas fa-clock'],
                ['name' => 'موظفين في إجازة اليوم', 'value' => $onLeaveTodayCount, 'icon' => 'fas fa-calendar-check'],
            ],
            'employeesPerDepartment' => $employeesPerDepartment,
            'attendanceLastWeek' => $attendanceLastWeek,
            'topPerformers' => $topPerformers,
            'lowestPerformers' => $lowestPerformers,
            'penaltyStats' => $penaltyStats,
            'userRole' => 'admin',
            'managedDepartments' => $managedDepartments,
            'activeDepartment' => $activeDepartment,
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

         // --- الإحصائيات الجديدة الخاصة بمدير القسم ---
         $personnelQuery = function ($query) use ($activeDepartment) {
            $query->where('department_id', $activeDepartment->id);
        };

        $recentEvaluationsScope = PerformanceEvaluation::whereHasMorph('evaluable', [Employee::class, Teacher::class], $personnelQuery)
                                    ->with(['evaluable.user'])
                                    ->where('evaluation_date', '>=', now()->subMonths(6));
        
        $topPerformers = (clone $recentEvaluationsScope)->orderByDesc('final_score_percentage')->limit(3)->get();
        $lowestPerformers = (clone $recentEvaluationsScope)->orderBy('final_score_percentage')->limit(3)->get();
        
        $penaltyStats = Penalty::whereHasMorph('penalizable', [Employee::class, Teacher::class], $personnelQuery)
            ->join('penalty_types', 'penalties.penalty_type_id', '=', 'penalty_types.id')
            ->select('penalty_types.name', DB::raw('count(penalties.id) as count'))
            ->groupBy('penalty_types.name')
            ->orderByDesc('count')
            ->limit(3)
            ->get();


        

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
             // --- إرسال البيانات الجديدة ---
             'topPerformers' => $topPerformers,
             'lowestPerformers' => $lowestPerformers,
             'penaltyStats' => $penaltyStats,
        ]);
    }
}

