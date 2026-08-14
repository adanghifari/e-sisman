<?php

use App\Livewire\Administration\ApprovalFlow\Index as ApprovalFlowIndex;
use App\Livewire\Administration\AccessGroup\Index as AccessGroupIndex;
use App\Livewire\Administration\AccessMenu\Index as AccessMenuIndex;
use App\Livewire\MasterData\BusinessFunction\Index as BusinessFunctionIndex;
use App\Livewire\MasterData\BusinessProcess\Index as BusinessProcessIndex;
use App\Livewire\MasterData\Department\Index as DepartmentIndex;
use App\Livewire\MasterData\DocumentType\Index as DocumentTypeIndex;
use App\Http\Controllers\DocumentManagement\DocumentController;
use App\Http\Controllers\DocumentManagement\DocumentApprovalController;
use App\Http\Controllers\DocumentManagement\DocumentInboxController;
use App\Http\Controllers\DocumentManagement\DocumentMasterController;
use App\Http\Controllers\DocumentManagement\DocumentTemplateController;
use App\Http\Middleware\EnsureRoutePermission;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified', EnsureRoutePermission::class])->group(function () {
    Route::view('dashboard', 'main.dashboard')->name('dashboard');
    Route::get('documents/inbox', DocumentInboxController::class)->name('documents.inbox');
    Route::get('documents/inbox/{document}', [DocumentApprovalController::class, 'show'])->name('documents.approval.show');
    Route::get('documents/inbox/{document}/assign', fn ($document) => redirect()->route('documents.approval.show', $document));
    Route::post('documents/inbox/{document}', [DocumentApprovalController::class, 'assign'])->name('documents.approval.assign');
    Route::post('documents/inbox/{document}/approve', [DocumentApprovalController::class, 'approve'])->name('documents.approval.approve');
    Route::post('documents/inbox/{document}/reject', [DocumentApprovalController::class, 'reject'])->name('documents.approval.reject');
    Route::post('documents/inbox/{document}/assign', fn ($document) => redirect()
        ->route('documents.approval.show', $document)
        ->withErrors(['stage_approvers' => 'Halaman assign sudah diperbarui. Silakan simpan ulang dari detail dokumen.']));
    Route::get('documents/inbox/{document}/files/{file}', [DocumentApprovalController::class, 'file'])->name('documents.approval.files.show');
    Route::get('documents/inbox/{document}/files/{file}/preview', [DocumentApprovalController::class, 'preview'])->name('documents.approval.files.preview');
    Route::view('documents/create', 'document-management.create.index')->name('documents.create');
    Route::view('documents/create/{level}', 'document-management.create.level')
        ->whereIn('level', array_keys(config('document-levels')))
        ->name('documents.create.level');
    Route::post('documents/create/{level}', [DocumentController::class, 'store'])
        ->whereIn('level', array_keys(config('document-levels')))
        ->name('documents.store');
    Route::get('documents/master', DocumentMasterController::class)->name('documents.master');
    Route::get('document-templates', [DocumentTemplateController::class, 'index'])->name('document-templates.index');
    Route::post('document-templates', [DocumentTemplateController::class, 'store'])->name('document-templates.store');
    Route::get('document-templates/files/{file}', [DocumentTemplateController::class, 'file'])->name('document-templates.files.show');
    Route::view('reports', 'reporting.index')->name('reports.index');
    Route::view('users', 'administration.users.index')->name('users.index');
    Route::livewire('access-groups', AccessGroupIndex::class)->name('access-groups.index');
    Route::livewire('access-menus', AccessMenuIndex::class)->name('access-menus.index');
    Route::livewire('approval-flows', ApprovalFlowIndex::class)->name('approval-flows.index');
    Route::livewire('master-data/process-functions', BusinessFunctionIndex::class)->name('master-data.process-functions');
    Route::livewire('master-data/business-processes', BusinessProcessIndex::class)->name('master-data.business-processes');
    Route::livewire('master-data/departments', DepartmentIndex::class)->name('master-data.departments');
    Route::livewire('master-data/document-types', DocumentTypeIndex::class)->name('master-data.document-types');
    Route::view('activity-log', 'log.activity-log.index')->name('activity-log.index');
});

require __DIR__.'/settings.php';
