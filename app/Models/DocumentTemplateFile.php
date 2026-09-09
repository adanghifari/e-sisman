<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_template_id',
    'file_order',
    'disk',
    'path_file',
    'original_file_name',
    'stored_file_name',
    'mime_type',
    'file_size',
])]
class DocumentTemplateFile extends Model
{
    protected function casts(): array
    {
        return [
            'file_order' => 'integer',
            'file_size' => 'integer',
        ];
    }

    public function documentTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class);
    }
}
