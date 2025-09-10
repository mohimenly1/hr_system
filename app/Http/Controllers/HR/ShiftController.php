<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use App\Services\FingerprintService;
use App\Models\DeviceUser;

class ShiftController extends Controller
{
    public function index(Request $request)
    {
        // جلب قائمة المستخدمين المزامنة من قاعدة البيانات
        $deviceUsers = DeviceUser::all();

        // 1. استقبال الفلاتر من الطلب
        $filters = $request->only(['search', 'filter_type']);
        $search = $filters['search'] ?? null;
        $filterType = $filters['filter_type'] ?? 'all';

        // 2. بناء الاستعلامات الأساسية
        $employeeQuery = Employee::query()->with(['user', 'shiftAssignment.shift']);
        $teacherQuery = Teacher::query()->with(['user', 'shiftAssignment.shift']);

        // 3. تطبيق فلتر البحث إذا كان موجوداً
        if ($search) {
            $employeeQuery->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            $teacherQuery->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        // 4. جلب البيانات بناءً على فلتر النوع
        $personnel = new Collection();
        if ($filterType === 'employees' || $filterType === 'all') {
            $personnel = $personnel->merge(
                $employeeQuery->get()->map->setPersonnelType('موظف', 'App\\Models\\Employee')
            );
        }
        if ($filterType === 'teachers' || $filterType === 'all') {
            $personnel = $personnel->merge(
                $teacherQuery->get()->map->setPersonnelType('معلم', 'App\\Models\\Teacher')
            );
        }
        
        // 5. ترتيب النتائج المدمجة
        $personnel = $personnel->sortBy('user.name')->values();

        // 6. إنشاء ترقيم الصفحات يدوياً
        $perPage = 15;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentPageItems = $personnel->slice(($currentPage - 1) * $perPage, $perPage)->all();
        
        $paginatedPersonnel = new LengthAwarePaginator($currentPageItems, count($personnel), $perPage, $currentPage, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
        
        return Inertia::render('HR/Shifts/Index', [
            'shifts' => Shift::all(),
            'personnel' => $paginatedPersonnel,
            'filters' => $filters,
            'deviceUsers' => $deviceUsers,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'grace_period_minutes' => 'required|integer|min:0',
        ]);

        Shift::create($request->all());

        return redirect()->back()->with('success', 'تم إنشاء الدوام بنجاح.');
    }

    public function update(Request $request, Shift $shift)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'grace_period_minutes' => 'required|integer|min:0',
        ]);

        $shift->update($request->all());

        return redirect()->back()->with('success', 'تم تحديث الدوام بنجاح.');
    }

    public function destroy(Shift $shift)
    {
        $shift->delete();
        return redirect()->back()->with('success', 'تم حذف الدوام بنجاح.');
    }
}
