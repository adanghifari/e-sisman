<?php

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
    Route::view('master-data/business-processes', 'master-data.business-processes')->name('master-data.business-processes');
    Route::view('master-data/departments', 'master-data.departments')->name('master-data.departments');
    Route::view('master-data/document-types', 'master-data.document-types')->name('master-data.document-types');
    Route::view('activity-log', 'activity-log.index')->name('activity-log.index');
});

require __DIR__.'/settings.php';
