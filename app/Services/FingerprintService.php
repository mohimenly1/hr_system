<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use CodingLibs\ZktecoPhp\Libs\ZKTeco;

class FingerprintService
{
    private $zk;

    /**
     * Attempts to connect to the fingerprint device.
     * Throws an exception if connection fails.
     */
    private function connect()
    {
        // يتصل بالجهاز باستخدام الإعدادات من ملف .env
        $this->zk = new ZKTeco(config('app.fingerprint_device_ip'), config('app.fingerprint_device_port'));
        if (!$this->zk->connect()) {
            throw new \Exception("غير قادر على الاتصال بجهاز البصمة.");
        }
    }

    /**
     * Disconnects from the fingerprint device.
     */
    private function disconnect()
    {
        if ($this->zk) {
            $this->zk->disconnect();
        }
    }

    /**
     * Fetches all users from the fingerprint device.
     *
     * @return array
     * @throws \Exception
     */
    public function getUsers(): array
    {
        try {
            $this->connect();
            $users = $this->zk->getUsers();
            $this->disconnect();
            return $users;
        } catch (\Exception $e) {
            $this->disconnect();
            Log::error('Fingerprint Service - getUsers Error: ' . $e->getMessage());
            // رمي الخطأ ليتم التعامل معه في الكنترولر أو إرجاع مصفوفة فارغة
            throw $e;
        }
    }


    /**
     * Syncs attendance records for a given date range.
     *
     * @param string $startDate
     * @param string $endDate
     * @param int|null $fingerprintId
     * @return array
     */
    public function syncForDateRange(string $startDate, string $endDate, ?int $fingerprintId = null): array
    {
        try {
            $this->connect();
            $logs = $this->zk->getAttendances();
            $this->disconnect();

            if (empty($logs)) {
                return ['processed' => 0, 'message' => 'لا توجد سجلات بصمة جديدة في الجهاز.'];
            }
            
            $appTimezone = config('app.timezone');
            $startDate = Carbon::parse($startDate, $appTimezone)->startOfDay();
            $endDate = Carbon::parse($endDate, $appTimezone)->endOfDay();
            $processedCount = 0;

            $filteredLogs = collect($logs)->filter(function ($log) use ($startDate, $endDate, $fingerprintId) {
                if (!isset($log['record_time'], $log['user_id'])) return false;
                $recordDate = Carbon::parse($log['record_time']); 
                $isWithinRange = $recordDate->between($startDate, $endDate);
                return $fingerprintId ? ($isWithinRange && $log['user_id'] == $fingerprintId) : $isWithinRange;
            });

            if ($filteredLogs->isEmpty()) {
                $period = $startDate->isSameDay($endDate) ? "ليوم {$startDate->toDateString()}" : "للفترة من {$startDate->toDateString()} إلى {$endDate->toDateString()}";
                return ['processed' => 0, 'message' => "لا توجد سجلات بصمة {$period}."];
            }

            $groupedLogs = $filteredLogs->groupBy(fn($log) => Carbon::parse($log['record_time'])->toDateString() . '_' . $log['user_id']);

            foreach ($groupedLogs as $group) {
                $firstLog = $group->first();
                $fingerprintId = $firstLog['user_id'];
                $attendanceDate = Carbon::parse($firstLog['record_time'])->toDateString();

                $person = Employee::with('shiftAssignment.shift')->where('fingerprint_id', $fingerprintId)->first()
                         ?? Teacher::with('shiftAssignment.shift')->where('fingerprint_id', $fingerprintId)->first();

                if (!$person) {
                    continue;
                }
                
                $checkIn = $group->min('record_time');
                $checkOut = $group->count() > 1 ? $group->max('record_time') : null;

                $status = 'present';
                $notes = [];
                $shiftAssignment = $person->shiftAssignment;

                if ($shiftAssignment && $shiftAssignment->shift) {
                    $shift = $shiftAssignment->shift;
                    
                    $shiftStartTime = Carbon::parse($attendanceDate . ' ' . $shift->start_time, $appTimezone);
                    $shiftEndTime = Carbon::parse($attendanceDate . ' ' . $shift->end_time, $appTimezone);
                    $deadline = $shiftStartTime->copy()->addMinutes($shift->grace_period_minutes);
                    
                    $checkInTime = Carbon::parse($checkIn, $appTimezone);
                    
                    if ($checkInTime->isAfter($deadline)) {
                        $status = 'late';
                        // --- THE FIX: Calculate difference using timestamps for reliability ---
                        $lateSeconds = $checkInTime->getTimestamp() - $deadline->getTimestamp();
                        $lateMinutes = (int) round($lateSeconds / 60);
                        if ($lateMinutes > 0) {
                            $notes[] = "تأخير لمدة {$lateMinutes} دقيقة.";
                        }
                    }

                    if ($checkOut) {
                        $checkOutTime = Carbon::parse($checkOut, $appTimezone);
                        if ($checkOutTime->isBefore($shiftEndTime)) {
                             // --- THE FIX: Calculate difference using timestamps for reliability ---
                            $earlySeconds = $shiftEndTime->getTimestamp() - $checkOutTime->getTimestamp();
                            $earlyMinutes = (int) round($earlySeconds / 60);
                            if ($earlyMinutes > 0) {
                                $notes[] = "مغادرة مبكرة بـ {$earlyMinutes} دقيقة.";
                            }
                        }
                    } else {
                        $notes[] = "لم يتم تسجيل بصمة الخروج.";
                    }
                } else {
                    if (!$checkOut) {
                        $notes[] = "لم يتم تسجيل بصمة الخروج.";
                    }
                }

                Attendance::updateOrCreate(
                    [
                        'attendable_id' => $person->id,
                        'attendable_type' => get_class($person),
                        'attendance_date' => $attendanceDate,
                    ],
                    [
                        'check_in_time' => Carbon::parse($checkIn)->format('H:i:s'),
                        'check_out_time' => $checkOut ? Carbon::parse($checkOut)->format('H:i:s') : null,
                        'status' => $status,
                        'notes' => implode(' | ', $notes),
                    ]
                );
                $processedCount++;
            }
            
            if ($processedCount === 0) {
                 return ['processed' => 0, 'message' => 'تم العثور على سجلات بصمة ولكنها غير مرتبطة بأي موظفين.'];
            }

            return ['processed' => $processedCount, 'message' => "تمت معالجة {$processedCount} سجل حضور بنجاح."];

        } catch (\Exception $e) {
            $this->disconnect();
            Log::error('Fingerprint Service Error on line ' . $e->getLine() . ': ' . $e->getMessage());
            throw $e;
        }
    }

    public function syncSingleUser(int $fingerprintId, string $date): bool
    {
        $result = $this->syncForDateRange($date, $date, $fingerprintId);
        return $result['processed'] > 0;
    }
}

