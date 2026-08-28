<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    't_document_id',
    'm_approval_status_id',
    'user_id',
    'role_id',
    'assigned_by',
    'assigned_at',
    'responded_at',
    'stages',
    'catatan',
    'stage_name_snapshot',
    'stage_order_snapshot',
    'approver_name_snapshot',
    'approver_position_snapshot',
    'approver_department_snapshot',
])]
class Approval extends Model
{
    protected $table = 't_approval';

    public $timestamps = false;

    public function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'responded_at' => 'datetime',
            'stage_order_snapshot' => 'integer',
        ];
    }

    public function fillResponseSnapshot(?int $stageOrder = null): self
    {
        $approver = $this->approver()
            ->with('department')
            ->first();

        return $this->fill([
            'stage_name_snapshot' => $this->stages,
            'stage_order_snapshot' => $stageOrder,
            'approver_name_snapshot' => $approver?->name,
            'approver_position_snapshot' => $approver?->jabatan,
            'approver_department_snapshot' => $approver?->department?->nama_department,
        ]);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 't_document_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ApprovalStatus::class, 'm_approval_status_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
