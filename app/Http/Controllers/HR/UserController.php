<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of the users with filtering.
     */
    public function index(Request $request)
    {
        $filters = $request->only('search', 'role', 'status');

        $users = User::with('roles')
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->input('role'), function ($query, $role) {
                $query->whereHas('roles', fn($q) => $q->where('name', $role));
            })
            ->when($request->input('status'), function ($query, $status) {
                if ($status === 'active') {
                    $query->whereNull('deactivated_at');
                } elseif ($status === 'inactive') {
                    $query->whereNotNull('deactivated_at');
                }
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('HR/Users/Index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => Role::all()->pluck('name'),
        ]);
    }

    /**
     * Show the form for editing the specified user's roles.
     */
    public function edit(User $user)
    {
        return Inertia::render('HR/Users/Edit', [
            'user' => $user->load('roles'),
            'roles' => Role::all(['id', 'name'])
        ]);
    }

    /**
     * Update the specified user's roles in storage.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name'
        ]);

        $user->syncRoles($request->roles);

        return Redirect::route('hr.users.index')->with('success', 'تم تحديث أدوار المستخدم بنجاح.');
    }
    
    /**
     * Update the activation status of a user.
     */
    public function updateStatus(Request $request, User $user)
    {
        $request->validate(['is_active' => 'required|boolean']);

        $user->deactivated_at = $request->is_active ? null : now();
        $user->save();

        $statusMessage = $request->is_active ? 'تفعيله' : 'إلغاء تفعيله';
        return Redirect::back()->with('success', "تم {$statusMessage} بنجاح.");
    }
}
