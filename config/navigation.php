<?php

return [
    'Utama' => [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'home'],
    ],

    'Manajemen Dokumen' => [
        ['label' => 'Butuh Diproses', 'route' => 'documents.inbox', 'icon' => 'inbox'],
        ['label' => 'Tambah Dokumen', 'route' => 'documents.create', 'icon' => 'document-plus'],
        ['label' => 'Dokumen Master', 'route' => 'documents.master', 'icon' => 'document-duplicate'],
        ['label' => 'Template Dokumen', 'route' => 'document-templates.index', 'icon' => 'document-text'],
    ],

    'Reporting' => [
        ['label' => 'Overview', 'route' => 'reports.index', 'icon' => 'chart-bar'],
    ],

    'Administrasi' => [
        [
            'label' => 'Manajemen User',
            'icon' => 'users',
            'children' => [
                ['label' => 'User', 'route' => 'users.index', 'icon' => 'user'],
                ['label' => 'Group Akses', 'route' => 'access-groups.index', 'icon' => 'shield-check'],
                ['label' => 'Menu Akses', 'route' => 'access-menus.index', 'icon' => 'list-bullet'],
            ],
        ],
        ['label' => 'Approval Flow', 'route' => 'approval-flows.index', 'icon' => 'adjustments-horizontal'],
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
