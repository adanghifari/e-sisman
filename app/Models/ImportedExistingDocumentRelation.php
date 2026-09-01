<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'imported_existing_document_id',
    'related_imported_existing_document_id',
    'related_document_id',
    'relation_type',
    'keterangan',
    'created_by',
])]
class ImportedExistingDocumentRelation extends Model
{
    public const SUPERSEDED_BY = 'superseded_by';

    public const REFERENCES = 'references';

    public const RELATION_TYPES = [
        self::SUPERSEDED_BY,
        self::REFERENCES,
    ];

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(ImportedExistingDocument::class, 'imported_existing_document_id');
    }

    public function relatedImportedDocument(): BelongsTo
    {
        return $this->belongsTo(ImportedExistingDocument::class, 'related_imported_existing_document_id');
    }

    public function relatedDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'related_document_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
