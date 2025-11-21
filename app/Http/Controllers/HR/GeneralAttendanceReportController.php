<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\GeneralAttendanceExport;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class GeneralAttendanceReportController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display the general attendance report page
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $type = $request->input('type', 'employees'); // 'employees' or 'teachers'
        $filterType = $request->input('filter_type', 'month'); // 'month' or 'date_range'
        $selectedMonth = $request->input('month', now()->format('Y-m'));
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Get available months based on type
        if ($type === 'employees') {
            $availableMonths = $this->getAvailableMonths(Employee::class);
            if ($filterType === 'date_range' && $startDate && $endDate) {
                $items = $this->getEmployeesDataByDateRange($startDate, $endDate);
            } else {
                $items = $this->getEmployeesData($selectedMonth);
            }
        } else {
            $availableMonths = $this->getAvailableMonths(Teacher::class);
            if ($filterType === 'date_range' && $startDate && $endDate) {
                $items = $this->getTeachersDataByDateRange($startDate, $endDate);
            } else {
                $items = $this->getTeachersData($selectedMonth);
            }
        }

        return Inertia::render('HR/GeneralAttendanceReport/Index', [
            'type' => $type,
            'filterType' => $filterType,
            'selectedMonth' => $selectedMonth,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'availableMonths' => $availableMonths,
            'items' => $items,
            'filters' => $request->only('type', 'filter_type', 'month', 'start_date', 'end_date'),
        ]);
    }

    /**
     * Export general attendance report
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $validated = $request->validate([
            'type' => 'required|in:employees,teachers',
            'filter_type' => 'required|in:month,date_range',
            'month' => 'required_if:filter_type,month|date_format:Y-m',
            'start_date' => 'required_if:filter_type,date_range|date',
            'end_date' => 'required_if:filter_type,date_range|date|after_or_equal:start_date',
        ]);

        $type = $validated['type'];
        $filterType = $validated['filter_type'];

        if ($filterType === 'date_range') {
            $startDate = $validated['start_date'];
            $endDate = $validated['end_date'];

            if ($type === 'employees') {
                $items = $this->getEmployeesDataByDateRange($startDate, $endDate, true);
                $typeLabel = 'الموظفين';
            } else {
                $items = $this->getTeachersDataByDateRange($startDate, $endDate, true);
                $typeLabel = 'المعلمين';
            }

            $periodName = \Carbon\Carbon::parse($startDate)->locale('ar')->translatedFormat('d F Y') .
                         ' - ' .
                         \Carbon\Carbon::parse($endDate)->locale('ar')->translatedFormat('d F Y');
            $fileName = 'تقرير_الحضور_العام_' . $typeLabel . '_' . $startDate . '_' . $endDate . '.xlsx';
        } else {
            $selectedMonth = $validated['month'];
            [$selectedYear, $selectedMonthNum] = explode('-', $selectedMonth);

            if ($type === 'employees') {
                $items = $this->getEmployeesData($selectedMonth, true);
                $typeLabel = 'الموظفين';
            } else {
                $items = $this->getTeachersData($selectedMonth, true);
                $typeLabel = 'المعلمين';
            }

            $periodName = \Carbon\Carbon::create($selectedYear, $selectedMonthNum, 1)
                ->locale('ar')
                ->translatedFormat('F Y');
            $fileName = 'تقرير_الحضور_العام_' . $typeLabel . '_' . $periodName . '.xlsx';
        }

        return Excel::download(
            new GeneralAttendanceExport($items, $type, $periodName),
            $fileName
        );
    }

    /**
     * Get available months from attendance records
     */
    private function getAvailableMonths($modelClass)
    {
        $attendances = \App\Models\Attendance::where('attendable_type', $modelClass)
            ->selectRaw('YEAR(attendance_date) as year, MONTH(attendance_date) as month')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get()
            ->map(function ($item) {
                $date = \Carbon\Carbon::create($item->year, $item->month, 1);
                return [
                    'value' => $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT),
                    'label' => $date->locale('ar')->translatedFormat('F Y'),
                ];
            });

        return $attendances;
    }

    /**
     * Get employees data with attendance statistics
     */
    private function getEmployeesData($selectedMonth, $forExport = false)
    {
        [$selectedYear, $selectedMonthNum] = explode('-', $selectedMonth);

        $employees = Employee::with(['user', 'department'])->get();

        $startDate = \Carbon\Carbon::create($selectedYear, $selectedMonthNum, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $allDaysInMonth = collect();
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $allDaysInMonth->push($currentDate->copy());
            $currentDate->addDay();
        }

        $fridays = $allDaysInMonth->filter(fn($date) => $date->dayOfWeek === \Carbon\Carbon::FRIDAY)->count();
        $saturdays = $allDaysInMonth->filter(fn($date) => $date->dayOfWeek === \Carbon\Carbon::SATURDAY)->count();
        $weekendDays = $fridays + $saturdays;
        $workingDays = $allDaysInMonth->count() - $weekendDays;

        return $employees->map(function ($employee) use ($selectedYear, $selectedMonthNum, $allDaysInMonth, $workingDays, $weekendDays, $forExport) {
            $attendanceRecords = $employee->attendances()
                ->whereYear('attendance_date', $selectedYear)
                ->whereMonth('attendance_date', $selectedMonthNum)
                ->get()
                ->keyBy(function ($record) {
                    return $record->attendance_date->format('Y-m-d');
                });

            $actualPresentDays = $allDaysInMonth
                ->filter(function ($date) use ($attendanceRecords) {
                    $dateStr = $date->format('Y-m-d');
                    $isWeekend = in_array($date->dayOfWeek, [\Carbon\Carbon::FRIDAY, \Carbon\Carbon::SATURDAY]);
                    if ($isWeekend) return false;
                    $record = $attendanceRecords->get($dateStr);
                    return $record && $record->status === 'present';
                })
                ->count();

            $actualAbsentDays = $allDaysInMonth
                ->filter(function ($date) use ($attendanceRecords) {
                    $dateStr = $date->format('Y-m-d');
                    $isWeekend = in_array($date->dayOfWeek, [\Carbon\Carbon::FRIDAY, \Carbon\Carbon::SATURDAY]);
                    if ($isWeekend) return false;
                    return !$attendanceRecords->has($dateStr);
                })
                ->count();

            $lateDays = $attendanceRecords->where('status', 'late')->count();
            $leaveDays = $attendanceRecords->where('status', 'on_leave')->count();

            $attendanceRate = $workingDays > 0 ? round(($actualPresentDays / $workingDays) * 100, 2) : 0;

            // Get daily attendance data for export
            $dailyData = [];
            if ($forExport) {
                foreach ($allDaysInMonth as $date) {
                    $dateStr = $date->format('Y-m-d');
                    $record = $attendanceRecords->get($dateStr);
                    $isWeekend = in_array($date->dayOfWeek, [\Carbon\Carbon::FRIDAY, \Carbon\Carbon::SATURDAY]);

                    if ($isWeekend) {
                        $dailyData[$dateStr] = 'weekend';
                    } elseif ($record) {
                        $dailyData[$dateStr] = $record->status; // present, absent, late, on_leave, holiday
                    } else {
                        $dailyData[$dateStr] = 'absent';
                    }
                }
            }

            return [
                'id' => $employee->id,
                'name' => $employee->user->full_name ?? $employee->user->name,
                'employee_id' => $employee->employee_id,
                'department' => $employee->department->name ?? 'غير محدد',
                'job_title' => $employee->job_title ?? 'غير محدد',
                'statistics' => [
                    'working_days' => $workingDays,
                    'weekend_days' => $weekendDays,
                    'actual_present_days' => $actualPresentDays,
                    'actual_absent_days' => $actualAbsentDays,
                    'late_days' => $lateDays,
                    'leave_days' => $leaveDays,
                    'attendance_rate' => $attendanceRate,
                ],
                'daily_data' => $dailyData,
                'all_days' => $allDaysInMonth->map(fn($d) => $d->format('Y-m-d'))->toArray(),
            ];
        })->values();
    }

    /**
     * Get teachers data with attendance statistics
     */
    private function getTeachersData($selectedMonth, $forExport = false)
    {
        [$selectedYear, $selectedMonthNum] = explode('-', $selectedMonth);

        $teachers = Teacher::with(['user', 'department'])->get();

        $startDate = \Carbon\Carbon::create($selectedYear, $selectedMonthNum, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $allDaysInMonth = collect();
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $allDaysInMonth->push($currentDate->copy());
            $currentDate->addDay();
        }

        $fridays = $allDaysInMonth->filter(fn($date) => $date->dayOfWeek === \Carbon\Carbon::FRIDAY)->count();
        $saturdays = $allDaysInMonth->filter(fn($date) => $date->dayOfWeek === \Carbon\Carbon::SATURDAY)->count();
        $weekendDays = $fridays + $saturdays;
        $workingDays = $allDaysInMonth->count() - $weekendDays;

        return $teachers->map(function ($teacher) use ($selectedYear, $selectedMonthNum, $allDaysInMonth, $workingDays, $weekendDays, $forExport) {
            $attendanceRecords = $teacher->attendances()
                ->whereYear('attendance_date', $selectedYear)
                ->whereMonth('attendance_date', $selectedMonthNum)
                ->get()
                ->keyBy(function ($record) {
                    return $record->attendance_date->format('Y-m-d');
                });

            $actualPresentDays = $allDaysInMonth
                ->filter(function ($date) use ($attendanceRecords) {
                    $dateStr = $date->format('Y-m-d');
                    $isWeekend = in_array($date->dayOfWeek, [\Carbon\Carbon::FRIDAY, \Carbon\Carbon::SATURDAY]);
                    if ($isWeekend) return false;
                    $record = $attendanceRecords->get($dateStr);
                    return $record && $record->status === 'present';
                })
                ->count();

            $actualAbsentDays = $allDaysInMonth
                ->filter(function ($date) use ($attendanceRecords) {
                    $dateStr = $date->format('Y-m-d');
                    $isWeekend = in_array($date->dayOfWeek, [\Carbon\Carbon::FRIDAY, \Carbon\Carbon::SATURDAY]);
                    if ($isWeekend) return false;
                    return !$attendanceRecords->has($dateStr);
                })
                ->count();

            $lateDays = $attendanceRecords->where('status', 'late')->count();
            $leaveDays = $attendanceRecords->where('status', 'on_leave')->count();

            $attendanceRate = $workingDays > 0 ? round(($actualPresentDays / $workingDays) * 100, 2) : 0;

            // Get daily attendance data for export
            $dailyData = [];
            if ($forExport) {
                foreach ($allDaysInMonth as $date) {
                    $dateStr = $date->format('Y-m-d');
                    $record = $attendanceRecords->get($dateStr);
                    $isWeekend = in_array($date->dayOfWeek, [\Carbon\Carbon::FRIDAY, \Carbon\Carbon::SATURDAY]);

                    if ($isWeekend) {
                        $dailyData[$dateStr] = 'weekend';
                    } elseif ($record) {
                        $dailyData[$dateStr] = $record->status; // present, absent, late, on_leave, holiday
                    } else {
                        $dailyData[$dateStr] = 'absent';
                    }
                }
            }

            return [
                'id' => $teacher->id,
                'name' => $teacher->user->full_name ?? $teacher->user->name,
                'specialization' => $teacher->specialization ?? 'غير محدد',
                'department' => $teacher->department->name ?? 'غير محدد',
                'statistics' => [
                    'working_days' => $workingDays,
                    'weekend_days' => $weekendDays,
                    'actual_present_days' => $actualPresentDays,
                    'actual_absent_days' => $actualAbsentDays,
                    'late_days' => $lateDays,
                    'leave_days' => $leaveDays,
                    'attendance_rate' => $attendanceRate,
                ],
                'daily_data' => $dailyData,
                'all_days' => $allDaysInMonth->map(fn($d) => $d->format('Y-m-d'))->toArray(),
            ];
        })->values();
    }

    /**
     * Get employees data by date range
     */
    private function getEmployeesDataByDateRange($startDate, $endDate, $forExport = false)
    {
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);

        $employees = Employee::with(['user', 'department'])->get();

        $allDaysInRange = collect();
        $currentDate = $start->copy();
        while ($currentDate->lte($end)) {
            $allDaysInRange->push($currentDate->copy());
            $currentDate->addDay();
        }

        $fridays = $allDaysInRange->filter(fn($date) => $date->dayOfWeek === \Carbon\Carbon::FRIDAY)->count();
        $saturdays = $allDaysInRange->filter(fn($date) => $date->dayOfWeek === \Carbon\Carbon::SATURDAY)->count();
        $weekendDays = $fridays + $saturdays;
        $workingDays = $allDaysInRange->count() - $weekendDays;

        return $employees->map(function ($employee) use ($start, $end, $allDaysInRange, $workingDays, $weekendDays, $forExport) {
            $attendanceRecords = $employee->attendances()
                ->whereBetween('attendance_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->get()
                ->keyBy(function ($record) {
                    return $record->attendance_date->format('Y-m-d');
                });

            $actualPresentDays = $allDaysInRange
                ->filter(function ($date) use ($attendanceRecords) {
                    $dateStr = $date->format('Y-m-d');
                    $isWeekend = in_array($date->dayOfWeek, [\Carbon\Carbon::FRIDAY, \Carbon\Carbon::SATURDAY]);
                    if ($isWeekend) return false;
                    $record = $attendanceRecords->get($dateStr);
                    return $record && $record->status === 'present';
                })
                ->count();

            $actualAbsentDays = $allDaysInRange
                ->filter(function ($date) use ($attendanceRecords) {
                    $dateStr = $date->format('Y-m-d');
                    $isWeekend = in_array($date->dayOfWeek, [\Carbon\Carbon::FRIDAY, \Carbon\Carbon::SATURDAY]);
                    if ($isWeekend) return false;
                    return !$attendanceRecords->has($dateStr);
                })
                ->count();

            $lateDays = $attendanceRecords->where('status', 'late')->count();
            $leaveDays = $attendanceRecords->where('status', 'on_leave')->count();

            $attendanceRate = $workingDays > 0 ? round(($actualPresentDays / $workingDays) * 100, 2) : 0;

            // Get daily attendance data for export
            $dailyData = [];
            if ($forExport) {
                foreach ($allDaysInRange as $date) {
                    $dateStr = $date->format('Y-m-d');
                    $record = $attendanceRecords->get($dateStr);
                    $isWeekend = in_array($date->dayOfWeek, [\Carbon\Carbon::FRIDAY, \Carbon\Carbon::SATURDAY]);

                    if ($isWeekend) {
                        $dailyData[$dateStr] = 'weekend';
                    } elseif ($record) {
                        $dailyData[$dateStr] = $record->status;
                    } else {
                        $dailyData[$dateStr] = 'absent';
                    }
                }
            }

            return [
                'id' => $employee->id,
                'name' => $employee->user->full_name ?? $employee->user->name,
                'employee_id' => $employee->employee_id,
                'department' => $employee->department->name ?? 'غير محدد',
                'job_title' => $employee->job_title ?? 'غير محدد',
                'statistics' => [
                    'working_days' => $workingDays,
                    'weekend_days' => $weekendDays,
                    'actual_present_days' => $actualPresentDays,
                    'actual_absent_days' => $actualAbsentDays,
                    'late_days' => $lateDays,
                    'leave_days' => $leaveDays,
                    'attendance_rate' => $attendanceRate,
                ],
                'daily_data' => $dailyData,
                'all_days' => $allDaysInRange->map(fn($d) => $d->format('Y-m-d'))->toArray(),
            ];
        })->values();
    }

    /**
     * Get teachers data by date range
     */
    private function getTeachersDataByDateRange($startDate, $endDate, $forExport = false)
    {
        $start = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);

        $teachers = Teacher::with(['user', 'department'])->get();

        $allDaysInRange = collect();
        $currentDate = $start->copy();
        while ($currentDate->lte($end)) {
            $allDaysInRange->push($currentDate->copy());
            $currentDate->addDay();
        }

        $fridays = $allDaysInRange->filter(fn($date) => $date->dayOfWeek === \Carbon\Carbon::FRIDAY)->count();
        $saturdays = $allDaysInRange->filter(fn($date) => $date->dayOfWeek === \Carbon\Carbon::SATURDAY)->count();
        $weekendDays = $fridays + $saturdays;
        $workingDays = $allDaysInRange->count() - $weekendDays;

        return $teachers->map(function ($teacher) use ($start, $end, $allDaysInRange, $workingDays, $weekendDays, $forExport) {
            $attendanceRecords = $teacher->attendances()
                ->whereBetween('attendance_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->get()
                ->keyBy(function ($record) {
                    return $record->attendance_date->format('Y-m-d');
                });

            $actualPresentDays = $allDaysInRange
                ->filter(function ($date) use ($attendanceRecords) {
                    $dateStr = $date->format('Y-m-d');
                    $isWeekend = in_array($date->dayOfWeek, [\Carbon\Carbon::FRIDAY, \Carbon\Carbon::SATURDAY]);
                    if ($isWeekend) return false;
                    $record = $attendanceRecords->get($dateStr);
                    return $record && $record->status === 'present';
                })
                ->count();

            $actualAbsentDays = $allDaysInRange
                ->filter(function ($date) use ($attendanceRecords) {
                    $dateStr = $date->format('Y-m-d');
                    $isWeekend = in_array($date->dayOfWeek, [\Carbon\Carbon::FRIDAY, \Carbon\Carbon::SATURDAY]);
                    if ($isWeekend) return false;
                    return !$attendanceRecords->has($dateStr);
                })
                ->count();

            $lateDays = $attendanceRecords->where('status', 'late')->count();
            $leaveDays = $attendanceRecords->where('status', 'on_leave')->count();

            $attendanceRate = $workingDays > 0 ? round(($actualPresentDays / $workingDays) * 100, 2) : 0;

            // Get daily attendance data for export
            $dailyData = [];
            if ($forExport) {
                foreach ($allDaysInRange as $date) {
                    $dateStr = $date->format('Y-m-d');
                    $record = $attendanceRecords->get($dateStr);
                    $isWeekend = in_array($date->dayOfWeek, [\Carbon\Carbon::FRIDAY, \Carbon\Carbon::SATURDAY]);

                    if ($isWeekend) {
                        $dailyData[$dateStr] = 'weekend';
                    } elseif ($record) {
                        $dailyData[$dateStr] = $record->status;
                    } else {
                        $dailyData[$dateStr] = 'absent';
                    }
                }
            }

            return [
                'id' => $teacher->id,
                'name' => $teacher->user->full_name ?? $teacher->user->name,
                'specialization' => $teacher->specialization ?? 'غير محدد',
                'department' => $teacher->department->name ?? 'غير محدد',
                'statistics' => [
                    'working_days' => $workingDays,
                    'weekend_days' => $weekendDays,
                    'actual_present_days' => $actualPresentDays,
                    'actual_absent_days' => $actualAbsentDays,
                    'late_days' => $lateDays,
                    'leave_days' => $leaveDays,
                    'attendance_rate' => $attendanceRate,
                ],
                'daily_data' => $dailyData,
                'all_days' => $allDaysInRange->map(fn($d) => $d->format('Y-m-d'))->toArray(),
            ];
        })->values();
    }
}
