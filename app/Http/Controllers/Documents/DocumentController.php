<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\ExternalParty;
use App\Models\DocumentWorkflow;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; 
use Illuminate\Support\Facades\Redirect;
use App\Models\DocumentAttachment;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of the documents.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $filters = $request->only('tab');
        $tab = $filters['tab'] ?? 'all';

        $query = Document::with(['creator', 'department', 'documentType']);

        // --- ### التعديل هنا: فلترة شاملة لمدير القسم ### ---
        // يطبق الفلترة إذا كان المستخدم مدير قسم، إلا إذا كان مدير عام
        if ($user->hasRole('department-manager') && !$user->hasRole('admin')) {
            $managedDeptIds = Department::where('manager_id', $user->id)->pluck('id');

            $query->where(function ($q) use ($managedDeptIds) {
                // الشرط الأول: الوثيقة صدرت من أحد أقسامه
                $q->whereIn('department_id', $managedDeptIds);

                // الشرط الثاني: أو أحد أقسامه موجود في مسار العمل
                foreach ($managedDeptIds as $deptId) {
                    $q->orWhereJsonContains('workflow_path', ['department_id' => $deptId]);
                }
            });
        }

        $query->when($tab === 'incoming', fn ($q) => $q->where('type', 'incoming'))
              ->when($tab === 'outgoing', fn ($q) => $q->where('type', 'outgoing'))
              ->when($tab === 'drafts', fn ($q) => $q->where('status', 'draft')->where('created_by_user_id', $user->id))
              ->when($tab === 'archived', fn ($q) => $q->where('status', 'archived'));

        $documents = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('Documents/Index', [
            'documents' => $documents,
            'filters' => ['tab' => $tab],
        ]);
    }

    /**
     * Show the form for creating a new document.
     */
    public function create(Request $request): Response
    {
        $user = Auth::user();
        $type = $request->query('type', 'outgoing');
        
        $year = date('Y');
        $lastDocument = Document::where('type', $type)->whereYear('created_at', $year)->latest('id')->first();
        $nextNumber = $lastDocument ? (int)substr($lastDocument->serial_number, -4) + 1 : 1;
        $serialNumber = strtoupper(substr($type, 0, 3)) . '/' . $year . '/' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

        $availableDepartments = [];
        if ($user->hasRole('department-manager')) {
            $availableDepartments = Department::where('manager_id', $user->id)->get(['id', 'name']);
        } elseif ($user->hasAnyRole(['admin', 'hr-manager'])) {
            $availableDepartments = Department::all(['id', 'name']);
        }

        return Inertia::render('Documents/Create', [
            'documentType' => $type,
            'generatedSerialNumber' => $serialNumber,
            'documentTypes' => DocumentType::all(['id', 'name']),
            'externalParties' => ExternalParty::all(['id', 'name']),
            'availableDepartments' => $availableDepartments, 
            'allDepartmentsForWorkflow' => Department::all(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created document in storage.
     */
    public function store(Request $request)
    {
        Log::debug("--- DocumentController@store: Request received ---");
        Log::debug("Request Data:", $request->all());

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
            'attachments.*' => 'file|max:10240', // 10MB max
            'workflow_steps' => 'nullable|array',
            'workflow_steps.*.department_id' => 'required|exists:departments,id',
            'action' => ['required', Rule::in(['save_draft', 'submit_review'])],
        ]);

        if ($validated['action'] === 'submit_review') {
            $request->validate(['workflow_steps' => 'required|array|min:1'], ['workflow_steps.required' => 'يجب تحديد مسار المراجعة والاعتماد قبل الإرسال.']);
        }

        DB::beginTransaction();
        try {
            $status = ($validated['action'] === 'save_draft') ? 'draft' : 'in_review';
            
            $document = Document::create([
                'type' => $validated['type'],
                'serial_number' => $validated['serial_number'],
                'subject' => $validated['subject'],
                'content' => $validated['content'],
                'document_type_id' => $validated['document_type_id'],
                'priority' => $validated['priority'],
                'confidentiality_level' => $validated['confidentiality_level'],
                'status' => $status,
                'created_by_user_id' => $user->id,
                'department_id' => $validated['department_id'],
                'external_party_id' => $validated['external_party_id'],
                'workflow_path' => $validated['workflow_steps'] ?? [],
            ]);

            // --- ### منطق حفظ المرفقات المفعّل ### ---
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

            if ($status === 'in_review' && !empty($validated['workflow_steps'])) {
                $firstStepDepartmentId = $validated['workflow_steps'][0]['department_id'];
                $document->workflowSteps()->create([
                    'from_department_id' => $validated['department_id'],
                    'to_department_id' => $firstStepDepartmentId,
                    'action' => 'review',
                    'notes' => 'الرجاء المراجعة والاعتماد',
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Document Creation Failed: ' . $e->getMessage());
            return Redirect::back()->with('error', 'حدث خطأ غير متوقع أثناء حفظ الوثيقة.');
        }

        $message = ($status === 'draft') ? 'تم حفظ المسودة بنجاح.' : 'تم إنشاء الوثيقة وبدء مسار العمل بنجاح.';
        return Redirect::route('documents.index')->with('success', $message);
    }
    /**
     * Display the specified document.
     */
    public function show(Document $document): Response
    {
        $this->authorize('view', $document);

        $document->load(['creator.employee', 'department', 'documentType', 'externalParty', 'attachments.uploader', 'workflowSteps.fromDepartment', 'workflowSteps.toDepartment', 'workflowSteps.processedBy']);
        
        $user = Auth::user();
        $managedDeptIds = Department::where('manager_id', $user->id)->pluck('id');

        // --- NEW LOGGING ---
        Log::debug("--- DocumentController@show: Finding current task for User ID: {$user->id} ---");
        Log::debug("Managed Department IDs: " . $managedDeptIds->implode(', '));

        $userCurrentTask = $document->workflowSteps()
            ->whereIn('to_department_id', $managedDeptIds)
            ->whereNull('completed_at')
            ->first();
            
        if ($userCurrentTask) {
            Log::debug("Task found in show(): Step ID {$userCurrentTask->id}, Target Dept ID {$userCurrentTask->to_department_id}");
        } else {
            Log::debug("No active task found for this user in show(). The action panel should be hidden.");
        }
        Log::debug("-----------------------------------------------------------------");

        return Inertia::render('Documents/Show', [
            'document' => $document,
            'userCurrentTask' => $userCurrentTask,
            'organizationDetails' => [
                'name' => 'Caledonian International School',
                'logoUrl' => asset('images/logo-school-one.png'),
                'stampUrl' => asset('images/stamp.png'),
                'address' => 'شارع الجمهورية، طرابلس، ليبيا',
            ],
        ]);
    }


    /**
     * Display the user's pending tasks.
     */
    public function tasks(Request $request): Response
    {
        $user = Auth::user();
        $managedDeptIds = Department::where('manager_id', $user->id)->pluck('id');

        $tasks = DocumentWorkflow::whereIn('to_department_id', $managedDeptIds)
            ->whereNull('completed_at')
            ->with(['document.creator', 'document.department', 'fromDepartment'])
            ->latest()
            ->paginate(15);

        return Inertia::render('Documents/Tasks', ['tasks' => $tasks]);
    }

    /**
     * Process a workflow action (approve/reject).
     */
    public function processWorkflowAction(Request $request, Document $document)
    {
        // --- NEW LOGGING ---
        Log::debug("--- DocumentController@processWorkflowAction: Received request. Authorizing... ---");
        
        $this->authorize('process', $document);

        Log::debug("--- Authorization PASSED in Controller. Proceeding with action. ---");

        $validated = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'notes' => 'nullable|string',
        ]);

        $user = Auth::user();
        $managedDeptIds = Department::where('manager_id', $user->id)->pluck('id');
        $currentTask = $document->workflowSteps()->whereIn('to_department_id', $managedDeptIds)->whereNull('completed_at')->first();

        if (!$currentTask) {
            return Redirect::back()->with('error', 'لا يوجد إجراء مطلوب منك لهذه الوثيقة.');
        }

        DB::beginTransaction();
        try {
            $currentTask->update([
                'completed_at' => now(),
                'notes' => $validated['notes'],
                'action' => $validated['action'],
                'processed_by_user_id' => $user->id,
            ]);

            if ($validated['action'] === 'approve') {
                $workflowPath = collect($document->workflow_path);
                $currentIndex = $workflowPath->search(fn($step) => $step['department_id'] == $currentTask->to_department_id);

                if ($currentIndex !== false && $workflowPath->has($currentIndex + 1)) {
                    $nextStepDepartmentId = $workflowPath->get($currentIndex + 1)['department_id'];
                    $document->workflowSteps()->create([
                        'from_department_id' => $currentTask->to_department_id,
                        'to_department_id' => $nextStepDepartmentId,
                        'action' => 'review',
                        'notes' => 'تمت الموافقة من قبل ' . $user->name,
                    ]);
                } else {
                    $document->update(['status' => 'approved']);
                }
            } else { 
                $document->update(['status' => 'rejected']);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Workflow Action Failed: ' . $e->getMessage());
            return Redirect::back()->with('error', 'حدث خطأ أثناء تنفيذ الإجراء.');
        }
        
        return Redirect::route('documents.tasks')->with('success', 'تم تنفيذ الإجراء بنجاح.');
    }


    public function update(Request $request, Document $document)
    {
        $this->authorize('update', $document);

        Log::debug("--- DocumentController@update: Request received for Document ID: {$document->id} ---");

        $user = Auth::user();
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'content' => 'nullable|string',
            'document_type_id' => 'required|exists:document_types,id',
            'department_id' => 'required|exists:departments,id',
            'priority' => 'required|string',
            'confidentiality_level' => 'required|string',
            'external_party_id' => 'nullable|exists:external_parties,id',
            'workflow_steps' => 'nullable|array',
            'workflow_steps.*.department_id' => 'required|exists:departments,id',
            'action' => ['required', Rule::in(['save_draft', 'submit_review'])],
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240',
            'attachments_to_remove' => 'nullable|array',
            'attachments_to_remove.*' => 'integer|exists:document_attachments,id',
        ]);
        
        Log::debug("Validation passed for update.");

        if ($validated['action'] === 'submit_review') {
            $request->validate(['workflow_steps' => 'required|array|min:1'], ['workflow_steps.required' => 'يجب تحديد مسار المراجعة والاعتماد قبل الإرسال.']);
        }

        DB::beginTransaction();
        try {
            $status = ($validated['action'] === 'save_draft') ? 'draft' : 'in_review';

            $document->update([
                'subject' => $validated['subject'],
                'content' => $validated['content'],
                'document_type_id' => $validated['document_type_id'],
                'priority' => $validated['priority'],
                'confidentiality_level' => $validated['confidentiality_level'],
                'status' => $status,
                'department_id' => $validated['department_id'],
                'external_party_id' => $validated['external_party_id'],
                'workflow_path' => $validated['workflow_steps'] ?? [],
            ]);
            
            // --- ### سجلات تتبع مفصلة للمرفقات ### ---
            Log::debug("Starting attachment processing...");

            // 1. حذف المرفقات المطلوبة
            if ($request->has('attachments_to_remove') && !empty($validated['attachments_to_remove'])) {
                Log::debug("Attachments to remove:", $validated['attachments_to_remove']);
                $attachmentsToDelete = DocumentAttachment::where('document_id', $document->id)
                    ->whereIn('id', $validated['attachments_to_remove'])
                    ->get();

                foreach ($attachmentsToDelete as $attachment) {
                    Log::debug("Deleting file from storage: " . $attachment->file_path);
                    Storage::disk('public')->delete($attachment->file_path);
                    $attachment->delete();
                    Log::debug("Deleted attachment record from DB: ID " . $attachment->id);
                }
            } else {
                Log::debug("No attachments marked for removal.");
            }

            // 2. إضافة المرفقات الجديدة
            if ($request->hasFile('attachments')) {
                Log::debug("'attachments' field contains files. Count: " . count($request->file('attachments')));
                foreach ($request->file('attachments') as $index => $file) {
                    if ($file && $file->isValid()) {
                        Log::debug("Uploading new file #{$index}: '{$file->getClientOriginalName()}'");
                        $path = $file->store("documents/{$document->id}", 'public');
                        $document->attachments()->create([
                            'uploaded_by_user_id' => $user->id,
                            'file_name' => $file->getClientOriginalName(),
                            'file_path' => $path,
                            'file_type' => $file->getClientMimeType(),
                            'file_size' => $file->getSize(),
                        ]);
                        Log::debug("File stored at: " . $path);
                    } else {
                         Log::warning("File #{$index} is not valid or null. Error: " . ($file ? $file->getError() : 'File is null'));
                    }
                }
            } else {
                Log::debug("'attachments' field does not contain any new files according to hasFile().");
            }
            
            if ($status === 'in_review' && !empty($validated['workflow_steps'])) {
                $document->workflowSteps()->delete(); 
                $firstStepDepartmentId = $validated['workflow_steps'][0]['department_id'];
                $document->workflowSteps()->create([
                    'from_department_id' => $validated['department_id'],
                    'to_department_id' => $firstStepDepartmentId,
                    'action' => 'review',
                    'notes' => 'الرجاء المراجعة والاعتماد',
                ]);
            }

            DB::commit();
            Log::debug("--- Document update process finished successfully. ---");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Document Update Failed: ' . $e->getMessage());
            return Redirect::back()->with('error', 'حدث خطأ غير متوقع أثناء تحديث الوثيقة.');
        }

        $message = ($status === 'draft') ? 'تم تحديث المسودة بنجاح.' : 'تم إرسال الوثيقة للمراجعة بنجاح.';
        return Redirect::route('documents.index')->with('success', $message);
    }

    public function edit(Document $document): Response
    {
        $this->authorize('update', $document); // Use the update policy

        $user = Auth::user();
        
        $availableDepartments = [];
        if ($user->hasRole('department-manager')) {
            $availableDepartments = Department::where('manager_id', $user->id)->get(['id', 'name']);
        } elseif ($user->hasAnyRole(['admin', 'hr-manager'])) {
            $availableDepartments = Department::all(['id', 'name']);
        }

        return Inertia::render('Documents/Create', [
            'document' => $document, // Pass the existing document
            'isEditing' => true, // Flag to indicate edit mode
            'documentType' => $document->type,
            'generatedSerialNumber' => $document->serial_number,
            'documentTypes' => DocumentType::all(['id', 'name']),
            'externalParties' => ExternalParty::all(['id', 'name']),
            'availableDepartments' => $availableDepartments, 
            'allDepartmentsForWorkflow' => Department::all(['id', 'name']),
        ]);
    }
}

