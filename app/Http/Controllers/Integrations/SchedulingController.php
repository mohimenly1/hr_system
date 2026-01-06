<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use App\Models\Employee;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\Section;
use App\Models\SchedulingConstraint;
use App\Models\ScheduleTemplate;
use App\Models\TimetableEntry;
use App\Services\TimetableGeneratorService;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Department;
use App\Models\CustomHour;
use App\Models\DefaultShiftSetting;
use Carbon\Carbon;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SchedulingController extends Controller
{
    /**
     * Format time to 12-hour format with Arabic period (صباحاً/مساءً)
     */
    private function formatTimeTo12HourArabic($time)
    {
        if (!$time) return null;

        $carbon = $time instanceof \DateTime
            ? Carbon::instance($time)
            : Carbon::parse($time);

        $hours = (int)$carbon->format('H');
        $minutes = $carbon->format('i');

        $period = $hours >= 12 ? 'مساءً' : 'صباحاً';

        $hours12 = $hours % 12;
        if ($hours12 === 0) $hours12 = 12;

        return sprintf('%d:%s %s', $hours12, $minutes, $period);
    }
    public function index(Request $request)
    {
        $personnelConstraints = null;
        $activePersonIdentifier = null;
        $selectedDepartmentId = $request->input('department_id') ? (int)$request->input('department_id') : null;

        // جلب الأقسام مع عدد الموظفين والمعلمين ومعلومات الجدول المفعل
        $departments = Department::withCount(['employees', 'teachers'])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function($department) {
                // Check if this department has an active shift setting and get its details
                $shiftSetting = DefaultShiftSetting::where('is_active', true)
                    ->where('department_id', $department->id)
                    ->first();

                $hasActiveShift = $shiftSetting !== null;

                // Convert work days to Arabic day names
                $dayNames = [
                    0 => 'الأحد',
                    1 => 'الإثنين',
                    2 => 'الثلاثاء',
                    3 => 'الأربعاء',
                    4 => 'الخميس',
                    5 => 'الجمعة',
                    6 => 'السبت',
                ];

                $workDaysNames = [];
                if ($shiftSetting && !empty($shiftSetting->work_days)) {
                    $workDaysNames = array_map(function($day) use ($dayNames) {
                        return $dayNames[$day] ?? "يوم $day";
                    }, $shiftSetting->work_days);
                }

                return [
                    'id' => $department->id,
                    'name' => $department->name,
                    'employees_count' => $department->employees_count,
                    'teachers_count' => $department->teachers_count,
                    'has_active_shift' => $hasActiveShift,
                    'shift_setting' => $shiftSetting ? [
                        'name' => $shiftSetting->name,
                        'start_time' => $this->formatTimeTo12HourArabic($shiftSetting->start_time),
                        'end_time' => $this->formatTimeTo12HourArabic($shiftSetting->end_time),
                        'work_days' => $shiftSetting->work_days,
                        'work_days_names' => $workDaysNames,
                        'work_days_count' => count($shiftSetting->work_days ?? []),
                        'hours_per_week' => $shiftSetting->hours_per_week,
                    ] : null,
                ];
            });

        // جلب الموظفين والمعلمين حسب القسم المحدد مع pagination
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search', '');

        $employeesQuery = Employee::with(['user', 'shiftAssignment.shift', 'department']);
        $teachersQuery = Teacher::with(['user', 'shiftAssignment.shift', 'department']);

        if ($selectedDepartmentId) {
            $employeesQuery->where('department_id', $selectedDepartmentId);
            $teachersQuery->where('department_id', $selectedDepartmentId);
        }

        // تطبيق البحث
        if ($search) {
            $employeesQuery->whereHas('user', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
            $teachersQuery->whereHas('user', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // دمج الموظفين والمعلمين
        $allPersonnel = collect();

        // جلب إعدادات الدوام الافتراضية للقسم المحدد والمؤسسة
        $departmentShiftSetting = null;
        $organizationShiftSetting = null;

        if ($selectedDepartmentId) {
            $departmentShiftSetting = DefaultShiftSetting::where('is_active', true)
                ->where('department_id', $selectedDepartmentId)
                ->first();
        }

        if (!$departmentShiftSetting) {
            $organizationShiftSetting = DefaultShiftSetting::where('is_active', true)
                ->whereNull('department_id')
                ->first();
        }

        $activeShiftSettingForDisplay = $departmentShiftSetting ?? $organizationShiftSetting;

        // جلب الموظفين
        $employees = $employeesQuery->get(['id', 'user_id', 'department_id', 'middle_name', 'last_name'])->map(function($emp) use ($activeShiftSettingForDisplay) {
            // بناء الاسم الكامل
            $fullName = $emp->user->name;
            if ($emp->middle_name) {
                $fullName .= ' ' . $emp->middle_name;
            }
            if ($emp->last_name) {
                $fullName .= ' ' . $emp->last_name;
            }

            // تحديد الدوام المعروض: إعدادات القسم/المؤسسة بدلاً من shift assignment
            $displayShift = null;
            if ($activeShiftSettingForDisplay) {
                $startTime = $this->formatTimeTo12HourArabic($activeShiftSettingForDisplay->start_time);
                $endTime = $this->formatTimeTo12HourArabic($activeShiftSettingForDisplay->end_time);
                $displayShift = $activeShiftSettingForDisplay->name . ' (' . $startTime . ' - ' . $endTime . ')';
            }

            return [
                'id' => $emp->id,
                'user_id' => $emp->user_id,
                'name' => $emp->user->name, // الاسم الأول فقط (للتوافق مع الكود القديم)
                'full_name' => trim($fullName), // الاسم الكامل
                'type' => 'Employee',
                'type_label' => 'موظف',
                'department' => $emp->department ? $emp->department->name : null,
                'shift' => $displayShift, // استخدام إعدادات المؤسسة/القسم بدلاً من shift assignment
                'shift_id' => null, // لا نعرض shift_id من shift assignment
                'shift_source' => $activeShiftSettingForDisplay ? ($activeShiftSettingForDisplay->department_id ? 'department' : 'organization') : null,
            ];
        });

        // جلب المعلمين
        $teachers = $teachersQuery->get(['id', 'user_id', 'department_id', 'middle_name', 'last_name'])->map(function($tea) use ($activeShiftSettingForDisplay) {
            // بناء الاسم الكامل
            $fullName = $tea->user->name;
            if ($tea->middle_name) {
                $fullName .= ' ' . $tea->middle_name;
            }
            if ($tea->last_name) {
                $fullName .= ' ' . $tea->last_name;
            }

            // تحديد الدوام المعروض: إعدادات القسم/المؤسسة بدلاً من shift assignment
            $displayShift = null;
            if ($activeShiftSettingForDisplay) {
                $startTime = $this->formatTimeTo12HourArabic($activeShiftSettingForDisplay->start_time);
                $endTime = $this->formatTimeTo12HourArabic($activeShiftSettingForDisplay->end_time);
                $displayShift = $activeShiftSettingForDisplay->name . ' (' . $startTime . ' - ' . $endTime . ')';
            }

            return [
                'id' => $tea->id,
                'user_id' => $tea->user_id,
                'name' => $tea->user->name, // الاسم الأول فقط (للتوافق مع الكود القديم)
                'full_name' => trim($fullName), // الاسم الكامل
                'type' => 'Teacher',
                'type_label' => 'معلم',
                'department' => $tea->department ? $tea->department->name : null,
                'shift' => $displayShift, // استخدام إعدادات المؤسسة/القسم بدلاً من shift assignment
                'shift_id' => null, // لا نعرض shift_id من shift assignment
                'shift_source' => $activeShiftSettingForDisplay ? ($activeShiftSettingForDisplay->department_id ? 'department' : 'organization') : null,
            ];
        });

        $allPersonnel = $employees->concat($teachers)->sortBy('full_name');

        // Pagination
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $perPage = (int)$perPage;
        $total = $allPersonnel->count();
        $offset = ($currentPage - 1) * $perPage;
        $paginatedPersonnel = $allPersonnel->slice($offset, $perPage)->values();

        $personnelPaginator = new LengthAwarePaginator(
            $paginatedPersonnel,
            $total,
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $personDepartmentId = null;
        $personnelConstraints = null;
        $activePersonIdentifier = null;

        if ($request->has('person_id') && $request->has('person_type')) {
            $personId = $request->person_id;
            $personType = $request->person_type;

            // جلب الموظف/المعلم لمعرفة القسم الذي ينتمي إليه
            $personModel = null;
            if ($personType === 'App\\Models\\Employee') {
                $personModel = Employee::find($personId);
            } elseif ($personType === 'App\\Models\\Teacher') {
                $personModel = Teacher::find($personId);
            }

            if ($personModel && $personModel->department_id) {
                $personDepartmentId = $personModel->department_id;
            }

            $constraints = SchedulingConstraint::where('schedulable_id', $personId)
                ->where('schedulable_type', $personType)
                ->get();

            $personnelConstraints = $constraints->pluck('value', 'constraint_type')->toArray();

            // جلب الوردية المحددة من ShiftAssignment
            $shiftAssignment = ShiftAssignment::where('shiftable_id', $personId)
                ->where('shiftable_type', $personType)
                ->with('shift')
                ->first();

            if ($shiftAssignment && $shiftAssignment->shift) {
                $personnelConstraints['assigned_shift_assignment'] = [
                    'id' => $shiftAssignment->id,
                    'shift_id' => $shiftAssignment->shift_id,
                    'shift_name' => $shiftAssignment->shift->name,
                    'start_time' => $this->formatTimeTo12HourArabic($shiftAssignment->shift->start_time),
                    'end_time' => $this->formatTimeTo12HourArabic($shiftAssignment->shift->end_time),
                ];
            }

            $modelName = last(explode('\\', $personType));
            $activePersonIdentifier = "{$modelName}-{$personId}";
        }

        // جلب الساعات المخصصة إذا كان هناك موظف/معلم محدد
        $customHours = null;
        if ($request->has('person_id') && $request->has('person_type')) {
            $customHours = CustomHour::where('hourly_id', $request->person_id)
                ->where('hourly_type', $request->person_type)
                ->get();
        }

        // جلب إعدادات الدوام الافتراضي
        // الأولوية: 1) إعدادات قسم الموظف/المعلم 2) إعدادات القسم المحدد في URL 3) إعدادات المؤسسة العامة
        $defaultShiftSetting = null;
        $personDepartmentShiftSetting = null;
        $selectedDepartmentShiftSetting = null;
        $organizationShiftSetting = null;

        // 1. محاولة جلب إعدادات قسم الموظف/المعلم
        if ($personDepartmentId) {
            $personDepartmentShiftSetting = DefaultShiftSetting::with('department')
                ->where('is_active', true)
                ->where('department_id', $personDepartmentId)
                ->first();
        }

        // 2. محاولة جلب إعدادات القسم المحدد في URL (إذا كان مختلفاً عن قسم الموظف)
        if ($selectedDepartmentId && $selectedDepartmentId != $personDepartmentId) {
            $selectedDepartmentShiftSetting = DefaultShiftSetting::with('department')
                ->where('is_active', true)
                ->where('department_id', $selectedDepartmentId)
                ->first();
        }

        // 3. جلب إعدادات المؤسسة العامة
        $organizationShiftSetting = DefaultShiftSetting::with('department')
            ->where('is_active', true)
            ->whereNull('department_id')
            ->first();

        // تحديد الإعدادات النشطة حسب الأولوية
        $defaultShiftSetting = $personDepartmentShiftSetting
            ?? $selectedDepartmentShiftSetting
            ?? $organizationShiftSetting;

        // جلب جميع إعدادات الدوام للأقسام
        $departmentShiftSettings = DefaultShiftSetting::with('department')
            ->where('is_active', true)
            ->whereNotNull('department_id')
            ->get();

        // جلب الوقت الإضافي إذا كان هناك موظف/معلم محدد
        $overtimeEntries = [];
        if ($request->has('person_id') && $request->has('person_type')) {
            $overtimeEntries = DB::table('overtime_entries')
                ->where('schedulable_id', $request->person_id)
                ->where('schedulable_type', $request->person_type)
                ->orderBy('date', 'asc')
                ->get()
                ->map(function($entry) {
                    // Ensure date is formatted as YYYY-MM-DD string
                    return [
                        'id' => $entry->id,
                        'schedulable_id' => $entry->schedulable_id,
                        'schedulable_type' => $entry->schedulable_type,
                        'date' => is_string($entry->date) ? $entry->date : date('Y-m-d', strtotime($entry->date)),
                        'start_time' => $entry->start_time,
                        'end_time' => $entry->end_time,
                        'minutes' => $entry->minutes,
                        'notes' => $entry->notes,
                        'status' => $entry->status ?? 'pending',
                    ];
                })
                ->toArray();
        }

        // جلب الإجازات إذا كان هناك موظف/معلم محدد
        $leaveEntries = [];
        if ($request->has('person_id') && $request->has('person_type')) {
            $personType = $request->person_type;
            $personId = $request->person_id;

            // جلب الإجازات المعتمدة فقط (approved)
            $leaves = \App\Models\Leave::with('leaveType')
                ->where('leavable_id', $personId)
                ->where('leavable_type', $personType)
                ->where('status', 'approved') // فقط الإجازات المعتمدة
                ->orderBy('start_date', 'asc')
                ->get();

            $leaveEntries = $leaves->map(function($leave) {
                return [
                    'id' => $leave->id,
                    'leavable_id' => $leave->leavable_id,
                    'leavable_type' => $leave->leavable_type,
                    'start_date' => $leave->start_date->format('Y-m-d'),
                    'end_date' => $leave->end_date->format('Y-m-d'),
                    'leave_type' => $leave->leaveType ? [
                        'id' => $leave->leaveType->id,
                        'name' => $leave->leaveType->name,
                        'is_paid' => $leave->leaveType->is_paid,
                    ] : null,
                    'reason' => $leave->reason,
                    'status' => $leave->status,
                ];
            })->toArray();
        }

        // Get timetables with all relationships
        $timetables = TimetableEntry::with(['schedulable.user', 'subject', 'section.grade', 'shift'])
            ->get()
            ->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'schedulable_id' => $entry->schedulable_id,
                    'schedulable_type' => $entry->schedulable_type,
                    'day_of_week' => $entry->day_of_week,
                    'start_time' => $entry->start_time,
                    'end_time' => $entry->end_time,
                    'shift_id' => $entry->shift_id,
                    'work_type' => $entry->work_type,
                    'subject_id' => $entry->subject_id,
                    'section_id' => $entry->section_id,
                    'is_break' => $entry->is_break,
                    'entry_type' => $entry->entry_type ?? ($entry->is_break ? 'break' : 'work'),
                    'title' => $entry->title,
                    'order_in_day' => $entry->order_in_day,
                    'work_minutes' => $entry->work_minutes,
                    'shift' => $entry->shift,
                    'subject' => $entry->subject,
                    'section' => $entry->section,
                    'schedulable' => $entry->schedulable,
                ];
            })
            ->values() // Ensure it's a proper array, not a collection
            ->toArray(); // Convert to array for Inertia

        // Check if organization has an active shift setting
        $hasOrganizationActiveShift = DefaultShiftSetting::where('is_active', true)
            ->whereNull('department_id')
            ->exists();

        return Inertia::render('Integrations/Scheduling/Index', [
            'departments' => $departments,
            'selectedDepartmentId' => $selectedDepartmentId,
            'personnel' => $personnelPaginator,
            'search' => $search,
            'subjects' => Subject::all(['id', 'name']),
            'sections' => Section::with('grade:id,name')->get(['id', 'name', 'grade_id']),
            'templates' => ScheduleTemplate::with('constraints')->where('is_active', true)->get(),
            'personnelConstraints' => $personnelConstraints,
            'timetables' => $timetables,
            'activePersonIdentifier' => $activePersonIdentifier,
            'shifts' => Shift::all(),
            'customHours' => $customHours,
            'overtimeEntries' => $overtimeEntries,
            'leaveEntries' => $leaveEntries, // إضافة الإجازات
            'defaultShiftSetting' => $defaultShiftSetting,
            'personDepartmentShiftSetting' => $personDepartmentShiftSetting,
            'organizationShiftSetting' => $organizationShiftSetting,
            'personDepartmentId' => $personDepartmentId,
            'departmentShiftSettings' => $departmentShiftSettings,
            'hasOrganizationActiveShift' => $hasOrganizationActiveShift, // مؤشر وجود جدول للمؤسسة
        ]);
    }

    public function storeConstraints(Request $request)
    {
        $data = $request->except(['schedulable_id', 'schedulable_type']);
        $schedulableId = $request->input('schedulable_id');
        $schedulableType = $request->input('schedulable_type');

        // Delete all existing constraints for this person
        SchedulingConstraint::where('schedulable_id', $schedulableId)
            ->where('schedulable_type', $schedulableType)
            ->delete();

        // Create new constraints only for non-null values
        foreach ($data as $key => $value) {
            // For assigned_shift_id, explicitly check if it's null or empty string to remove it
            if ($key === 'assigned_shift_id' && ($value === null || $value === '' || $value === 'null')) {
                // Don't create constraint for assigned_shift_id if it's null/empty
                continue;
            }

            // Ensure value is not null before creating constraint
            if ($value !== null && $value !== '' && $value !== 'null') {
                SchedulingConstraint::create([
                    'schedulable_id' => $schedulableId,
                    'schedulable_type' => $schedulableType,
                    'constraint_type' => $key,
                    'value' => $value,
                ]);
            }
        }

        return redirect()->back()->with('success', 'تم حفظ القيود بنجاح.');
    }

    /**
     * Delete all individual constraints for a person
     */
    public function deleteConstraints(Request $request)
    {
        $request->validate([
            'schedulable_id' => 'required|integer',
            'schedulable_type' => 'required|string',
        ]);

        $schedulableId = $request->input('schedulable_id');
        $schedulableType = $request->input('schedulable_type');

        SchedulingConstraint::where('schedulable_id', $schedulableId)
            ->where('schedulable_type', $schedulableType)
            ->delete();

        return redirect()->back()->with('success', 'تم حذف القيود الفردية بنجاح.');
    }

    // --- Template Management Methods ---

    public function storeTemplate(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:schedule_templates,name',
            'type' => 'required|in:teacher,employee,general',
            'is_active' => 'required|boolean',
        ]);
        ScheduleTemplate::create($request->all());
        return redirect()->back()->with('success', 'تم إنشاء القالب بنجاح.');
    }

    public function updateTemplate(Request $request, ScheduleTemplate $template)
    {
        $request->validate([
            'name' => 'required|string|unique:schedule_templates,name,' . $template->id,
            'type' => 'required|in:teacher,employee,general',
            'is_active' => 'required|boolean',
        ]);
        $template->update($request->all());
        return redirect()->back()->with('success', 'تم تحديث القالب بنجاح.');
    }

    public function storeTemplateConstraints(Request $request, ScheduleTemplate $template)
    {
        $template->constraints()->delete();
        $constraintsData = $request->all();
        foreach ($constraintsData as $key => $value) {
            $template->constraints()->create([
                'constraint_type' => $key,
                'value' => $value,
            ]);
        }
        return redirect()->route('hr.integrations.scheduling.index')->with('success', 'تم حفظ قيود القالب بنجاح.');
    }

    public function generateTimetable(Request $request, TimetableGeneratorService $generator)
    {
        try {
            $redirectParams = [];

            // If person_identifier is provided, generate for that person only
            if ($request->has('person_identifier') && $request->person_identifier) {
                 list($type, $id) = explode('-', $request->person_identifier);
                $personType = "App\\Models\\{$type}";
                $personId = $id;

                $result = $generator->generateForPerson($personId, $personType);

                $redirectParams['person_type'] = $personType;
                $redirectParams['person_id'] = $personId;
            } else {
                // Generate for all personnel (old behavior)
                $result = $generator->generate();
            }

            if (isset($result['success']) && !$result['success']) {
                return redirect()->back()->with('error', $result['message']);
            }

            return redirect()->route('hr.integrations.scheduling.index', $redirectParams)
                             ->with('success', $result['message']);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'حدث خطأ أثناء عملية الجدولة: ' . $e->getMessage());
        }
    }

    /**
     * Store or update a timetable entry via drag and drop.
     */
    public function storeTimetableEntry(Request $request)
    {
        \Log::info('=== STORE TIMETABLE ENTRY - START ===');
        \Log::info('Raw request data:', $request->all());

        // Handle time format - accept both H:i and H:i:s
        $startTime = $request->input('start_time');
        $endTime = $request->input('end_time');

        \Log::info('Raw times:', ['start_time' => $startTime, 'end_time' => $endTime]);

        // Convert HH:mm to HH:mm:ss if needed
        if ($startTime && strlen($startTime) === 5 && substr_count($startTime, ':') === 1) {
            $startTime .= ':00';
        }
        if ($endTime && strlen($endTime) === 5 && substr_count($endTime, ':') === 1) {
            $endTime .= ':00';
        }

        \Log::info('Converted times:', ['start_time' => $startTime, 'end_time' => $endTime]);

        // Replace request values
        $request->merge([
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);

        $request->validate([
            'schedulable_id' => 'required|integer',
            'schedulable_type' => 'required|string',
            'day_of_week' => 'required|integer|min:0|max:6',
            'start_time' => 'required|date_format:H:i:s',
            'end_time' => 'required|date_format:H:i:s|after:start_time',
            'shift_id' => 'nullable|exists:shifts,id',
            'work_type' => 'nullable|in:monthly_full,monthly_partial,hourly',
            'subject_id' => 'nullable|exists:subjects,id',
            'section_id' => 'nullable|exists:sections,id',
            'is_break' => 'boolean',
            'entry_type' => 'nullable|string',
            'title' => 'nullable|string',
            'order_in_day' => 'integer|min:0',
        ]);

        $entry = TimetableEntry::create([
            'schedulable_id' => $request->schedulable_id,
            'schedulable_type' => $request->schedulable_type,
            'day_of_week' => $request->day_of_week,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'shift_id' => $request->shift_id,
            'work_type' => $request->work_type,
            'subject_id' => $request->subject_id,
            'section_id' => $request->section_id,
            'is_break' => $request->is_break ?? false,
            'entry_type' => $request->entry_type ?? ($request->is_break ? 'break' : 'work'),
            'title' => $request->title,
            'order_in_day' => $request->order_in_day ?? 0,
        ]);

        // Calculate work minutes
        $start = \Carbon\Carbon::parse($entry->start_time);
        $end = \Carbon\Carbon::parse($entry->end_time);
        if ($end->lt($start)) {
            $end->addDay();
        }
        $entry->work_minutes = $start->diffInMinutes($end);
        $entry->save();

        \Log::info('=== STORE TIMETABLE ENTRY - SUCCESS ===');
        \Log::info('Created entry:', $entry->toArray());

        // Return Inertia redirect with fresh timetables data
        return redirect()->back()->with('success', 'تم إضافة إدخال الجدول بنجاح');
    }

    /**
     * Update a timetable entry (for drag and drop repositioning).
     */
    public function updateTimetableEntry(Request $request, TimetableEntry $timetableEntry)
    {
        \Log::info('=== UPDATE TIMETABLE ENTRY - START ===');
        \Log::info('Entry ID:', ['id' => $timetableEntry->id]);
        \Log::info('Request data:', $request->all());

        $request->validate([
            'day_of_week' => 'sometimes|integer|min:0|max:6',
            'start_time' => 'sometimes|date_format:H:i:s',
            'end_time' => 'sometimes|date_format:H:i:s',
            'shift_id' => 'nullable|exists:shifts,id',
            'work_type' => 'nullable|in:monthly_full,monthly_partial,hourly',
            'subject_id' => 'nullable|exists:subjects,id',
            'section_id' => 'nullable|exists:sections,id',
            'is_break' => 'boolean',
            'entry_type' => 'nullable|string',
            'title' => 'nullable|string',
            'order_in_day' => 'integer|min:0',
        ]);

        // Handle time format - accept both H:i and H:i:s
        $startTime = $request->start_time;
        $endTime = $request->end_time;

        if ($startTime && strlen($startTime) === 5) {
            $startTime .= ':00';
        }
        if ($endTime && strlen($endTime) === 5) {
            $endTime .= ':00';
        }

        // Validate end_time is after start_time
        if ($startTime && $endTime) {
            $start = \Carbon\Carbon::parse($startTime);
            $end = \Carbon\Carbon::parse($endTime);
            if ($end->lte($start)) {
                return redirect()->back()->withErrors([
                    'end_time' => 'وقت النهاية يجب أن يكون بعد وقت البداية',
                ]);
            }
        }

        $updateData = $request->only([
            'day_of_week', 'shift_id', 'work_type',
            'subject_id', 'section_id', 'is_break', 'entry_type', 'title', 'order_in_day'
        ]);

        // Ensure entry_type is set if not provided
        if (!isset($updateData['entry_type']) && isset($updateData['is_break'])) {
            $updateData['entry_type'] = $updateData['is_break'] ? 'break' : 'work';
        }

        if ($startTime) {
            $updateData['start_time'] = $startTime;
        }
        if ($endTime) {
            $updateData['end_time'] = $endTime;
        }

        $timetableEntry->fill($updateData);

        // Recalculate work minutes if time changed
        if ($startTime || $endTime) {
            $start = \Carbon\Carbon::parse($timetableEntry->start_time);
            $end = \Carbon\Carbon::parse($timetableEntry->end_time);
            if ($end->lt($start)) {
                $end->addDay();
            }
            $timetableEntry->work_minutes = $start->diffInMinutes($end);
        }

        $timetableEntry->save();

        \Log::info('=== UPDATE TIMETABLE ENTRY - SUCCESS ===');
        \Log::info('Updated entry:', $timetableEntry->toArray());

        // Return Inertia redirect instead of JSON
        return redirect()->back()->with('success', 'تم تحديث إدخال الجدول بنجاح');
    }

    /**
     * Delete a timetable entry.
     */
    public function deleteTimetableEntry(TimetableEntry $timetableEntry)
    {
        $timetableEntry->delete();

        // Return Inertia redirect instead of JSON
        return redirect()->back()->with('success', 'تم حذف الإدخال بنجاح.');
    }

    /**
     * Bulk update timetable entries (for drag and drop operations).
     */
    public function bulkUpdateTimetableEntries(Request $request)
    {
        $request->validate([
            'entries' => 'required|array',
            'entries.*.id' => 'required|exists:timetable_entries,id',
            'entries.*.day_of_week' => 'sometimes|integer|min:0|max:6',
            'entries.*.start_time' => 'sometimes|date_format:H:i',
            'entries.*.end_time' => 'sometimes|date_format:H:i',
            'entries.*.order_in_day' => 'sometimes|integer|min:0',
        ]);

        $updated = [];
        foreach ($request->entries as $entryData) {
            $entry = TimetableEntry::find($entryData['id']);
            if ($entry) {
                $entry->fill($entryData);

                // Recalculate work minutes if time changed
                if (isset($entryData['start_time']) || isset($entryData['end_time'])) {
                    $start = \Carbon\Carbon::parse($entry->start_time);
                    $end = \Carbon\Carbon::parse($entry->end_time);
                    if ($end->lt($start)) {
                        $end->addDay();
                    }
                    $entry->work_minutes = $start->diffInMinutes($end);
                }

                $entry->save();
                $updated[] = $entry->load('shift', 'subject', 'section.grade');
            }
        }

        return response()->json([
            'success' => true,
            'entries' => $updated,
        ]);
    }

    /**
     * Store or update custom hours for hourly employees/teachers.
     */
    public function storeCustomHours(Request $request)
    {
        $request->validate([
            'hourly_id' => 'required|integer',
            'hourly_type' => 'required|string',
            'hours' => 'required|array',
            'hours.*.day_of_week' => 'required|integer|min:0|max:6',
            'hours.*.hours' => 'required|numeric|min:0|max:24',
            'hours.*.start_time' => 'nullable|date_format:H:i',
            'hours.*.end_time' => 'nullable|date_format:H:i|after:hours.*.start_time',
            'hours.*.notes' => 'nullable|string|max:500',
        ]);

        $hourlyId = $request->hourly_id;
        $hourlyType = $request->hourly_type;

        // حذف الساعات القديمة
        CustomHour::where('hourly_id', $hourlyId)
            ->where('hourly_type', $hourlyType)
            ->delete();

        // إضافة الساعات الجديدة
        foreach ($request->hours as $hourData) {
            if ($hourData['hours'] > 0) {
                // تحويل start_time و end_time إلى تنسيق H:i:s إذا لزم الأمر
                $startTime = $hourData['start_time'] ?? null;
                if ($startTime && strlen($startTime) === 5 && substr_count($startTime, ':') === 1) {
                    $startTime = $startTime . ':00';
                }

                $endTime = $hourData['end_time'] ?? null;
                if ($endTime && strlen($endTime) === 5 && substr_count($endTime, ':') === 1) {
                    $endTime = $endTime . ':00';
                }

                CustomHour::create([
                    'hourly_id' => $hourlyId,
                    'hourly_type' => $hourlyType,
                    'day_of_week' => $hourData['day_of_week'],
                    'hours' => $hourData['hours'],
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'notes' => $hourData['notes'] ?? null,
                ]);
            }
        }

        return redirect()->back()->with('success', 'تم حفظ الساعات المخصصة بنجاح.');
    }

    /**
     * Get custom hours for a specific employee/teacher.
     */
    public function getCustomHours(Request $request)
    {
        $request->validate([
            'hourly_id' => 'required|integer',
            'hourly_type' => 'required|string',
        ]);

        $customHours = CustomHour::where('hourly_id', $request->hourly_id)
            ->where('hourly_type', $request->hourly_type)
            ->get();

        return response()->json([
            'success' => true,
            'customHours' => $customHours,
        ]);
    }

    /**
     * Get default shift settings
     */
    public function getDefaultShiftSettings()
    {
        $settings = DefaultShiftSetting::getActive();
        return response()->json([
            'success' => true,
            'settings' => $settings,
        ]);
    }

    /**
     * Store or update default shift settings
     */
    public function storeDefaultShiftSettings(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'work_days' => 'required|array|min:1',
            'work_days.*' => 'integer|min:0|max:6',
            'description' => 'nullable|string',
        ]);

        // Deactivate existing settings for the same department (or organization if department_id is null)
        // This allows each department to have its own active shift setting
        if ($request->has('department_id') && $request->department_id) {
            // Deactivate only settings for this specific department
            DefaultShiftSetting::where('is_active', true)
                ->where('department_id', $request->department_id)
                ->update(['is_active' => false]);
        } else {
            // If creating organization-wide setting, deactivate only other organization-wide settings
            // Keep department-specific settings active
            DefaultShiftSetting::where('is_active', true)
                ->whereNull('department_id')
                ->update(['is_active' => false]);
        }

        // Calculate hours
        $start = \Carbon\Carbon::parse($request->start_time);
        $end = \Carbon\Carbon::parse($request->end_time);
        if ($end->lt($start)) {
            $end->addDay();
        }
        $hoursPerDay = $start->diffInHours($end) + ($start->diffInMinutes($end) % 60) / 60;
        $workDaysCount = count($request->work_days);
        $hoursPerWeek = round($hoursPerDay * $workDaysCount, 2);
        $hoursPerMonth = round($hoursPerWeek * 4.33, 2);

        $settings = DefaultShiftSetting::create([
            'name' => $request->name,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'work_days' => $request->work_days,
            'hours_per_week' => $hoursPerWeek,
            'hours_per_month' => $hoursPerMonth,
            'work_days_per_week' => $workDaysCount,
            'is_active' => true,
            'description' => $request->description,
            'department_id' => $request->department_id,
        ]);

        return redirect()->back()->with('success', 'تم حفظ إعدادات الدوام الافتراضي بنجاح.');
    }

    /**
     * Update default shift settings
     */
    public function updateDefaultShiftSettings(Request $request, DefaultShiftSetting $defaultShiftSetting)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'work_days' => 'required|array|min:1',
            'work_days.*' => 'integer|min:0|max:6',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // Calculate hours
        $start = \Carbon\Carbon::parse($request->start_time);
        $end = \Carbon\Carbon::parse($request->end_time);
        if ($end->lt($start)) {
            $end->addDay();
        }
        $hoursPerDay = $start->diffInHours($end) + ($start->diffInMinutes($end) % 60) / 60;
        $workDaysCount = count($request->work_days);
        $hoursPerWeek = round($hoursPerDay * $workDaysCount, 2);
        $hoursPerMonth = round($hoursPerWeek * 4.33, 2);

        // If activating this setting, deactivate only other settings for the same department/organization
        // This allows each department to have its own active shift setting
        if ($request->is_active) {
            if ($defaultShiftSetting->department_id) {
                // Deactivate only other settings for this specific department
                DefaultShiftSetting::where('id', '!=', $defaultShiftSetting->id)
                    ->where('is_active', true)
                    ->where('department_id', $defaultShiftSetting->department_id)
                    ->update(['is_active' => false]);
            } else {
                // Deactivate only other organization-wide settings
                // Keep department-specific settings active
                DefaultShiftSetting::where('id', '!=', $defaultShiftSetting->id)
                    ->where('is_active', true)
                    ->whereNull('department_id')
                    ->update(['is_active' => false]);
            }
        }

        $defaultShiftSetting->update([
            'name' => $request->name,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'work_days' => $request->work_days,
            'hours_per_week' => $hoursPerWeek,
            'hours_per_month' => $hoursPerMonth,
            'work_days_per_week' => $workDaysCount,
            'is_active' => $request->is_active ?? $defaultShiftSetting->is_active,
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'تم تحديث إعدادات الدوام الافتراضي بنجاح.');
    }

    /**
     * Delete default shift settings for a department or organization
     */
    public function deleteDefaultShiftSettings(Request $request)
    {
        $request->validate([
            'department_id' => 'nullable|integer|exists:departments,id',
        ]);

        if ($request->has('department_id') && $request->department_id) {
            // Delete shift settings for a specific department
            $deleted = DefaultShiftSetting::where('department_id', $request->department_id)
                ->delete();

            if ($deleted > 0) {
                return redirect()->back()->with('success', 'تم حذف جدول الدوام الخاص بالقسم بنجاح.');
            } else {
                return redirect()->back()->with('error', 'لم يتم العثور على جدول دوام لهذا القسم.');
            }
        } else {
            // Delete organization-wide shift settings
            $deleted = DefaultShiftSetting::whereNull('department_id')
                ->delete();

            if ($deleted > 0) {
                return redirect()->back()->with('success', 'تم حذف جدول الدوام الخاص بالمؤسسة بنجاح.');
            } else {
                return redirect()->back()->with('error', 'لم يتم العثور على جدول دوام للمؤسسة.');
            }
        }
    }

    /**
     * Store overtime entry
     */
    public function storeOvertime(Request $request)
    {
        $request->validate([
            'schedulable_id' => 'required|integer',
            'schedulable_type' => 'required|string',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'notes' => 'nullable|string|max:1000',
        ]);

        $schedulableId = $request->schedulable_id;
        $schedulableType = $request->schedulable_type;

        // Calculate minutes
        $start = \Carbon\Carbon::parse($request->date . ' ' . $request->start_time);
        $end = \Carbon\Carbon::parse($request->date . ' ' . $request->end_time);
        if ($end->lt($start)) {
            $end->addDay();
        }
        $minutes = $start->diffInMinutes($end);

        // Check if overtime entry already exists for this date
        $existing = \DB::table('overtime_entries')
            ->where('schedulable_id', $schedulableId)
            ->where('schedulable_type', $schedulableType)
            ->where('date', $request->date)
            ->first();

        if ($existing) {
            // Update existing entry
            \DB::table('overtime_entries')
                ->where('id', $existing->id)
                ->update([
                    'start_time' => $request->start_time,
                    'end_time' => $request->end_time,
                    'minutes' => $minutes,
                    'notes' => $request->notes,
                    'updated_at' => now(),
                ]);
        } else {
            // Create new entry
            \DB::table('overtime_entries')->insert([
                'schedulable_id' => $schedulableId,
                'schedulable_type' => $schedulableType,
                'date' => $request->date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'minutes' => $minutes,
                'notes' => $request->notes,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect()->back()->with('success', 'تم إضافة الوقت الإضافي بنجاح.');
    }

    /**
     * Get overtime entries
     */
    public function getOvertime(Request $request)
    {
        $request->validate([
            'schedulable_id' => 'required|integer',
            'schedulable_type' => 'required|string',
            'month' => 'nullable|date_format:Y-m',
        ]);

        $query = DB::table('overtime_entries')
            ->where('schedulable_id', $request->schedulable_id)
            ->where('schedulable_type', $request->schedulable_type);

        if ($request->has('month')) {
            $query->whereYear('date', substr($request->month, 0, 4))
                  ->whereMonth('date', substr($request->month, 5, 2));
        }

        $overtimeEntries = $query->get();

        return response()->json([
            'success' => true,
            'overtimeEntries' => $overtimeEntries,
        ]);
    }

    /**
     * Update overtime entry
     */
    public function updateOvertime(Request $request, $id)
    {
        \Log::info('=== UPDATE OVERTIME - START ===');
        \Log::info('Overtime ID:', ['id' => $id]);
        \Log::info('Request data:', $request->all());

        $request->validate([
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'notes' => 'nullable|string|max:1000',
        ]);

        $overtimeEntry = \DB::table('overtime_entries')->where('id', $id)->first();

        if (!$overtimeEntry) {
            \Log::error('Overtime entry not found:', ['id' => $id]);
            return redirect()->back()->with('error', 'السجل غير موجود.');
        }

        \Log::info('Existing entry:', [
            'id' => $overtimeEntry->id,
            'date' => $overtimeEntry->date,
            'start_time' => $overtimeEntry->start_time,
            'end_time' => $overtimeEntry->end_time,
        ]);

        // Calculate minutes
        $start = \Carbon\Carbon::parse($overtimeEntry->date . ' ' . $request->start_time);
        $end = \Carbon\Carbon::parse($overtimeEntry->date . ' ' . $request->end_time);
        if ($end->lt($start)) {
            $end->addDay();
        }
        $minutes = $start->diffInMinutes($end);

        \Log::info('Calculated minutes:', ['minutes' => $minutes]);

        \DB::table('overtime_entries')
            ->where('id', $id)
            ->update([
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'minutes' => $minutes,
                'notes' => $request->notes,
                'updated_at' => now(),
            ]);

        \Log::info('=== UPDATE OVERTIME - END ===');

        return redirect()->back()->with('success', 'تم تحديث الوقت الإضافي بنجاح.');
    }

    /**
     * Delete overtime entry
     */
    public function deleteOvertime($id)
    {
        \Log::info('=== DELETE OVERTIME - START ===');
        \Log::info('Overtime ID:', ['id' => $id]);

        $overtimeEntry = \DB::table('overtime_entries')->where('id', $id)->first();

        if (!$overtimeEntry) {
            \Log::error('Overtime entry not found:', ['id' => $id]);
            return redirect()->back()->with('error', 'السجل غير موجود.');
        }

        \Log::info('Deleting entry:', [
            'id' => $overtimeEntry->id,
            'date' => $overtimeEntry->date,
            'schedulable_id' => $overtimeEntry->schedulable_id,
            'schedulable_type' => $overtimeEntry->schedulable_type,
        ]);

        \DB::table('overtime_entries')->where('id', $id)->delete();

        \Log::info('=== DELETE OVERTIME - END ===');

        return redirect()->back()->with('success', 'تم حذف الوقت الإضافي بنجاح.');
    }
}

