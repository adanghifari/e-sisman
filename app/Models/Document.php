<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'm_status_document_id',
    'm_document_level_id',
    'm_document_types_id',
    'm_proses_bisnis_id',
    'm_proses_fungsi_id',
    'user_id',
    'reference',
    'nama_dokumen',
    'nomor_dokumen',
    'nomor_revisi',
    'catatan_revisi',
    'tanggal_terbit',
    'submitted_at',
    'approved_at',
    'rejected_at',
    'cancelled_at',
])]
class Document extends Model
{
    protected $table = 't_document';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'nomor_revisi' => 'integer',
            'tanggal_terbit' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(StatusDocument::class, 'm_status_document_id');
    }

    public function documentLevel(): BelongsTo
    {
        return $this->belongsTo(DocumentLevel::class, 'm_document_level_id');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'm_document_types_id');
    }

    public function businessProcess(): BelongsTo
    {
        return $this->belongsTo(BusinessProcess::class, 'm_proses_bisnis_id');
    }

    public function businessFunction(): BelongsTo
    {
        return $this->belongsTo(BusinessFunction::class, 'm_proses_fungsi_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function referenceDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reference');
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(
            Department::class,
            'document_departments',
            't_document_id',
            'department_id',
        );
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class, 't_document_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DocumentFile::class, 't_document_id');
    }
}
