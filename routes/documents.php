<?php

use App\Http\Controllers\Documents\DocumentController;
use App\Http\Controllers\Documents\Settings\DocumentTypeController;
use App\Http\Controllers\Documents\Settings\ExternalPartyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    
    Route::prefix('documents')->name('documents.')->group(function () {
        
        Route::get('/', [DocumentController::class, 'index'])->name('index')->middleware('permission:view documents');
        Route::get('/create', [DocumentController::class, 'create'])->name('create')->middleware('permission:create outgoing documents|register incoming documents');
        Route::post('/', [DocumentController::class, 'store'])->name('store');
        Route::get('/tasks', [DocumentController::class, 'tasks'])->name('tasks')->middleware('permission:execute document workflows');
        
        // --- الإضافات الجديدة هنا ---
        // هذا المسار مسؤول عن "تفعيل" وعرض صفحة Documents/Show.vue
        Route::get('/{document}', [DocumentController::class, 'show'])->name('show');
        // هذا المسار مسؤول عن استقبال إجراءات الموافقة والرفض
        Route::post('/{document}/workflow', [DocumentController::class, 'processWorkflowAction'])->name('workflow.action');

        Route::prefix('settings')->name('settings.')->middleware('permission:manage document settings')->group(function() {
            Route::resource('document-types', DocumentTypeController::class)->except(['show', 'create', 'edit']);
            Route::resource('external-parties', ExternalPartyController::class)->except(['show', 'create', 'edit']);
        });

    });
});

