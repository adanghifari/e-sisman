<?php

use App\Livewire\MasterData\BusinessProcess\Index as BusinessProcessIndex;
use App\Livewire\MasterData\Department\Index as DepartmentIndex;
use App\Livewire\MasterData\DocumentType\Index as DocumentTypeIndex;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::view('documents/inbox', 'documents.inbox')->name('documents.inbox');
    Route::view('documents/create', 'documents.create')->name('documents.create');
    Route::view('documents/master', 'documents.master')->name('documents.master');
    Route::view('reports', 'reports.index')->name('reports.index');
    Route::view('users', 'users.index')->name('users.index');
    Route::view('approval-flows', 'approval-flows.index')->name('approval-flows.index');
    Route::view('document-templates', 'document-templates.index')->name('document-templates.index');
    Route::view('master-data/process-functions', 'master-data.process-functions')->name('master-data.process-functions');
    Route::livewire('master-data/business-processes', BusinessProcessIndex::class)->name('master-data.business-processes');
    Route::livewire('master-data/departments', DepartmentIndex::class)->name('master-data.departments');
    Route::livewire('master-data/document-types', DocumentTypeIndex::class)->name('master-data.document-types');
    Route::view('activity-log', 'activity-log.index')->name('activity-log.index');
});

require __DIR__.'/settings.php';
