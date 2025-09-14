<?php

namespace App\Http\Controllers\Documents\Settings;

use App\Http\Controllers\Controller;
use App\Models\ExternalParty;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // <-- الخطوة الأولى: استدعاء السمة

class ExternalPartyController extends Controller
{
    use AuthorizesRequests; // <-- الخطوة الثانية: استخدام السمة
    public function index()
    {
        $this->authorize('manage document settings');
        return Inertia::render('Documents/Settings/ExternalParties/Index', [
            'externalParties' => ExternalParty::all(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage document settings');
        $validated = $request->validate([
            'name' => 'required|string|unique:external_parties,name',
            'type' => 'nullable|string',
            'contact_info' => 'nullable|string',
        ]);

        ExternalParty::create($validated);
        return Redirect::back()->with('success', 'تم إنشاء الجهة بنجاح.');
    }

    public function update(Request $request, ExternalParty $externalParty)
    {
        $this->authorize('manage document settings');
        $validated = $request->validate([
            'name' => 'required|string|unique:external_parties,name,' . $externalParty->id,
            'type' => 'nullable|string',
            'contact_info' => 'nullable|string',
        ]);

        $externalParty->update($validated);
        return Redirect::back()->with('success', 'تم تحديث الجهة بنجاح.');
    }

    public function destroy(ExternalParty $externalParty)
    {
        $this->authorize('manage document settings');
        $externalParty->delete();
        return Redirect::back()->with('success', 'تم حذف الجهة بنجاح.');
    }
}
