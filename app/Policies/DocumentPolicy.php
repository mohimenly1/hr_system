<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Log; // <-- الخطوة 1: تأكد من إضافة هذا السطر

class DocumentPolicy
{
    /**
     * Perform pre-authorization checks.
     *
     * @param  \App\Models\User  $user
     * @param  string  $ability
     * @return bool|null
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('admin') || $user->can('manage all documents')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view documents');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Document $document): bool
    {
        if ($document->created_by_user_id === $user->id) {
            return true;
        }

        $workflowPathUserIds = collect($document->workflow_path)->pluck('user_id')->all();
        
        return in_array($user->id, $workflowPathUserIds);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create outgoing documents') || $user->can('register incoming documents');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Document $document): bool
    {
        return $document->created_by_user_id === $user->id && $document->status->value === 'draft';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Document $document): bool
    {
        return $document->created_by_user_id === $user->id && $document->status->value === 'draft';
    }

    /**
     * ### تم تعديل اسم الدالة هنا ###
     * Determine whether the user can process a workflow action on the model.
     */
    public function process(User $user, Document $document): bool
    {
        Log::debug("--- DocumentPolicy@process Check ---");
        Log::debug("User ID trying to act: " . $user->id . " (" . $user->name . ")");
        Log::debug("Document ID being processed: " . $document->id . " (Subject: " . $document->subject . ")");

        // البحث عن الخطوة الحالية النشطة (التي لم تكتمل بعد) في مسار العمل
        $currentStep = $document->workflowSteps()->whereNull('completed_at')->first();

        if (!$currentStep) {
            Log::debug("Result: No active workflow step found. Denying action.");
            Log::debug("------------------------------------------");
            return false;
        }

        Log::debug("Active step found. Step ID: " . $currentStep->id);
        Log::debug("Required User ID for this step (to_user_id): " . $currentStep->to_user_id);

        // فقط المستخدم المكلف بالخطوة الحالية هو من يمكنه اتخاذ الإجراء
        $isAuthorized = $currentStep->to_user_id === $user->id;

        Log::debug("Authorization check result: " . ($isAuthorized ? 'true (Authorized)' : 'false (Unauthorized)'));
        Log::debug("------------------------------------------");
        
        return $isAuthorized;
    }
}

