<?php

use App\Livewire\Administration\ApprovalFlow\Index as ApprovalFlowIndex;
use App\Livewire\MasterData\BusinessFunction\Index as BusinessFunctionIndex;
use App\Livewire\MasterData\BusinessProcess\Index as BusinessProcessIndex;
use App\Livewire\MasterData\Department\Index as DepartmentIndex;
use App\Livewire\MasterData\DocumentType\Index as DocumentTypeIndex;
use App\Http\Controllers\DocumentManagement\DocumentController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'main.dashboard')->name('dashboard');
    Route::view('documents/inbox', 'document-management.inbox')->name('documents.inbox');
    Route::view('documents/create', 'document-management.create.index')->name('documents.create');
    Route::view('documents/create/{level}', 'document-management.create.level')
        ->whereIn('level', array_keys(config('document-levels')))
        ->name('documents.create.level');
    Route::post('documents/create/{level}', [DocumentController::class, 'store'])
        ->whereIn('level', array_keys(config('document-levels')))
        ->name('documents.store');
    Route::view('documents/master', 'document-management.master')->name('documents.master');
    Route::view('document-templates', 'document-management.templates.index')->name('document-templates.index');
    Route::view('reports', 'reporting.index')->name('reports.index');
    Route::view('users', 'administration.users.index')->name('users.index');
    Route::view('access-groups', 'administration.access-groups.index')->name('access-groups.index');
    Route::view('access-menus', 'administration.access-menus.index')->name('access-menus.index');
    Route::livewire('approval-flows', ApprovalFlowIndex::class)->name('approval-flows.index');
    Route::livewire('master-data/process-functions', BusinessFunctionIndex::class)->name('master-data.process-functions');
    Route::livewire('master-data/business-processes', BusinessProcessIndex::class)->name('master-data.business-processes');
    Route::livewire('master-data/departments', DepartmentIndex::class)->name('master-data.departments');
    Route::livewire('master-data/document-types', DocumentTypeIndex::class)->name('master-data.document-types');
    Route::view('activity-log', 'log.activity-log.index')->name('activity-log.index');
});

require __DIR__.'/settings.php';
