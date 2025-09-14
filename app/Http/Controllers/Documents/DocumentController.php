<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\ExternalParty;
use App\Models\DocumentWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; 


class DocumentController extends Controller
{
    use AuthorizesRequests;
    /**
     * عرض قائمة الوثائق (الصادر والوارد) مع الفلترة والبحث.
     */
 // في DocumentController.php

public function index(Request $request): Response
{
    // استلام الفلاتر من الرابط (مثل ?tab=incoming)
    $filters = $request->only('tab');
    $tab = $filters['tab'] ?? 'all';

    $query = Document::with(['creator', 'department', 'documentType'])
        ->when($tab === 'incoming', function ($q) {
            $q->where('type', 'incoming');
        })
        ->when($tab === 'outgoing', function ($q) {
            $q->where('type', 'outgoing');
        })
        ->when($tab === 'drafts', function ($q) {
            $q->where('status', 'draft')->where('created_by_user_id', Auth::id());
        })
        ->when($tab === 'archived', function ($q) {
            $q->where('status', 'archived');
        });

    $documents = $query->latest()->paginate(15)->withQueryString();

    return Inertia::render('Documents/Index', [
        'documents' => $documents,
        'filters' => ['tab' => $tab], // إرسال التبويب النشط للواجهة
    ]);
}
    /**
     * عرض نموذج إنشاء وثيقة جديدة (صادرة أو واردة).
     */
// في DocumentController.php

public function create(Request $request): Response
{
    $user = Auth::user();
    $type = $request->query('type', 'outgoing');

    // --- Generate Serial Number ---
    $year = date('Y');
    $lastDocument = Document::where('type', $type)->whereYear('created_at', $year)->latest('id')->first();
    $nextNumber = $lastDocument ? (int)substr($lastDocument->serial_number, -4) + 1 : 1;
    $serialNumber = strtoupper(substr($type, 0, 3)) . '/' . $year . '/' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

    // --- NEW: Determine available departments for the current user ---
    $availableDepartments = [];
    if ($user->hasRole('department-manager')) {
        // If user is a manager, only show departments they manage
        $availableDepartments = Department::where('manager_id', $user->id)->get(['id', 'name']);
    } elseif ($user->hasAnyRole(['admin', 'hr-manager'])) {
        // If admin/hr, show all departments
        $availableDepartments = Department::all(['id', 'name']);
    }
    // For other roles, the list will be empty unless you define a specific logic.

    return Inertia::render('Documents/Create', [
        'documentType' => $type,
        'generatedSerialNumber' => $serialNumber,
        'documentTypes' => DocumentType::all(['id', 'name']),
        'externalParties' => ExternalParty::all(['id', 'name']),
        'availableDepartments' => $availableDepartments, // <-- UPDATED PROP
        'users' => User::all(['id', 'name']),
    ]);
}
    /**
     * عرض المهام المعلقة على المستخدم الحالي.
     */
    public function tasks(Request $request): Response
    {
        $user = Auth::user();

        // جلب جميع خطوات العمل الموجهة للمستخدم الحالي والتي لم تكتمل بعد
        $tasks = DocumentWorkflow::where('to_user_id', $user->id)
            ->whereNull('completed_at')
            ->with(['document' => function($query) {
                $query->with(['creator', 'department']);
            }, 'fromUser'])
            ->latest()
            ->paginate(15);

        return Inertia::render('Documents/Tasks', [
            'tasks' => $tasks,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'type' => ['required', Rule::in(['outgoing', 'incoming'])],
            'serial_number' => 'required|string|unique:documents,serial_number',
            'subject' => 'required|string|max:255',
            'content' => 'nullable|string',
            'document_type_id' => 'required|exists:document_types,id',
            'department_id' => 'required|exists:departments,id',
            'priority' => 'required|string',
            'confidentiality_level' => 'required|string',
            'external_party_id' => 'nullable|exists:external_parties,id',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240',
            'workflow_steps' => 'required|array|min:1',
            'workflow_steps.*.user_id' => 'required|exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            $document = Document::create([
                'type' => $validated['type'],
                'serial_number' => $validated['serial_number'],
                'subject' => $validated['subject'],
                'content' => $validated['content'],
                'document_type_id' => $validated['document_type_id'],
                'priority' => $validated['priority'],
                'confidentiality_level' => $validated['confidentiality_level'],
                'status' => 'in_review',
                'created_by_user_id' => $user->id,
                'department_id' => $validated['department_id'],
                'external_party_id' => $validated['external_party_id'],
                'workflow_path' => $validated['workflow_steps'], // <-- حفظ المسار الكامل
            ]);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store("documents/{$document->id}", 'public');
                    $document->attachments()->create([
                        'uploaded_by_user_id' => $user->id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'file_type' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }

            $firstStepUser = $validated['workflow_steps'][0]['user_id'];
            $document->workflowSteps()->create([
                'from_user_id' => $user->id,
                'to_user_id' => $firstStepUser,
                'action' => 'review',
                'notes' => 'الرجاء المراجعة والاعتماد',
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Document Creation Failed: ' . $e->getMessage());
            return Redirect::back()->with('error', 'حدث خطأ غير متوقع أثناء حفظ الوثيقة.');
        }

        return Redirect::route('documents.index')->with('success', 'تم إنشاء الوثيقة وبدء مسار العمل بنجاح.');
    }

    public function show(Document $document): Response
    {
        $this->authorize('view', $document);

        $document->load(['creator.employee', 'department', 'documentType', 'externalParty', 'attachments.uploader', 'workflowSteps.fromUser', 'workflowSteps.toUser']);
        
        // Find the current active step for the logged-in user
        $user = Auth::user();
        $userCurrentTask = $document->workflowSteps()
            ->where('to_user_id', $user->id)
            ->whereNull('completed_at')
            ->first();

        return Inertia::render('Documents/Show', [
            'document' => $document,
            'userCurrentTask' => $userCurrentTask,
            // --- التعديل هنا: استخدام مسارات الصور المحلية ---
            'organizationDetails' => [
                'name' => 'Caledonian International School',
                'logoUrl' => asset('images/logo-school-one.png'), // استخدام asset() للوصول لمجلد public
                'stampUrl' => asset('images/stamp.png'), // استخدام asset() للوصول لمجلد public
                'address' => 'شارع الجمهورية، طرابلس، ليبيا',
            ],
        ]);
    }


    public function processWorkflowAction(Request $request, Document $document)
    {
        $this->authorize('process', $document);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'notes' => 'nullable|string',
        ]);

        $user = Auth::user();
        $currentTask = $document->workflowSteps()->where('to_user_id', $user->id)->whereNull('completed_at')->first();

        if (!$currentTask) {
            return Redirect::back()->with('error', 'لا يوجد إجراء مطلوب منك لهذه الوثيقة.');
        }

        DB::beginTransaction();
        try {
            // 1. Mark current task as completed
            $currentTask->update([
                'completed_at' => now(),
                'notes' => $validated['notes'],
                'action' => $validated['action'],
            ]);

            if ($validated['action'] === 'approve') {
                $workflowPath = collect($document->workflow_path);
                $currentIndex = $workflowPath->search(fn($step) => $step['user_id'] == $user->id);

                // Check if there is a next step
                if ($currentIndex !== false && $workflowPath->has($currentIndex + 1)) {
                    $nextStepUser = $workflowPath->get($currentIndex + 1)['user_id'];
                    $document->workflowSteps()->create([
                        'from_user_id' => $user->id,
                        'to_user_id' => $nextStepUser,
                        'action' => 'review',
                        'notes' => 'تمت الموافقة من قبل ' . $user->name,
                    ]);
                } else {
                    // This is the last step, approve the document
                    $document->update(['status' => 'approved']);
                }
            } else { // Action is 'reject'
                $document->update(['status' => 'rejected']);
                // Optional: Notify the creator
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Workflow Action Failed: ' . $e->getMessage());
            return Redirect::back()->with('error', 'حدث خطأ أثناء تنفيذ الإجراء.');
        }
        
        return Redirect::route('documents.tasks')->with('success', 'تم تنفيذ الإجراء بنجاح.');
    }
}