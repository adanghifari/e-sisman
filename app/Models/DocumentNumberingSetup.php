<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'scope_identifier',
    'existing_start_number',
    'existing_end_number',
    'v2_start_number',
    'configured_by',
    'configured_at',
])]
class DocumentNumberingSetup extends Model
{
    protected $table = 'document_numbering_setups';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'existing_start_number' => 'integer',
            'existing_end_number' => 'integer',
            'v2_start_number' => 'integer',
            'configured_at' => 'datetime',
        ];
    }

    public function configurator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'configured_by');
    }
}
