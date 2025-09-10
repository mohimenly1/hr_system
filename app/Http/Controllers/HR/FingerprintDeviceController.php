<?php

namespace App\Http\Controllers\Hr;

// ... other use statements
use App\Models\DeviceUser; // <-- إضافة النموذج الجديد
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use App\Models\Employee;
use App\Models\Teacher;
use Illuminate\Support\Str;
use CodingLibs\ZktecoPhp\Libs\ZKTeco;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Attendance;
use App\Services\FingerprintService;


class FingerprintDeviceController extends Controller
{
    /**
     * Helper function to connect to the ZKTeco device using the new library.
     */
    private function getZkInstance()
    {
        $ip = config('app.fingerprint_device_ip');
        $port = config('app.fingerprint_device_port');
        $commKey = config('app.fingerprint_device_comm_key');

        if (!$ip) {
            throw new \Exception('لم يتم تعيين IP جهاز البصمة.');
        }

        $zk = new ZKTeco(ip: $ip, port: (int)$port, password: (int)$commKey);
        
        if (!$zk->connect()) {
             throw new \Exception(message: 'فشل الاتصال بجهاز البصمة. تأكد من صحة البيانات () وأن الجهاز متصل. 0923290545');
        }
        return $zk;
    }

    public function index()
    {
        // --- التغيير: جلب المستخدمين من الجدول المؤقت عند تحميل الصفحة ---
        return Inertia::render('HR/Fingerprint/Index', [
            'deviceUsers' => DeviceUser::all()
        ]);
    }


    /**
     * Tries a simple command to verify the connection.
     */
    public function testConnection()
    {
        $zk = null;
        try {
            $zk = $this->getZkInstance();
            $serialNumber = $zk->serialNumber();
            $zk->disconnect();

            if ($serialNumber) {
                return Redirect::back()->with('success', "تم الاتصال بنجاح! الرقم التسلسلي للجهاز: " . $serialNumber);
            }
            return Redirect::back()->with('error', 'تم الاتصال ولكن فشل جلب البيانات. قد تكون هناك مشكلة توافق أو أن مفتاح الاتصال (CommKey) غير صحيح.');
        } catch (\Exception $e) {
            if ($zk) { $zk->disconnect(); }
            Log::error('Fingerprint Test Connection Error: ' . $e->getMessage());
            return Redirect::back()->with('error', 'فشل اختبار الاتصال: ' . $e->getMessage());
        }
    }
    
    /**
     * Sync users FROM the system TO the device.
     */
    public function syncUsersToDevice()
    {
        $zk = null;
        try {
            $zk = $this->getZkInstance();
            
            $employees = Employee::with('user')->whereNotNull('fingerprint_id')->get();
            $teachers = Teacher::with('user')->whereNotNull('fingerprint_id')->get();
            $systemPersons = $employees->concat($teachers);

            if ($systemPersons->isEmpty()) {
                $zk->disconnect();
                return Redirect::back()->with('info', 'لا يوجد موظفين أو معلمين لديهم رقم بصمة ليتم مزامنتهم.');
            }

            $deviceUsers = $zk->getUsers();
            $addedCount = 0;
            $failedCount = 0;

            foreach ($systemPersons as $person) {
                if (collect($deviceUsers)->firstWhere('userId', (string)$person->fingerprint_id)) {
                    continue;
                }
                
                // --- THIS IS THE FIX: Using Str::slug for maximum compatibility ---
                // This converts "عبد المهيمن" to "aabd-almhymn" which is safer for firmware.
                $safeName = Str::slug($person->user->name, '_');
                
                try {
                    $zk->setUser($person->fingerprint_id, (string)$person->fingerprint_id, $safeName, '', 0);
                    $addedCount++;
                } catch (\Exception $e) {
                    $failedCount++;
                    Log::error("FAILURE: Failed to add person [ID: {$person->fingerprint_id}] to device. Reason: " . $e->getMessage());
                }
            }
            
            $zk->disconnect();
            
            $message = "اكتملت المزامنة. تمت محاولة إضافة {$addedCount} شخص.";
            if ($failedCount > 0) $message .= " فشلت إضافة {$failedCount} شخص.";
            return Redirect::back()->with('success', $message);

        } catch (\Exception $e) {
            if ($zk) { $zk->disconnect(); }
            Log::error('Fingerprint User Sync Error: ' . $e->getMessage());
            return Redirect::back()->with('error', 'خطأ في مزامنة المستخدمين: ' . $e->getMessage());
        }
    }

