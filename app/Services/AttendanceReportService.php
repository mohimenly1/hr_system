<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Teacher;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceReportService
{
    /**
     * Get overall statistics for all departments
     */
    public function getOverallStatistics($startDate = null, $endDate = null): array
    {
        $startDate = $startDate ? Carbon::parse($startDate) : Carbon::now()->startOfMonth();
        $endDate = $endDate ? Carbon::parse($endDate) : Carbon::now()->endOfMonth();

        $departments = Department::with(['employees', 'teachers'])->get();

        $totalEmployees = Employee::count();
        $totalTeachers = Teacher::count();
        $totalPersonnel = $totalEmployees + $totalTeachers;

        // Calculate overall attendance statistics
        $totalDays = $startDate->diffInDays($endDate) + 1;
        $workingDays = $this->calculateWorkingDays($startDate, $endDate);

        // Get all attendances in the period
        $attendances = Attendance::whereBetween('attendance_date', [$startDate, $endDate])
            ->get()
            ->groupBy('attendable_type');

        $employeeAttendances = $attendances->get('App\Models\Employee', collect());
        $teacherAttendances = $attendances->get('App\Models\Teacher', collect());

        $totalPresent = $employeeAttendances->where('status', 'present')->count()
                      + $teacherAttendances->where('status', 'present')->count();

        $totalAbsent = $employeeAttendances->where('status', 'absent')->count()
                     + $teacherAttendances->where('status', 'absent')->count();

        $totalLate = $employeeAttendances->where('status', 'late')->count()
                   + $teacherAttendances->where('status', 'late')->count();

        $totalOnLeave = $employeeAttendances->where('status', 'on_leave')->count()
                      + $teacherAttendances->where('status', 'on_leave')->count();

        $expectedAttendanceRecords = $totalPersonnel * $workingDays;
        $actualAttendanceRecords = $totalPresent + $totalAbsent + $totalLate + $totalOnLeave;
        $attendanceRate = $expectedAttendanceRecords > 0
            ? round(($actualAttendanceRecords / $expectedAttendanceRecords) * 100, 2)
            : 0;

        return [
            'total_departments' => $departments->count(),
            'total_employees' => $totalEmployees,
            'total_teachers' => $totalTeachers,
            'total_personnel' => $totalPersonnel,
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'total_days' => $totalDays,
                'working_days' => $workingDays,
            ],
            'statistics' => [
                'total_present' => $totalPresent,
                'total_absent' => $totalAbsent,
                'total_late' => $totalLate,
                'total_on_leave' => $totalOnLeave,
                'attendance_rate' => $attendanceRate,
            ],
        ];
    }

    /**
     * Get statistics for a specific department
     */
    public function getDepartmentStatistics(Department $department, $startDate = null, $endDate = null): array
    {
        $startDate = $startDate ? Carbon::parse($startDate) : Carbon::now()->startOfMonth();
        $endDate = $endDate ? Carbon::parse($endDate) : Carbon::now()->endOfMonth();

        $employees = $department->employees()->with('user')->get();
        $teachers = $department->teachers()->with('user')->get();
        $totalPersonnel = $employees->count() + $teachers->count();

        $workingDays = $this->calculateWorkingDays($startDate, $endDate);

        // Get attendances for employees in this department
        $employeeIds = $employees->pluck('id');
        $teacherIds = $teachers->pluck('id');

        $employeeAttendances = Attendance::where('attendable_type', 'App\Models\Employee')
            ->whereIn('attendable_id', $employeeIds)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->get();

        $teacherAttendances = Attendance::where('attendable_type', 'App\Models\Teacher')
            ->whereIn('attendable_id', $teacherIds)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->get();

        $allAttendances = $employeeAttendances->concat($teacherAttendances);

        $present = $allAttendances->where('status', 'present')->count();
        $absent = $allAttendances->where('status', 'absent')->count();
        $late = $allAttendances->where('status', 'late')->count();
        $onLeave = $allAttendances->where('status', 'on_leave')->count();

        $expectedRecords = $totalPersonnel * $workingDays;
        $actualRecords = $present + $absent + $late + $onLeave;
        $attendanceRate = $expectedRecords > 0
            ? round(($actualRecords / $expectedRecords) * 100, 2)
            : 0;

        return [
            'department' => [
                'id' => $department->id,
                'name' => $department->name,
                'description' => $department->description,
            ],
            'personnel' => [
                'total' => $totalPersonnel,
                'employees' => $employees->count(),
                'teachers' => $teachers->count(),
            ],
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'working_days' => $workingDays,
            ],
            'statistics' => [
                'present' => $present,
                'absent' => $absent,
                'late' => $late,
                'on_leave' => $onLeave,
                'attendance_rate' => $attendanceRate,
            ],
            'chart_data' => [
                'labels' => ['حضور', 'غياب', 'تأخير', 'إجازة'],
                'data' => [$present, $absent, $late, $onLeave],
                'colors' => ['#10b981', '#ef4444', '#f59e0b', '#3b82f6'],
            ],
        ];
    }

    /**
     * Get statistics for all departments (for dashboard)
     */
    public function getAllDepartmentsStatistics($startDate = null, $endDate = null): Collection
    {
        $startDate = $startDate ? Carbon::parse($startDate) : Carbon::now()->startOfMonth();
        $endDate = $endDate ? Carbon::parse($endDate) : Carbon::now()->endOfMonth();

        $departments = Department::with(['employees', 'teachers'])->get();

        return $departments->map(function ($department) use ($startDate, $endDate) {
            return $this->getDepartmentStatistics($department, $startDate, $endDate);
        });
    }

    /**
     * Calculate working days (excluding weekends)
     */
    private function calculateWorkingDays(Carbon $startDate, Carbon $endDate): int
    {
        $workingDays = 0;
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            // Exclude Friday (5) and Saturday (6) - Carbon uses 0-6 where 0 is Sunday
            $dayOfWeek = $current->dayOfWeek;
            // Convert: Carbon Sunday=0, Monday=1... Saturday=6
            // We want to exclude Friday (5) and Saturday (6)
            if ($dayOfWeek != Carbon::FRIDAY && $dayOfWeek != Carbon::SATURDAY) {
                $workingDays++;
            }
            $current->addDay();
        }

        return $workingDays;
    }

    /**
     * Get daily attendance trend for chart
     */
    public function getDailyAttendanceTrend($startDate = null, $endDate = null): array
    {
        $startDate = $startDate ? Carbon::parse($startDate) : Carbon::now()->startOfMonth();
        $endDate = $endDate ? Carbon::parse($endDate) : Carbon::now()->endOfMonth();

        $attendances = Attendance::whereBetween('attendance_date', [$startDate, $endDate])
            ->get()
            ->groupBy('attendance_date');

        $labels = [];
        $presentData = [];
        $absentData = [];
        $lateData = [];

        $current = $startDate->copy();
        while ($current->lte($endDate)) {
            $dateStr = $current->format('Y-m-d');
            $labels[] = $current->format('Y-m-d');

            $dayAttendances = $attendances->get($dateStr, collect());
            $presentData[] = $dayAttendances->where('status', 'present')->count();
            $absentData[] = $dayAttendances->where('status', 'absent')->count();
            $lateData[] = $dayAttendances->where('status', 'late')->count();

            $current->addDay();
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'حضور',
                    'data' => $presentData,
                    'backgroundColor' => '#10b981',
                ],
                [
                    'label' => 'غياب',
                    'data' => $absentData,
                    'backgroundColor' => '#ef4444',
                ],
                [
                    'label' => 'تأخير',
                    'data' => $lateData,
                    'backgroundColor' => '#f59e0b',
                ],
            ],
        ];
    }
}






