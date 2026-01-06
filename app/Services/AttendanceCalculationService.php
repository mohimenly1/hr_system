<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\TimetableEntry;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AttendanceCalculationService
{
    /**
     * Calculate attendance status based on timetable and shift.
     */
    public function calculateAttendanceStatus($attendable, Carbon $attendanceDate, ?Carbon $checkInTime = null, ?Carbon $checkOutTime = null): array
    {
        $dayOfWeek = $attendanceDate->dayOfWeek; // 0 = Sunday, 6 = Saturday
        // Convert to our system: 6 = Saturday, 0 = Sunday, 1-5 = Monday-Friday
        $dayOfWeek = $dayOfWeek === 0 ? 6 : ($dayOfWeek === 6 ? 0 : $dayOfWeek);

        // Get expected timetable entries for this day
        $expectedEntries = TimetableEntry::where('schedulable_id', $attendable->id)
            ->where('schedulable_type', get_class($attendable))
            ->where('day_of_week', $dayOfWeek)
            ->where('is_break', false)
            ->orderBy('start_time')
            ->get();

        // If no timetable entries, check shift assignment
        if ($expectedEntries->isEmpty()) {
            $shiftAssignment = $attendable->shiftAssignment;
            if ($shiftAssignment && $shiftAssignment->shift) {
                return $this->calculateBasedOnShift(
                    $shiftAssignment->shift,
                    $checkInTime,
                    $checkOutTime,
                    $attendanceDate
                );
            }

            return [
                'status' => 'absent',
                'expected_hours' => 0,
                'actual_hours' => 0,
                'is_late' => false,
                'is_early_leave' => false,
                'notes' => 'لا يوجد جدول محدد لهذا اليوم',
            ];
        }

        return $this->calculateBasedOnTimetable(
            $expectedEntries,
            $checkInTime,
            $checkOutTime,
            $attendanceDate
        );
    }

    /**
     * Calculate attendance based on shift.
     */
    private function calculateBasedOnShift(Shift $shift, ?Carbon $checkInTime, ?Carbon $checkOutTime, Carbon $attendanceDate): array
    {
        $expectedStart = Carbon::parse($attendanceDate->format('Y-m-d') . ' ' . $shift->start_time);
        $expectedEnd = Carbon::parse($attendanceDate->format('Y-m-d') . ' ' . $shift->end_time);

        // Handle overnight shifts
        if ($expectedEnd->lt($expectedStart)) {
            $expectedEnd->addDay();
        }

        $expectedMinutes = $expectedStart->diffInMinutes($expectedEnd);
        $expectedHours = $expectedMinutes / 60;

        if (!$checkInTime) {
            return [
                'status' => 'absent',
                'expected_hours' => $expectedHours,
                'actual_hours' => 0,
                'is_late' => false,
                'is_early_leave' => false,
                'notes' => 'لم يتم تسجيل الحضور',
            ];
        }

        $actualStart = $checkInTime;
        $actualEnd = $checkOutTime ?? $checkInTime->copy()->addHours($expectedHours);

        // Check if late
        $gracePeriod = $shift->grace_period_minutes ?? 15;
        $isLate = $actualStart->gt($expectedStart->copy()->addMinutes($gracePeriod));

        // Check if early leave
        $isEarlyLeave = $checkOutTime && $actualEnd->lt($expectedEnd->copy()->subMinutes($gracePeriod));

        $actualMinutes = $actualStart->diffInMinutes($actualEnd);
        $actualHours = $actualMinutes / 60;

        $status = 'present';
        if ($isLate) {
            $status = 'late';
        } elseif ($isEarlyLeave) {
            $status = 'early_leave';
        }

        return [
            'status' => $status,
            'expected_hours' => $expectedHours,
            'actual_hours' => $actualHours,
            'is_late' => $isLate,
            'is_early_leave' => $isEarlyLeave,
            'late_minutes' => $isLate ? $expectedStart->diffInMinutes($actualStart) : 0,
            'early_leave_minutes' => $isEarlyLeave ? $actualEnd->diffInMinutes($expectedEnd) : 0,
            'notes' => $this->generateNotes($isLate, $isEarlyLeave, $expectedHours, $actualHours),
        ];
    }

    /**
     * Calculate attendance based on timetable entries.
     */
    private function calculateBasedOnTimetable($expectedEntries, ?Carbon $checkInTime, ?Carbon $checkOutTime, Carbon $attendanceDate): array
    {
        $expectedTotalMinutes = 0;
        $expectedStart = null;
        $expectedEnd = null;

        foreach ($expectedEntries as $entry) {
            if ($entry->work_minutes) {
                $expectedTotalMinutes += $entry->work_minutes;
            } else {
                $start = Carbon::parse($entry->start_time);
                $end = Carbon::parse($entry->end_time);
                if ($end->lt($start)) {
                    $end->addDay();
                }
                $expectedTotalMinutes += $start->diffInMinutes($end);
            }

            $entryStart = Carbon::parse($attendanceDate->format('Y-m-d') . ' ' . $entry->start_time);
            $entryEnd = Carbon::parse($attendanceDate->format('Y-m-d') . ' ' . $entry->end_time);
            if ($entryEnd->lt($entryStart)) {
                $entryEnd->addDay();
            }

            if (!$expectedStart || $entryStart->lt($expectedStart)) {
                $expectedStart = $entryStart;
            }
            if (!$expectedEnd || $entryEnd->gt($expectedEnd)) {
                $expectedEnd = $entryEnd;
            }
        }

        $expectedHours = $expectedTotalMinutes / 60;

        if (!$checkInTime) {
            return [
                'status' => 'absent',
                'expected_hours' => $expectedHours,
                'actual_hours' => 0,
                'is_late' => false,
                'is_early_leave' => false,
                'notes' => 'لم يتم تسجيل الحضور',
            ];
        }

        $actualStart = $checkInTime;
        $actualEnd = $checkOutTime ?? $checkInTime->copy()->addHours($expectedHours);

        // Check if late (15 minutes grace period)
        $gracePeriod = 15;
        $isLate = $actualStart->gt($expectedStart->copy()->addMinutes($gracePeriod));

        // Check if early leave
        $isEarlyLeave = $checkOutTime && $actualEnd->lt($expectedEnd->copy()->subMinutes($gracePeriod));

        $actualMinutes = $actualStart->diffInMinutes($actualEnd);
        $actualHours = $actualMinutes / 60;

        $status = 'present';
        if ($isLate) {
            $status = 'late';
        } elseif ($isEarlyLeave) {
            $status = 'early_leave';
        }

        return [
            'status' => $status,
            'expected_hours' => $expectedHours,
            'actual_hours' => $actualHours,
            'is_late' => $isLate,
            'is_early_leave' => $isEarlyLeave,
            'late_minutes' => $isLate ? $expectedStart->diffInMinutes($actualStart) : 0,
            'early_leave_minutes' => $isEarlyLeave ? $actualEnd->diffInMinutes($expectedEnd) : 0,
            'notes' => $this->generateNotes($isLate, $isEarlyLeave, $expectedHours, $actualHours),
        ];
    }

    /**
     * Generate notes based on attendance status.
     */
    private function generateNotes(bool $isLate, bool $isEarlyLeave, float $expectedHours, float $actualHours): string
    {
        $notes = [];

        if ($isLate) {
            $notes[] = 'تأخر في الحضور';
        }

        if ($isEarlyLeave) {
            $notes[] = 'مغادرة مبكرة';
        }

        if ($actualHours < $expectedHours * 0.8) {
            $notes[] = 'ساعات عمل غير كافية';
        }

        return implode('، ', $notes) ?: 'حضور طبيعي';
    }

    /**
     * Calculate monthly attendance summary.
     */
    public function calculateMonthlySummary($attendable, int $year, int $month): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $attendances = Attendance::where('attendable_id', $attendable->id)
            ->where('attendable_type', get_class($attendable))
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->get();

        $totalExpectedHours = 0;
        $totalActualHours = 0;
        $presentDays = 0;
        $absentDays = 0;
        $lateDays = 0;
        $earlyLeaveDays = 0;

        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $dayOfWeek = $currentDate->dayOfWeek;
            $dayOfWeek = $dayOfWeek === 0 ? 6 : ($dayOfWeek === 6 ? 0 : $dayOfWeek);

            // Check if this day is a work day
            $expectedEntries = TimetableEntry::where('schedulable_id', $attendable->id)
                ->where('schedulable_type', get_class($attendable))
                ->where('day_of_week', $dayOfWeek)
                ->where('is_break', false)
                ->get();

            if ($expectedEntries->isEmpty()) {
                $currentDate->addDay();
                continue;
            }

            $dayExpectedMinutes = 0;
            foreach ($expectedEntries as $entry) {
                $dayExpectedMinutes += $entry->work_minutes ?? 0;
            }
            $dayExpectedHours = $dayExpectedMinutes / 60;
            $totalExpectedHours += $dayExpectedHours;

            $attendance = $attendances->firstWhere('attendance_date', $currentDate->format('Y-m-d'));

            if ($attendance) {
                if ($attendance->check_in_time) {
                    $checkIn = Carbon::parse($attendance->attendance_date . ' ' . $attendance->check_in_time);
                    $checkOut = $attendance->check_out_time
                        ? Carbon::parse($attendance->attendance_date . ' ' . $attendance->check_out_time)
                        : null;

                    $actualMinutes = $checkIn->diffInMinutes($checkOut ?? $checkIn->copy()->addHours($dayExpectedHours));
                    $totalActualHours += $actualMinutes / 60;

                    $presentDays++;

                    if ($attendance->status === 'late') {
                        $lateDays++;
                    }
                    if ($attendance->status === 'early_leave') {
                        $earlyLeaveDays++;
                    }
                } else {
                    $absentDays++;
                }
            } else {
                $absentDays++;
            }

            $currentDate->addDay();
        }

        return [
            'total_expected_hours' => round($totalExpectedHours, 2),
            'total_actual_hours' => round($totalActualHours, 2),
            'present_days' => $presentDays,
            'absent_days' => $absentDays,
            'late_days' => $lateDays,
            'early_leave_days' => $earlyLeaveDays,
            'attendance_rate' => $totalExpectedHours > 0
                ? round(($totalActualHours / $totalExpectedHours) * 100, 2)
                : 0,
        ];
    }
}
