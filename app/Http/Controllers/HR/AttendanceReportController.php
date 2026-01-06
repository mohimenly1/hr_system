<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Services\AttendanceReportService;
use App\Services\AttendanceComparisonService;
use App\Services\DeductionApplicationService;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Teacher;
use App\Exports\DeductionRulesExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class AttendanceReportController extends Controller
{
    protected $reportService;
    protected $comparisonService;
    protected $deductionService;

    public function __construct(
        AttendanceReportService $reportService,
        AttendanceComparisonService $comparisonService,
        DeductionApplicationService $deductionService
    ) {
        $this->reportService = $reportService;
        $this->comparisonService = $comparisonService;
        $this->deductionService = $deductionService;
    }

    /**
     * Display the main attendance reports dashboard
     */
    public function index(Request $request)
    {
        $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));

        $overallStats = $this->reportService->getOverallStatistics($startDate, $endDate);
        $departmentsStats = $this->reportService->getAllDepartmentsStatistics($startDate, $endDate);
        $dailyTrend = $this->reportService->getDailyAttendanceTrend($startDate, $endDate);

        return Inertia::render('HR/AttendanceReports/Index', [
            'overallStatistics' => $overallStats,
            'departmentsStatistics' => $departmentsStats,
            'dailyTrend' => $dailyTrend,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    /**
     * Display department-specific report
     */
    public function showDepartment(Department $department, Request $request)
    {
        $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));

        $departmentStats = $this->reportService->getDepartmentStatistics($department, $startDate, $endDate);

        // Get employees and teachers in this department with their statistics
        $employees = $department->employees()->with(['user', 'attendances', 'shiftAssignment.shift'])->get();
        $teachers = $department->teachers()->with(['user', 'attendances', 'shiftAssignment.shift'])->get();

        $startCarbon = Carbon::parse($startDate);
        $endCarbon = Carbon::parse($endDate);

        return Inertia::render('HR/AttendanceReports/Department', [
            'department' => $departmentStats,
            'employees' => $employees->map(function ($employee) use ($startCarbon, $endCarbon) {
                return $this->getPersonnelStatistics($employee, 'App\Models\Employee', $startCarbon, $endCarbon);
            }),
            'teachers' => $teachers->map(function ($teacher) use ($startCarbon, $endCarbon) {
                return $this->getPersonnelStatistics($teacher, 'App\Models\Teacher', $startCarbon, $endCarbon);
            }),
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    /**
     * Get statistics for a single personnel (employee or teacher)
     */
    private function getPersonnelStatistics($person, string $personType, Carbon $startDate, Carbon $endDate): array
    {
        // Get attendances in the period
        $attendances = \App\Models\Attendance::where('attendable_id', $person->id)
            ->where('attendable_type', $personType)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->get();

        // Get timetable entries
        $timetableEntries = \App\Models\TimetableEntry::where('schedulable_id', $person->id)
            ->where('schedulable_type', $personType)
            ->where('is_break', false)
            ->get()
            ->groupBy('day_of_week');

        // Calculate statistics
        $present = $attendances->where('status', 'present')->count();
        $absent = $attendances->where('status', 'absent')->count();
        $late = $attendances->where('status', 'late')->count();
        $onLeave = $attendances->where('status', 'on_leave')->count();

        // Calculate working days
        $workingDays = 0;
        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            $dayOfWeek = $current->dayOfWeek;
            // Convert Carbon dayOfWeek (0=Sunday) to our system (1=Saturday)
            $convertedDay = $dayOfWeek === 0 ? 6 : ($dayOfWeek === 6 ? 0 : $dayOfWeek);

            if ($dayOfWeek != Carbon::FRIDAY && $dayOfWeek != Carbon::SATURDAY) {
                if ($timetableEntries->has($convertedDay)) {
                    $workingDays++;
                }
            }
            $current->addDay();
        }

        $attendanceRate = $workingDays > 0 ? round(($present / $workingDays) * 100, 2) : 0;

        // Get shift info
        $shiftInfo = null;
        if ($person->shiftAssignment && $person->shiftAssignment->shift) {
            $shiftInfo = [
                'name' => $person->shiftAssignment->shift->name,
                'start_time' => $person->shiftAssignment->shift->start_time,
                'end_time' => $person->shiftAssignment->shift->end_time,
            ];
        }

        // Get timetable summary
        $timetableSummary = [];
        foreach ($timetableEntries as $day => $entries) {
            $dayNames = ['السبت', 'الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'];
            $timetableSummary[] = [
                'day' => $dayNames[$day - 1] ?? "يوم $day",
                'entries_count' => $entries->count(),
                'total_hours' => round($entries->sum('work_minutes') / 60, 2),
            ];
        }

        return [
            'id' => $person->id,
            'name' => $person->user->full_name ?? $person->user->name,
            'type' => $personType === 'App\Models\Employee' ? 'employee' : 'teacher',
            'job_title' => $person->job_title ?? $person->specialization ?? null,
            'shift' => $shiftInfo,
            'timetable_summary' => $timetableSummary,
            'statistics' => [
                'present' => $present,
                'absent' => $absent,
                'late' => $late,
                'on_leave' => $onLeave,
                'working_days' => $workingDays,
                'attendance_rate' => $attendanceRate,
            ],
        ];
    }

    /**
     * Show detailed comparison for a specific personnel
     */
    public function showPersonnelDetails(Request $request, $personType, $personId)
    {
        $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));

        $startCarbon = Carbon::parse($startDate);
        $endCarbon = Carbon::parse($endDate);

        // Determine model class
        $modelClass = $personType === 'employee' ? 'App\Models\Employee' : 'App\Models\Teacher';

        $person = $modelClass::with(['user', 'department', 'shiftAssignment.shift'])->find($personId);

        if (!$person) {
            return redirect()->back()->with('error', 'لم يتم العثور على الموظف/المعلم المحدد.');
        }

        // Get comparison data
        $comparison = $this->comparisonService->compareAttendanceWithTimetable(
            $personId,
            $modelClass,
            $startCarbon,
            $endCarbon
        );

        // Apply deduction rules
        $deductions = $this->deductionService->applyDeductionRules(
            $comparison,
            $personId,
            $modelClass,
            $startCarbon,
            $endCarbon
        );

        return Inertia::render('HR/AttendanceReports/PersonnelDetails', [
            'person' => [
                'id' => $person->id,
                'name' => $person->user->full_name ?? $person->user->name,
                'type' => $personType,
                'type_label' => $personType === 'employee' ? 'موظف' : 'معلم',
                'job_title' => $person->job_title ?? $person->specialization ?? null,
                'department' => $person->department ? $person->department->name : null,
            ],
            'comparison' => $comparison,
            'deductions' => $deductions,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    /**
     * Export deduction rules report to Excel
     */
    public function exportDeductionRules(Request $request, $personType, $personId)
    {
        $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));

        $startCarbon = Carbon::parse($startDate);
        $endCarbon = Carbon::parse($endDate);

        // Determine model class
        $modelClass = $personType === 'employee' ? 'App\Models\Employee' : 'App\Models\Teacher';

        $person = $modelClass::with(['user', 'department'])->find($personId);

        if (!$person) {
            return redirect()->back()->with('error', 'لم يتم العثور على الموظف/المعلم المحدد.');
        }

        // Get comparison data
        $comparison = $this->comparisonService->compareAttendanceWithTimetable(
            $personId,
            $modelClass,
            $startCarbon,
            $endCarbon
        );

        // Apply deduction rules
        $deductions = $this->deductionService->applyDeductionRules(
            $comparison,
            $personId,
            $modelClass,
            $startCarbon,
            $endCarbon
        );

        // Prepare person data
        $personData = [
            'name' => $person->user->full_name ?? $person->user->name,
            'type_label' => $personType === 'employee' ? 'موظف' : 'معلم',
            'job_title' => $person->job_title ?? $person->specialization ?? null,
            'department' => $person->department ? $person->department->name : null,
        ];

        // Generate file name
        $personName = str_replace(' ', '_', $personData['name']);
        $fileName = 'الخصومات_المطبقة_' . $personName . '_' . $startDate . '_' . $endDate . '.xlsx';

        return Excel::download(
            new DeductionRulesExport($personData, $deductions, $startDate, $endDate),
            $fileName
        );
    }
}






