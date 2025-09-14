<?php

namespace App\Http\Controllers\Documents\Settings;

use App\Http\Controllers\Controller;
use App\Models\DocumentType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // <-- الخطوة الأولى: استدعاء السمة


class DocumentTypeController extends Controller
{
    use AuthorizesRequests; // <-- الخطوة الثانية: استخدام السمة
    public function index()
    {
        $this->authorize('manage document settings'); //
        return Inertia::render('Documents/Settings/DocumentTypes/Index', [
            'documentTypes' => DocumentType::all(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage document settings');
        $validated = $request->validate([
            'name' => 'required|string|unique:document_types,name',
            'description' => 'nullable|string',
        ]);

        DocumentType::create($validated);
        return Redirect::back()->with('success', 'تم إنشاء نوع الوثيقة بنجاح.');
    }

    public function update(Request $request, DocumentType $documentType)
    {
        $this->authorize('manage document settings');
        $validated = $request->validate([
            'name' => 'required|string|unique:document_types,name,' . $documentType->id,
            'description' => 'nullable|string',
        ]);

        $documentType->update($validated);
        return Redirect::back()->with('success', 'تم تحديث نوع الوثيقة بنجاح.');
    }

    public function destroy(DocumentType $documentType)
    {
        $this->authorize('manage document settings');
        // هنا يمكنك إضافة منطق للتحقق إذا كان النوع مستخدماً قبل الحذف
        $documentType->delete();
        return Redirect::back()->with('success', 'تم حذف نوع الوثيقة بنجاح.');
    }
}