    /**
     * Get users from device, CACHE them in the database, and then display them.
     */
    public function getDeviceUsers()
    {
        $zk = null;
        try {
            $zk = $this->getZkInstance();
            $deviceUsersRaw = $zk->getUsers();
            $zk->disconnect();

            // --- الخطوة الجديدة: تحديث الجدول المؤقت ---
            if (is_array($deviceUsersRaw)) {
                // أولاً، احذف المستخدمين القدامى الذين لم يعودوا موجودين في الجهاز
                $deviceUids = collect($deviceUsersRaw)->pluck('uid')->all();
                DeviceUser::whereNotIn('uid', $deviceUids)->delete();

                foreach ($deviceUsersRaw as $user) {
                    DeviceUser::updateOrCreate(
                        ['uid' => $user['uid']], // الشرط: ابحث عن مستخدم بنفس الـ uid
                        [
                            'name' => $user['name'], // البيانات للتحديث أو الإنشاء
                            'role' => $user['role'],
                        ]
                    );
                }
            }
            // -----------------------------------------

            $deviceUsers = collect($deviceUsersRaw)->map(fn($user) => [
                'uid' => $user['uid'],
                'name' => $user['name'],
                'role' => $user['role'],
            ])->all();

            return Inertia::render('HR/Fingerprint/Index', [
                'deviceUsers' => $deviceUsers
            ]);

        } catch (\Exception $e) {
            if ($zk) { $zk->disconnect(); }
            Log::error('Fingerprint Get Users Error: ' . $e->getMessage());
            return Redirect::back()->with('error', 'خطأ في جلب المستخدمين: ' . $e->getMessage());
        }
    }

    /**
     * Clear all users from the device.
     */
    public function clearDeviceUsers()
    {
        $zk = null;
        try {
            $zk = $this->getZkInstance();
            $zk->disableDevice();
            // --- تحديث إضافي: مسح الجدول المؤقت أيضاً ---
            $zk->clearAllUsers();
            DeviceUser::truncate(); // يفرغ الجدول
            // ----------------------------------------
            $zk->enableDevice();
            $zk->disconnect();
            
            return Redirect::back()->with('success', 'تم مسح جميع المستخدمين من جهاز البصمة ومن ذاكرة النظام المؤقتة بنجاح.');
        } catch (\Exception $e) {
             if ($zk) { $zk->enableDevice(); $zk->disconnect(); }
            Log::error('Fingerprint Clear Users Error: ' . $e->getMessage());
            return Redirect::back()->with('error', 'خطأ في مسح المستخدمين: ' . $e->getMessage());
        }
    }

