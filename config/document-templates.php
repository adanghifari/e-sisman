<?php

return [
    'upload' => [
        'max_file_size_kb' => 10 * 1024,
        'max_files' => 10,
        'allowed_extensions' => ['pdf', 'doc', 'docx'],
        'allowed_mime_types' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
    ],
];
