<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    't_document_id',
    't_document_files_id',
    'type_file',
    'document_number',
    'attachment_title',
    'attachment_order',
    'path_file',
    'uploaded_by',
    'updated_at',
    'original_file_name',
    'stored_file_name',
    'source_file_id',
    'file_size',
])]
class DocumentFile extends Model
{
    protected $table = 't_document_files';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'updated_at' => 'datetime',
            'file_size' => 'integer',
            'attachment_order' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 't_document_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function sourceFile(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_file_id');
    }
}
