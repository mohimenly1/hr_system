<?php

namespace App\Services;

use App\Models\LeaveType;
use App\Models\Teacher;
use App\Models\Employee;
use Carbon\Carbon;

class LeaveBalanceService
{
    /**
     * Calculates the duration of a leave request in days.
     * For now, it's a simple day difference. Can be expanded to exclude weekends.
     */
    public function calculateLeaveDuration(string $startDate, string $endDate): int
    {
        return Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
    }

    /**
     * Calculates the total number of approved leave days taken by a person for a specific leave type within the current year.
     */
    public function getUsedBalance(Employee|Teacher $person, LeaveType $leaveType): int
    {
        $currentYear = now()->year;

        return $person->leaves()
            ->where('status', 'approved')
            ->where('leave_type_id', $leaveType->id)
            ->whereYear('start_date', $currentYear)
            ->get()
            ->sum(function ($leave) {
                return $this->calculateLeaveDuration($leave->start_date, $leave->end_date);
            });
    }

    /**
     * Calculates the remaining available balance for a person for a specific leave type.
     */
    public function getAvailableBalance(Employee|Teacher $person, LeaveType $leaveType): int
    {
        $totalBalance = $leaveType->default_balance;
        $usedBalance = $this->getUsedBalance($person, $leaveType);

        return $totalBalance - $usedBalance;
    }

    /**
     * Get all leave balances for a specific person.
     */
    public function getAllBalancesForPerson(Employee|Teacher $person): array
    {
        $leaveTypes = LeaveType::where('is_active', true)->get();
        $balances = [];

        foreach ($leaveTypes as $type) {
            $balances[] = [
                'name' => $type->name,
                'total' => $type->default_balance,
                'used' => $this->getUsedBalance($person, $type),
                'available' => $this->getAvailableBalance($person, $type),
            ];
        }

        return $balances;
    }
}
