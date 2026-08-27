<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'imported_existing_document_id',
    'type_file',
    'path_file',
    'uploaded_by',
    'original_file_name',
    'stored_file_name',
    'file_size',
])]
class ImportedExistingDocumentFile extends Model
{
    public const EXISTING_DOCUMENT = 'existing_document';

    public const OBSOLETE_DOCUMENT = self::EXISTING_DOCUMENT;

    public const ATTACHMENT = 'attachment';

    public const FILE_TYPES = [
        self::EXISTING_DOCUMENT,
        self::ATTACHMENT,
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function importedExistingDocument(): BelongsTo
    {
        return $this->belongsTo(ImportedExistingDocument::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
