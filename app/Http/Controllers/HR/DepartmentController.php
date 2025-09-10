<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // <-- الخطوة الأولى: استدعاء السمة

class DepartmentController extends Controller
{
    use AuthorizesRequests; // <-- الخطوة الثانية: استخدام السمة

    public function index()
    {
        $this->authorize('viewAny', Department::class);

        // تحميل الأقسام مع تحميل علاقة المدير وعلاقات المدير بالموظف/المعلم
        $departments = Department::withCount(['employees', 'teachers'])
            ->with(['manager' => function ($query) {
                $query->with(['employee', 'teacher']);
            }])
            ->get();

        // تحميل المدراء المحتملين مع علاقاتهم بالموظف/المعلم
        $managers = User::whereHas('roles', fn($q) => $q->whereIn('name', ['admin', 'hr-manager', 'department-manager']))
            ->with(['employee', 'teacher'])
            ->get();

        return Inertia::render('HR/Departments/Index', [
            'departments' => $departments,
            'managers' => $managers,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Department::class);

        $validated = $request->validate([
            'name' => 'required|string|unique:departments,name',
            'description' => 'nullable|string',
            'manager_id' => 'nullable|exists:users,id',
        ]);

        Department::create($validated);
        return Redirect::route('hr.departments.index')->with('success', 'تم إنشاء القسم بنجاح.');
    }

    public function update(Request $request, Department $department)
    {
        $this->authorize('update', $department);

        $validated = $request->validate([
            'name' => 'required|string|unique:departments,name,' . $department->id,
            'description' => 'nullable|string',
            'manager_id' => 'nullable|exists:users,id',
        ]);

        $department->update($validated);
        return Redirect::route('hr.departments.index')->with('success', 'تم تحديث القسم بنجاح.');
    }

    public function destroy(Department $department)
    {
        $this->authorize('delete', $department);

        $department->delete();
        return Redirect::route('hr.departments.index')->with('success', 'تم حذف القسم بنجاح.');
    }
}

