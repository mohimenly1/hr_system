<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\ScheduleTemplate;
use App\Models\Shift;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Models\CustomHour;
use App\Models\DefaultShiftSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class TimetableGeneratorService
{
    protected Collection $personnel;
    protected Collection $scheduleTemplates;
    protected Collection $shifts;

    public function generate()
    {
        Log::info('Starting timetable generation process...');
        TimetableEntry::query()->truncate();
        Log::info('Cleared old timetable entries.');

        $this->loadData();

        foreach ($this->personnel as $person) {
            $this->schedulePerson($person);
        }

        Log::info('Timetable generation process finished.');
        return ['message' => 'تم إكمال عملية الجدولة بنجاح.'];
    }

    /**
     * Generate timetable for a single person only
     */
    public function generateForPerson($personId, $personType)
    {
        Log::info("Starting timetable generation for person {$personId} of type {$personType}...");

        // Delete existing entries for this person only
        TimetableEntry::where('schedulable_id', $personId)
            ->where('schedulable_type', $personType)
            ->delete();

        Log::info("Cleared old timetable entries for person {$personId}.");

        // Load data
        $this->scheduleTemplates = ScheduleTemplate::with('constraints')->where('is_active', true)->get();
        $this->shifts = Shift::all();

        // Load the specific person
        $person = null;
        if ($personType === 'App\\Models\\Employee') {
            $person = Employee::with(['user', 'constraints', 'department'])->find($personId);
        } elseif ($personType === 'App\\Models\\Teacher') {
            $person = Teacher::with(['user', 'constraints', 'department'])->find($personId);
        }

        if (!$person) {
            Log::warning("Person {$personId} of type {$personType} not found.");
            return ['message' => 'لم يتم العثور على الموظف/المعلم المحدد.', 'success' => false];
        }

        // Schedule this person
        $this->schedulePerson($person);

        Log::info("Timetable generation for person {$personId} finished.");
        return ['message' => 'تم إنشاء الجدول بنجاح.', 'success' => true];
    }

    private function loadData(): void
    {
        $employees = Employee::with(['user', 'constraints', 'department'])->get();
        $teachers = Teacher::with(['user', 'constraints', 'department'])->get();
        $this->personnel = $employees->concat($teachers);
        $this->scheduleTemplates = ScheduleTemplate::with('constraints')->where('is_active', true)->get();
        $this->shifts = Shift::all(); // تحميل جميع الدوامات مرة واحدة
        Log::info("Loaded {$this->personnel->count()} personnel and {$this->shifts->count()} shifts for scheduling.");
    }

    private function getConsolidatedConstraints(Employee|Teacher $person): array
    {
        $individualConstraints = $person->constraints->pluck('value', 'constraint_type');
        $templateConstraints = collect();
        return $individualConstraints->union($templateConstraints)->all();
    }

    private function schedulePerson(Employee|Teacher $person): void
    {
        $constraints = $this->getConsolidatedConstraints($person);
        $employmentType = $constraints['employment_type'] ?? null;

        Log::info("Processing {$person->user->name} with constraints: ", $constraints);

        if ($person instanceof Employee && !empty($constraints['required_days'])) {
            $this->scheduleEmployee($person, $constraints, $employmentType);
        }
        elseif ($person instanceof Teacher && !empty($constraints['required_days'])) {
            $this->scheduleTeacher($person, $constraints, $employmentType);
        }
    }

    private function scheduleEmployee(Employee $employee, array $constraints, ?string $employmentType): void
    {
        $workType = $employmentType ?? 'monthly_full';

        // للموظفين بالساعات - استخدام الساعات المخصصة إذا كانت موجودة
        if ($employmentType === 'hourly') {
            $customHours = CustomHour::where('hourly_id', $employee->id)
                ->where('hourly_type', get_class($employee))
                ->get();

            Log::info("Found {$customHours->count()} custom hours for employee {$employee->id}");

            if ($customHours->isNotEmpty()) {
                // استخدام الساعات المخصصة
                $orderInDay = 0;
                foreach ($customHours as $customHour) {
                    if ($customHour->hours > 0) {
                        // تحويل start_time إلى تنسيق H:i:s إذا لزم الأمر
                        $startTime = $customHour->start_time ?? '08:00:00';
                        if ($startTime && strlen($startTime) === 5 && substr_count($startTime, ':') === 1) {
                            $startTime = $startTime . ':00';
                        }
                        
                        $endTime = $customHour->end_time;

                        // إذا لم يكن هناك وقت نهاية محدد، احسبه بناءً على الساعات
                        if (!$endTime) {
                            $start = Carbon::parse($startTime);
                            $end = $start->copy()->addHours((float)$customHour->hours);
                            $endTime = $end->format('H:i:s');
                        } else {
                            // تحويل end_time إلى تنسيق H:i:s إذا لزم الأمر
                            if (strlen($endTime) === 5 && substr_count($endTime, ':') === 1) {
                                $endTime = $endTime . ':00';
                            }
                        }

                        $start = Carbon::parse($startTime);
                        $end = Carbon::parse($endTime);
                        if ($end->lt($start)) {
                            $end->addDay();
                        }
                        $workMinutes = (int)($customHour->hours * 60);

                        Log::info("Creating timetable entry for employee {$employee->id}, day {$customHour->day_of_week}, time {$startTime} - {$endTime}");

                        TimetableEntry::create([
                            'schedulable_id' => $employee->id,
                            'schedulable_type' => get_class($employee),
                            'day_of_week' => $customHour->day_of_week,
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'work_type' => 'hourly',
                            'work_minutes' => $workMinutes,
                            'is_break' => false,
                            'order_in_day' => $orderInDay++,
                        ]);
                    }
                }
                Log::info("Created {$orderInDay} timetable entries from custom hours for employee {$employee->id}");
                return;
            } else {
                Log::warning("No custom hours found for hourly employee {$employee->id}");
            }
        }

        // للموظفين الشهريين أو الذين لا يملكون ساعات مخصصة
        $startTime = '08:00:00';
        $endTime = '16:00:00';
        $shiftId = null;
        $workDays = $constraints['required_days'] ?? [];

        // الأولوية الأولى: التحقق من وجود دوام محدد
        if (!empty($constraints['assigned_shift_id'])) {
            $assignedShift = $this->shifts->firstWhere('id', $constraints['assigned_shift_id']);
            if ($assignedShift) {
                $startTime = $assignedShift->start_time;
                $endTime = $assignedShift->end_time;
                $shiftId = $assignedShift->id;
            }
        }
        // الأولوية الثانية: استخدام الدوام الافتراضي (للقسم أولاً، ثم المؤسسة)
        elseif ($employmentType === 'monthly_full' || $employmentType === 'monthly_partial') {
            $departmentId = $employee->department_id;

            // الأولوية: إعدادات القسم أولاً
            $defaultShift = null;
            if ($departmentId) {
                $defaultShift = DefaultShiftSetting::where('is_active', true)
                    ->where('department_id', $departmentId)
                    ->first();
            }

            // إذا لم يكن هناك إعدادات للقسم، استخدم إعدادات المؤسسة
            if (!$defaultShift) {
                $defaultShift = DefaultShiftSetting::where('is_active', true)
                    ->whereNull('department_id')
                    ->first();
            }

            if ($defaultShift) {
                $startTime = $defaultShift->start_time->format('H:i:s');
                $endTime = $defaultShift->end_time->format('H:i:s');

                // إذا لم تكن هناك أيام عمل محددة، استخدم أيام العمل من الدوام الافتراضي
                if (empty($workDays)) {
                    $workDays = $defaultShift->work_days;
                }

                // تحديث القيود لتشمل إجمالي الساعات من الدوام الافتراضي
                if (empty($constraints['total_hours_per_week'])) {
                    $constraints['total_hours_per_week'] = $defaultShift->hours_per_week;
                }

                Log::info("Using default shift setting for employee {$employee->id}: " . ($defaultShift->department_id ? "Department {$defaultShift->department_id}" : "Organization-wide"));
            }
        }
        // الأولوية الثالثة: الاعتماد على إجمالي الساعات الأسبوعية
        elseif (!empty($constraints['total_hours_per_week'])) {
            $totalHours = (int)$constraints['total_hours_per_week'];
            $numDays = count($workDays);
            if ($numDays > 0) {
                $hoursPerDay = floor($totalHours / $numDays);
                $endCarbon = Carbon::parse($startTime)->addHours($hoursPerDay);
                $endTime = $endCarbon->format('H:i:s');
            }
        }

        // تحديد نوع العمل بناءً على employment_type
        if ($employmentType === 'monthly_partial') {
            $workType = 'monthly_partial';
        }

        // إذا لم تكن هناك أيام عمل محددة، لا ننشئ جدول
        if (empty($workDays)) {
            Log::warning("No work days specified for employee {$employee->id}");
            return;
        }

        $orderInDay = 0;
        foreach ($workDays as $day) {
            $start = Carbon::parse($startTime);
            $end = Carbon::parse($endTime);
            if ($end->lt($start)) {
                $end->addDay();
            }
            $workMinutes = $start->diffInMinutes($end);

            TimetableEntry::create([
                'schedulable_id' => $employee->id,
                'schedulable_type' => get_class($employee),
                'day_of_week' => $day,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'shift_id' => $shiftId,
                'work_type' => $workType,
                'work_minutes' => $workMinutes,
                'is_break' => false,
                'order_in_day' => $orderInDay++,
            ]);
        }
    }

    private function scheduleTeacher(Teacher $teacher, array $constraints, ?string $employmentType): void
    {
        $classDuration = 60;
        $dailyStartTime = Carbon::parse('08:00:00');
        $workType = $employmentType ?? 'monthly_full';
        $workDays = $constraints['required_days'] ?? [];

        // للمعلمين بالساعات - استخدام الساعات المخصصة إذا كانت موجودة
        if ($employmentType === 'hourly') {
            $customHours = CustomHour::where('hourly_id', $teacher->id)
                ->where('hourly_type', get_class($teacher))
                ->get();

            Log::info("Found {$customHours->count()} custom hours for teacher {$teacher->id}");

            if ($customHours->isNotEmpty()) {
                // استخدام الساعات المخصصة
                $orderInDay = 0;
                foreach ($customHours as $customHour) {
                    if ($customHour->hours > 0) {
                        // تحويل start_time إلى تنسيق H:i:s إذا لزم الأمر
                        $startTime = $customHour->start_time ?? '08:00:00';
                        if ($startTime && strlen($startTime) === 5 && substr_count($startTime, ':') === 1) {
                            $startTime = $startTime . ':00';
                        }
                        
                        $endTime = $customHour->end_time;

                        // إذا لم يكن هناك وقت نهاية محدد، احسبه بناءً على الساعات
                        if (!$endTime) {
                            $start = Carbon::parse($startTime);
                            $end = $start->copy()->addHours((float)$customHour->hours);
                            $endTime = $end->format('H:i:s');
                        } else {
                            // تحويل end_time إلى تنسيق H:i:s إذا لزم الأمر
                            if (strlen($endTime) === 5 && substr_count($endTime, ':') === 1) {
                                $endTime = $endTime . ':00';
                            }
                        }

                        $start = Carbon::parse($startTime);
                        $end = Carbon::parse($endTime);
                        if ($end->lt($start)) {
                            $end->addDay();
                        }
                        $workMinutes = (int)($customHour->hours * 60);

                        Log::info("Creating timetable entry for teacher {$teacher->id}, day {$customHour->day_of_week}, time {$startTime} - {$endTime}");

                        TimetableEntry::create([
                            'schedulable_id' => $teacher->id,
                            'schedulable_type' => get_class($teacher),
                            'day_of_week' => $customHour->day_of_week,
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'work_type' => 'hourly',
                            'work_minutes' => $workMinutes,
                            'is_break' => false,
                            'order_in_day' => $orderInDay++,
                        ]);
                    }
                }
                Log::info("Created {$orderInDay} timetable entries from custom hours for teacher {$teacher->id}");
                return;
            } else {
                Log::warning("No custom hours found for hourly teacher {$teacher->id}");
            }

            // إذا لم تكن هناك ساعات مخصصة، استخدم الطريقة القديمة
            if (!empty($constraints['total_hours_per_week'])) {
                $totalHours = (int)$constraints['total_hours_per_week'];
                $numDays = count($constraints['required_days']);
                if ($numDays > 0) {
                    $hoursPerDay = floor($totalHours / $numDays);
                    $endTime = $dailyStartTime->copy()->addHours($hoursPerDay)->format('H:i:s');

                    foreach ($workDays as $day) {
                        $start = Carbon::parse($dailyStartTime->format('H:i:s'));
                        $end = Carbon::parse($endTime);
                        $workMinutes = $start->diffInMinutes($end);

                        TimetableEntry::create([
                            'schedulable_id' => $teacher->id,
                            'schedulable_type' => get_class($teacher),
                            'day_of_week' => $day,
                            'start_time' => $dailyStartTime->format('H:i:s'),
                            'end_time' => $endTime,
                            'work_type' => 'hourly',
                            'work_minutes' => $workMinutes,
                            'is_break' => false,
                            'order_in_day' => 0,
                        ]);
                    }
                    return;
                }
            }
        }

        // للمعلمين الشهريين (كامل أو جزئي)
        if (empty($constraints['allowed_subjects']) || empty($constraints['allowed_sections'])) {
            Log::warning("Teacher {$teacher->user->name} has no allowed subjects or sections.");
            return;
        }

        $possibleClasses = [];
        foreach($constraints['allowed_subjects'] as $subjectId) {
            foreach($constraints['allowed_sections'] as $sectionId) {
                $possibleClasses[] = ['subject_id' => $subjectId, 'section_id' => $sectionId];
            }
        }

        if (empty($possibleClasses)) return;

        $classIndex = 0;
        $orderInDay = 0;
        // إذا لم تكن هناك أيام عمل محددة، لا ننشئ جدول
        if (empty($workDays)) {
            Log::warning("No work days specified for teacher {$teacher->id}");
            return;
        }

        foreach ($workDays as $day) {
            $currentTime = $dailyStartTime->copy();
            $maxClasses = $constraints['max_subjects_per_day'] ?? 3;

            for ($i = 0; $i < $maxClasses; $i++) {
                if ($classIndex >= count($possibleClasses)) break;

                $class = $possibleClasses[$classIndex];
                $endTime = $currentTime->copy()->addMinutes($classDuration);
                $workMinutes = $currentTime->diffInMinutes($endTime);

                TimetableEntry::create([
                    'schedulable_id' => $teacher->id,
                    'schedulable_type' => get_class($teacher),
                    'day_of_week' => $day,
                    'start_time' => $currentTime->format('H:i:s'),
                    'end_time' => $endTime->format('H:i:s'),
                    'subject_id' => $class['subject_id'],
                    'section_id' => $class['section_id'],
                    'work_type' => $workType,
                    'work_minutes' => $workMinutes,
                    'is_break' => false,
                    'order_in_day' => $orderInDay++,
                ]);

                $currentTime->addMinutes($classDuration);
                $classIndex++;
            }
        }
    }
}

