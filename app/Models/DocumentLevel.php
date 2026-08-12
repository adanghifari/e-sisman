<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kode', 'nama_level', 'nama_dokumen', 'prefix', 'description', 'is_active', 'sort_order'])]
class DocumentLevel extends Model
{
    protected $table = 'm_document_levels';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function approvalFlows(): HasMany
    {
        return $this->hasMany(ApprovalFlow::class, 'm_document_level_id');
    }
}