    /**
     * Sync attendance records FROM the device TO the system.
     */
    public function syncForDateRange(Carbon $startDate, Carbon $endDate): array
    {
        $zk = null;
        try {
            $zk = $this->getZkInstance();
            $logs = $zk->getAttendances();
            $zk->disconnect();

            if (empty($logs)) {
                return ['status' => 'info', 'message' => 'لا توجد سجلات بصمة جديدة في ذاكرة الجهاز.'];
            }

            Log::debug('Raw attendance logs from device: ' . json_encode($logs));

            $appTimezone = config('app.timezone'); 
            
            $filteredLogs = collect($logs)->filter(function ($log) use ($startDate, $endDate, $appTimezone) {
                if (!isset($log['record_time'], $log['user_id'])) {
                    return false;
                }
                $logTimestamp = Carbon::parse($log['record_time'])->setTimezone($appTimezone);
                return $logTimestamp->between($startDate->copy()->startOfDay(), $endDate->copy()->endOfDay());
            });

            if ($filteredLogs->isEmpty()) {
                return ['status' => 'info', 'message' => "لا توجد سجلات بصمة للفترة من {$startDate->toDateString()} إلى {$endDate->toDateString()}."];
            }

            $logsByDay = $filteredLogs->groupBy(function($log) use ($appTimezone) {
                return Carbon::parse($log['record_time'])->setTimezone($appTimezone)->format('Y-m-d');
            });

            $totalProcessedCount = 0;
            
            foreach ($logsByDay as $dateString => $dayLogs) {
                $logsByUser = $dayLogs->groupBy('user_id');

                foreach ($logsByUser as $fingerprintId => $userLogs) {
                    // --- الخطوة 2: البحث مباشرة في جدول الموظفين ثم المعلمين ---
                    $person = Employee::where('fingerprint_id', $fingerprintId)->first();
                    
                    if (!$person) {
                        $person = Teacher::where('fingerprint_id', $fingerprintId)->first();
                    }

                    // إذا لم يتم العثور على الشخص، انتقل إلى السجل التالي
                    if (!$person) {
                        Log::warning("Fingerprint ID {$fingerprintId} from device does not match any employee or teacher.");
                        continue;
                    }

                    $checkIn = $userLogs->min('record_time');
                    $checkOut = $userLogs->count() > 1 ? $userLogs->max('record_time') : null;

                    // --- الخطوة 3: استخدام العلاقة متعددة الأشكال للحفظ ---
                    Attendance::updateOrCreate(
                        [
                            'attendable_id'   => $person->id,
                            'attendable_type' => get_class($person),
                            'attendance_date' => $dateString,
                        ],
                        [
                            'check_in_time' => Carbon::parse($checkIn)->setTimezone($appTimezone)->format('H:i:s'),
                            'check_out_time' => $checkOut ? Carbon::parse($checkOut)->setTimezone($appTimezone)->format('H:i:s') : null,
                            'status' => 'present',
                        ]
                    );
                    $totalProcessedCount++;
                }
            }
            
            if ($totalProcessedCount === 0) {
                return ['status' => 'info', 'message' => 'تم العثور على سجلات بصمة ولكن لم يتم ربطها بأي موظفين أو معلمين مسجلين.'];
            }

            return ['status' => 'success', 'message' => "تمت المزامنة بنجاح. تمت معالجة {$totalProcessedCount} سجل."];

        } catch (\Exception $e) {
            if ($zk) $zk->disconnect();
            Log::error("Fingerprint Sync Error on line {$e->getLine()}: {$e->getMessage()}");
            return ['status' => 'error', 'message' => 'حدث خطأ أثناء المزامنة: ' . $e->getMessage()];
        }
    }

    public function syncAttendance(Request $request, FingerprintService $fingerprintService)
    {
        $request->validate(['date' => 'required|date']);
        $targetDate = Carbon::parse($request->date);

        try {
            $result = $fingerprintService->syncForDateRange($targetDate->toDateString(), $targetDate->toDateString());
            return Redirect::back()->with('success', $result['message']);
        } catch (\Exception $e) {
            Log::error("Fingerprint Sync Error: " . $e->getMessage());
            return Redirect::back()->with('error', 'حدث خطأ أثناء المزامنة: ' . $e->getMessage());
        }
    }

    /**
     * Syncs attendance for a full month using the service.
     */
    public function syncMonthly(Request $request, FingerprintService $fingerprintService)
    {
        $request->validate(['month' => 'required|date_format:Y-m']);
        $month = Carbon::parse($request->month);
        
        $startDate = $month->copy()->startOfMonth();
        $endDate = $month->copy()->endOfMonth();

        try {
            $result = $fingerprintService->syncForDateRange($startDate->toDateString(), $endDate->toDateString());
            return Redirect::back()->with('success', $result['message']);
        } catch (\Exception $e) {
            Log::error("Fingerprint Sync Error: " . $e->getMessage());
            return Redirect::back()->with('error', 'حدث خطأ أثناء المزامنة: ' . $e->getMessage());
        }
    }
}

