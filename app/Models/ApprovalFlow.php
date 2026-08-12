<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['m_document_types_id', 'nama_flow'])]
class ApprovalFlow extends Model
{
    protected $table = 'm_approval_flows';

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'm_document_types_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ApprovalFlowStage::class, 'm_approval_flow_id')
            ->orderBy('stage_order');
    }
}
