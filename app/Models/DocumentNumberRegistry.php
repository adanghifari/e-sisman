<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_number',
    'scope_identifier',
    'source_type',
    'source_id',
    'registered_by',
    'registered_at',
])]
class DocumentNumberRegistry extends Model
{
    protected $table = 'document_number_registry';

    public const UPDATED_AT = null;

    public const SOURCE_T_DOCUMENT = 't_document';

    public const SOURCE_IMPORTED_EXISTING = 'imported_existing_document';

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
        ];
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}
