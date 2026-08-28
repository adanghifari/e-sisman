<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    't_document_id',
    'source_document_file_id',
    'generation_number',
    'generation_status',
    'path_file',
    'generated_file_name',
    'checksum_sha256',
    'file_size',
    'generated_by',
    'generated_at',
    'generation_error',
])]
class DocumentFinalArtifact extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_GENERATED = 'generated';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'generation_number' => 'integer',
            'file_size' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 't_document_id');
    }

    public function sourceFile(): BelongsTo
    {
        return $this->belongsTo(DocumentFile::class, 'source_document_file_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
