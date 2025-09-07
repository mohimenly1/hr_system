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
            
            $startDate = Carbon::parse($startDate)->startOfDay();
            $endDate = Carbon::parse($endDate)->endOfDay();
            $processedCount = 0;

            $filteredLogs = collect($logs)->filter(function ($log) use ($startDate, $endDate, $fingerprintId) {
                if (!isset($log['record_time'], $log['user_id'])) {
                    return false;
                }
                $recordDate = Carbon::parse($log['record_time']);
                $isWithinRange = $recordDate->between($startDate, $endDate);
                
                // إذا تم تحديد رقم بصمة، يتم الفلترة بناءً عليه أيضاً
                if ($fingerprintId) {
                    return $isWithinRange && $log['user_id'] == $fingerprintId;
                }
                
                return $isWithinRange;
            });

            if ($filteredLogs->isEmpty()) {
                $period = $startDate->isSameDay($endDate) ? "ليوم {$startDate->toDateString()}" : "للفترة من {$startDate->toDateString()} إلى {$endDate->toDateString()}";
                return ['processed' => 0, 'message' => "لا توجد سجلات بصمة {$period}."];
            }

            // تجميع السجلات حسب اليوم والمستخدم
            $groupedLogs = $filteredLogs->groupBy(function ($log) {
                return Carbon::parse($log['record_time'])->toDateString() . '_' . $log['user_id'];
            });


            foreach ($groupedLogs as $group) {
                $firstLog = $group->first();
                $fingerprintId = $firstLog['user_id'];
                $attendanceDate = Carbon::parse($firstLog['record_time'])->toDateString();

                // البحث عن الموظف أو المعلم
                $person = Employee::where('fingerprint_id', $fingerprintId)->first()
                         ?? Teacher::where('fingerprint_id', $fingerprintId)->first();

                if (!$person) {
                    continue; // تجاهل السجلات لمستخدمين غير معروفين
                }
                
                $checkIn = $group->min('record_time');
                $checkOut = $group->count() > 1 ? $group->max('record_time') : null;

                Attendance::updateOrCreate(
                    [
                        'attendable_id' => $person->id,
                        'attendable_type' => get_class($person),
                        'attendance_date' => $attendanceDate,
                    ],
                    [
                        'check_in_time' => Carbon::parse($checkIn)->format('H:i:s'),
                        'check_out_time' => $checkOut ? Carbon::parse($checkOut)->format('H:i:s') : null,
                        'status' => 'present',
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
            Log::error('Fingerprint Service Error: ' . $e->getMessage());
            // رمي الخطأ ليتم التعامل معه في الكنترولر
            throw $e;
        }
    }

    /**
     * A simplified method to sync attendance for a single user for today.
     *
     * @param int $fingerprintId
     * @return bool Returns true if a record was processed, false otherwise.
     */
    public function syncSingleUser(int $fingerprintId, string $date): bool
    {
        $result = $this->syncForDateRange($date, $date, $fingerprintId);
        return $result['processed'] > 0;
    }
}
