<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['m_approval_flow_id', 'stage_order', 'keterangan', 'nama_tahap'])]
class ApprovalFlowStage extends Model
{
    protected $table = 'm_approval_flow_stages';

    protected function casts(): array
    {
        return [
            'stage_order' => 'integer',
        ];
    }

    public function approvalFlow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlow::class, 'm_approval_flow_id');
    }

    public function getDisplayLabelAttribute(): string
    {
        return trim(collect([$this->keterangan, $this->nama_tahap])
            ->filter()
            ->implode(' '));
    }
}
