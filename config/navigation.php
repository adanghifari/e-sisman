<?php

return [
    'Utama' => [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'home'],
    ],

    'Manajemen Dokumen' => [
        ['label' => 'Butuh Diproses', 'route' => 'documents.inbox', 'icon' => 'inbox'],
        ['label' => 'Tambah Dokumen', 'route' => 'documents.create', 'icon' => 'document-plus'],
        ['label' => 'Dokumen Master', 'route' => 'documents.master', 'icon' => 'document-duplicate'],
    ],

    'Reporting' => [
        ['label' => 'Buat Laporan', 'route' => 'reports.index', 'icon' => 'chart-bar'],
    ],

    'Administrasi' => [
        ['label' => 'Manajemen User', 'route' => 'users.index', 'icon' => 'users'],
        ['label' => 'Approval Flow', 'route' => 'approval-flows.index', 'icon' => 'adjustments-horizontal'],
        ['label' => 'Template Dokumen', 'route' => 'document-templates.index', 'icon' => 'document-text'],
    ],

    'Master Data' => [
        ['label' => 'Proses / Fungsi', 'route' => 'master-data.process-functions', 'icon' => 'rectangle-stack'],
        ['label' => 'Proses Bisnis', 'route' => 'master-data.business-processes', 'icon' => 'briefcase'],
        ['label' => 'Department', 'route' => 'master-data.departments', 'icon' => 'building-office-2'],
        ['label' => 'Jenis Dokumen', 'route' => 'master-data.document-types', 'icon' => 'tag'],
    ],

    'Log' => [
        ['label' => 'Catatan Aktivitas', 'route' => 'activity-log.index', 'icon' => 'clipboard-document-list'],
    ],
];
