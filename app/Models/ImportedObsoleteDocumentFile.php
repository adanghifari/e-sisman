<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'imported_obsolete_document_id',
    'type_file',
    'path_file',
    'uploaded_by',
    'original_file_name',
    'stored_file_name',
    'file_size',
])]
class ImportedObsoleteDocumentFile extends Model
{
    public const OBSOLETE_DOCUMENT = 'obsolete_document';

    public const ATTACHMENT = 'attachment';

    public const FILE_TYPES = [
        self::OBSOLETE_DOCUMENT,
        self::ATTACHMENT,
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function importedObsoleteDocument(): BelongsTo
    {
        return $this->belongsTo(ImportedObsoleteDocument::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
