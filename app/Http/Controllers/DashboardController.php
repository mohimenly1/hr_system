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
use App\Models\Document;
use App\Models\DocumentWorkflow;



class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();

        if ($user->hasAnyRole(['admin', 'hr-manager'])) {
            return $this->adminDashboard($request);
        }

        if ($user->hasRole('department-manager')) {
            return $this->departmentManagerDashboard($user, $request);
        }

        return Inertia::render('Dashboard', ['stats' => [], 'userRole' => 'other']);
    }

    private function adminDashboard(Request $request): Response
    {
        // --- الإحصائيات العامة ---
        $employeeCount = Employee::where('employment_status', 'active')->count();
        $departmentCount = Department::count();
        
        // --- إحصائيات المراسلات الجديدة ---
        $outgoingCount = Document::where('type', 'outgoing')->count();
        $pendingTasksCount = DocumentWorkflow::whereNull('completed_at')->count();

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
                $activeDepartmentId = $request->input('department_id', $managedDepartments->first()->id);
                $activeDepartment = $managedDepartments->firstWhere('id', $activeDepartmentId) ?? $managedDepartments->first();
            }
        }

        return Inertia::render('Dashboard', [
            'stats' => [
                ['name' => 'الموظفين النشطين', 'value' => $employeeCount, 'icon' => 'fas fa-users', 'route' => route('hr.employees.index')],
                ['name' => 'الأقسام', 'value' => $departmentCount, 'icon' => 'fas fa-building', 'route' => route('hr.departments.index')],
                ['name' => 'إجمالي الصادر', 'value' => $outgoingCount, 'icon' => 'fas fa-file-export', 'route' => route('documents.index', ['tab' => 'outgoing'])],
                ['name' => 'المهام المعلقة', 'value' => $pendingTasksCount, 'icon' => 'fas fa-inbox', 'route' => route('documents.tasks')],
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
            return Inertia::render('Dashboard', ['stats' => [], 'managedDepartments' => [], 'activeDepartment' => null, 'userRole' => 'department-manager']);
        }
        
        $activeDepartmentId = $request->input('department_id', $managedDepartments->first()->id);
        $activeDepartment = $managedDepartments->firstWhere('id', $activeDepartmentId) ?? $managedDepartments->first();
        $activeDeptIdArray = [$activeDepartment->id];

        $personnelCount = Employee::whereIn('department_id', $activeDeptIdArray)->count() + Teacher::whereIn('department_id', $activeDeptIdArray)->count();
        
        // --- إحصائيات المراسلات الجديدة لمدير القسم ---
        $outgoingCount = Document::whereIn('department_id', $activeDeptIdArray)->where('type', 'outgoing')->count();
        $pendingTasksCount = DocumentWorkflow::whereIn('to_department_id', $activeDeptIdArray)->whereNull('completed_at')->count();

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
        
        $personnelQuery = function ($query) use ($activeDepartment) { $query->where('department_id', $activeDepartment->id); };
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
                ['name' => 'أعضاء القسم', 'value' => $personnelCount, 'icon' => 'fas fa-users', 'route' => '#'],
                ['name' => 'الصادر من القسم', 'value' => $outgoingCount, 'icon' => 'fas fa-file-export', 'route' => route('documents.index', ['tab' => 'outgoing'])],
                ['name' => 'المهام الواردة للقسم', 'value' => $pendingTasksCount, 'icon' => 'fas fa-inbox', 'route' => route('documents.tasks')],
            ],
            'attendanceLastWeek' => $attendanceLastWeek,
            'managedDepartments' => $managedDepartments,
            'activeDepartment' => $activeDepartment,
            'topPerformers' => $topPerformers,
            'lowestPerformers' => $lowestPerformers,
            'penaltyStats' => $penaltyStats,
            'userRole' => 'department-manager',
        ]);
    }
}

