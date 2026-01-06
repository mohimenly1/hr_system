<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\TimetableEntry;
use App\Models\ShiftAssignment;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceComparisonService
{
    /**
     * Compare attendance records with timetable for a specific person
     */
    public function compareAttendanceWithTimetable($personId, string $personType, Carbon $startDate, Carbon $endDate): array
    {
        // Get all attendances in the period
        $attendances = Attendance::where('attendable_id', $personId)
            ->where('attendable_type', $personType)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->orderBy('attendance_date')
            ->get()
            ->keyBy(function ($attendance) {
                return $attendance->attendance_date->format('Y-m-d');
            });

        // Get timetable entries grouped by day of week
        $timetableEntries = TimetableEntry::where('schedulable_id', $personId)
            ->where('schedulable_type', $personType)
            ->where('is_break', false)
            ->get()
            ->groupBy('day_of_week');

        // Get shift assignment
        $shiftAssignment = ShiftAssignment::where('shiftable_id', $personId)
            ->where('shiftable_type', $personType)
            ->with('shift')
            ->first();

        // Get person info to determine department
        $person = null;
        $personDepartment = null;
        if ($personType === 'App\Models\Employee') {
            $person = \App\Models\Employee::with('department')->find($personId);
            $personDepartment = $person?->department;
        } elseif ($personType === 'App\Models\Teacher') {
            $person = \App\Models\Teacher::with('department')->find($personId);
            $personDepartment = $person?->department;
        }

        $comparisons = [];
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $dateStr = $current->format('Y-m-d');
            $dayOfWeek = $this->convertCarbonDayToSystem($current->dayOfWeek);

            $attendance = $attendances->get($dateStr);
            $expectedSchedule = $this->getExpectedScheduleForDay($dayOfWeek, $timetableEntries, $shiftAssignment, $personDepartment);

            $comparison = [
                'date' => $dateStr,
                'day_name' => $this->getDayName($dayOfWeek),
                'is_weekend' => in_array($current->dayOfWeek, [Carbon::FRIDAY, Carbon::SATURDAY]),
                'attendance' => $attendance ? [
                    'check_in_time' => $attendance->check_in_time,
                    'check_out_time' => $attendance->check_out_time,
                    'status' => $attendance->status,
                ] : null,
                'expected_schedule' => $expectedSchedule,
                'comparison_result' => $this->compareTimes($attendance, $expectedSchedule, $dateStr),
            ];

            $comparisons[] = $comparison;
            $current->addDay();
        }

        // Calculate summary statistics
        $summary = $this->calculateSummary($comparisons);

        return [
            'comparisons' => $comparisons,
            'summary' => $summary,
        ];
    }

    /**
     * Get expected schedule for a specific day
     */
    private function getExpectedScheduleForDay(int $dayOfWeek, Collection $timetableEntries, $shiftAssignment, $personDepartment): ?array
    {
        // First, try to get from timetable entries
        if ($timetableEntries->has($dayOfWeek)) {
            $entries = $timetableEntries->get($dayOfWeek);
            $sortedEntries = $entries->sortBy('order_in_day');

            if ($sortedEntries->isNotEmpty()) {
                $firstEntry = $sortedEntries->first();
                $lastEntry = $sortedEntries->last();

                // Determine schedule scope
                $scheduleScope = 'personal'; // جدول شخصي
                $scopeLabel = 'جدول شخصي';

                // If person has a department, it's department-specific
                if ($personDepartment) {
                    $scheduleScope = 'department';
                    $scopeLabel = 'جدول قسم: ' . $personDepartment->name;
                }

                return [
                    'type' => 'timetable',
                    'start_time' => $firstEntry->start_time,
                    'end_time' => $lastEntry->end_time,
                    'entries_count' => $entries->count(),
                    'total_minutes' => $entries->sum('work_minutes'),
                    'schedule_scope' => $scheduleScope,
                    'scope_label' => $scopeLabel,
                ];
            }
        }

        // Fallback to shift assignment
        if ($shiftAssignment && $shiftAssignment->shift) {
            // Determine shift scope
            $scheduleScope = 'personal'; // دوام شخصي
            $scopeLabel = 'دوام شخصي';

            // Check if shift is department-specific or institution-wide
            // If person has a department, assume it's department-specific
            if ($personDepartment) {
                $scheduleScope = 'department';
                $scopeLabel = 'دوام قسم: ' . $personDepartment->name;
            } else {
                $scheduleScope = 'institution';
                $scopeLabel = 'دوام عام للمؤسسة';
            }

            return [
                'type' => 'shift',
                'start_time' => $shiftAssignment->shift->start_time,
                'end_time' => $shiftAssignment->shift->end_time,
                'shift_name' => $shiftAssignment->shift->name,
                'schedule_scope' => $scheduleScope,
                'scope_label' => $scopeLabel,
            ];
        }

        return null;
    }

    /**
     * Compare actual attendance times with expected schedule
     */
    private function compareTimes($attendance, $expectedSchedule, $dateStr): array
    {
        if (!$attendance) {
            return [
                'status' => 'absent',
                'is_late' => false,
                'is_early_leave' => false,
                'minutes_late' => null,
                'minutes_early_leave' => null,
                'actual_hours' => 0,
                'expected_hours' => $expectedSchedule ? $this->calculateHours($expectedSchedule['start_time'], $expectedSchedule['end_time']) : 0,
            ];
        }

        if (!$expectedSchedule) {
            return [
                'status' => 'no_schedule',
                'is_late' => false,
                'is_early_leave' => false,
                'minutes_late' => null,
                'minutes_early_leave' => null,
                'actual_hours' => $attendance->check_out_time
                    ? $this->calculateHours($attendance->check_in_time, $attendance->check_out_time)
                    : 0,
                'expected_hours' => 0,
            ];
        }

        // Parse times with date for accurate comparison
        $expectedStart = Carbon::parse($dateStr . ' ' . $expectedSchedule['start_time']);
        $expectedEnd = Carbon::parse($dateStr . ' ' . $expectedSchedule['end_time']);

        // Handle overnight shifts
        if ($expectedEnd->lt($expectedStart)) {
            $expectedEnd->addDay();
        }

        $checkIn = $attendance->check_in_time
            ? Carbon::parse($dateStr . ' ' . $attendance->check_in_time)
            : null;

        $checkOut = $attendance->check_out_time
            ? Carbon::parse($dateStr . ' ' . $attendance->check_out_time)
            : null;

        $isLate = false;
        $minutesLate = null;
        $isEarlyLeave = false;
        $minutesEarlyLeave = null;

        if ($checkIn) {
            // Compare check-in time with expected start time
            if ($checkIn->gt($expectedStart)) {
                $isLate = true;
                $minutesLate = $expectedStart->diffInMinutes($checkIn);
            }
        }

        if ($checkOut && $checkIn) {
            // Compare check-out time with expected end time
            if ($checkOut->lt($expectedEnd)) {
                $isEarlyLeave = true;
                $minutesEarlyLeave = $checkOut->diffInMinutes($expectedEnd);
            }

            $actualHours = $this->calculateHours($attendance->check_in_time, $attendance->check_out_time);
        } else {
            $actualHours = 0;
        }

        $expectedHours = $this->calculateHours($expectedSchedule['start_time'], $expectedSchedule['end_time']);

        return [
            'status' => $attendance->status,
            'is_late' => $isLate,
            'is_early_leave' => $isEarlyLeave,
            'minutes_late' => $minutesLate,
            'minutes_early_leave' => $minutesEarlyLeave,
            'actual_hours' => $actualHours,
            'expected_hours' => $expectedHours,
            'hours_difference' => $actualHours - $expectedHours,
        ];
    }

    /**
     * Calculate hours between two times
     */
    private function calculateHours($startTime, $endTime): float
    {
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);

        if ($end->lt($start)) {
            $end->addDay();
        }

        return round($start->diffInMinutes($end) / 60, 2);
    }

    /**
     * Calculate summary statistics from comparisons
     */
    private function calculateSummary(array $comparisons): array
    {
        $totalDays = count($comparisons);
        $workingDays = collect($comparisons)->where('is_weekend', false)->count();

        $present = 0;
        $absent = 0;
        $late = 0;
        $earlyLeave = 0;
        $totalMinutesLate = 0;
        $totalMinutesEarlyLeave = 0;
        $totalActualHours = 0;
        $totalExpectedHours = 0;

        foreach ($comparisons as $comparison) {
            if ($comparison['is_weekend']) {
                continue;
            }

            $result = $comparison['comparison_result'];

            if ($comparison['attendance']) {
                if ($result['status'] === 'present') {
                    $present++;
                } elseif ($result['status'] === 'absent') {
                    $absent++;
                }

                if ($result['is_late']) {
                    $late++;
                    $totalMinutesLate += $result['minutes_late'] ?? 0;
                }

                if ($result['is_early_leave']) {
                    $earlyLeave++;
                    $totalMinutesEarlyLeave += $result['minutes_early_leave'] ?? 0;
                }

                $totalActualHours += $result['actual_hours'];
            } else {
                $absent++;
            }

            $totalExpectedHours += $result['expected_hours'];
        }

        $averageMinutesLate = $late > 0 ? round($totalMinutesLate / $late, 2) : 0;
        $averageMinutesEarlyLeave = $earlyLeave > 0 ? round($totalMinutesEarlyLeave / $earlyLeave, 2) : 0;
        $attendanceRate = $workingDays > 0 ? round(($present / $workingDays) * 100, 2) : 0;

        return [
            'total_days' => $totalDays,
            'working_days' => $workingDays,
            'present' => $present,
            'absent' => $absent,
            'late_count' => $late,
            'early_leave_count' => $earlyLeave,
            'total_minutes_late' => $totalMinutesLate,
            'total_minutes_early_leave' => $totalMinutesEarlyLeave,
            'average_minutes_late' => $averageMinutesLate,
            'average_minutes_early_leave' => $averageMinutesEarlyLeave,
            'total_actual_hours' => round($totalActualHours, 2),
            'total_expected_hours' => round($totalExpectedHours, 2),
            'hours_difference' => round($totalActualHours - $totalExpectedHours, 2),
            'attendance_rate' => $attendanceRate,
        ];
    }

    /**
     * Convert Carbon dayOfWeek to system dayOfWeek
     * Carbon: 0=Sunday, 1=Monday, ..., 6=Saturday
     * System: 1=Saturday, 2=Sunday, ..., 7=Friday
     */
    private function convertCarbonDayToSystem(int $carbonDay): int
    {
        // Carbon: 0=Sunday, 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday
        // System: 1=Saturday, 2=Sunday, 3=Monday, 4=Tuesday, 5=Wednesday, 6=Thursday, 7=Friday
        $mapping = [
            0 => 2, // Sunday -> 2
            1 => 3, // Monday -> 3
            2 => 4, // Tuesday -> 4
            3 => 5, // Wednesday -> 5
            4 => 6, // Thursday -> 6
            5 => 7, // Friday -> 7
            6 => 1, // Saturday -> 1
        ];

        return $mapping[$carbonDay] ?? $carbonDay;
    }

    /**
     * Get Arabic day name
     */
    private function getDayName(int $dayOfWeek): string
    {
        $days = [
            1 => 'السبت',
            2 => 'الأحد',
            3 => 'الإثنين',
            4 => 'الثلاثاء',
            5 => 'الأربعاء',
            6 => 'الخميس',
            7 => 'الجمعة',
        ];

        return $days[$dayOfWeek] ?? "يوم $dayOfWeek";
    }
}
