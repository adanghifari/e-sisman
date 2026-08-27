<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'document_state',
    'obsolete_rule_type',
    'm_document_level_id',
    'm_document_types_id',
    'm_proses_bisnis_id',
    'm_proses_fungsi_id',
    'uploaded_by',
    'nama_dokumen',
    'nomor_dokumen',
    'nomor_revisi',
    'tanggal_terbit',
    'tanggal_obsolete',
    'catatan',
])]
class ImportedExistingDocument extends Model
{
    public const STATE_MASTER = 'master';

    public const STATE_OBSOLETE = 'obsolete';

    public const DOCUMENT_STATES = [
        self::STATE_MASTER,
        self::STATE_OBSOLETE,
    ];

    public const CURRENT_RULE = 'current_rule';

    public const LEGACY_RULE = 'legacy_rule';

    public const RULE_TYPES = [
        self::CURRENT_RULE,
        self::LEGACY_RULE,
    ];

    public function isMaster(): bool
    {
        return $this->document_state === self::STATE_MASTER;
    }

    public function isObsolete(): bool
    {
        return $this->document_state === self::STATE_OBSOLETE;
    }

    protected function casts(): array
    {
        return [
            'tanggal_terbit' => 'date',
            'tanggal_obsolete' => 'date',
        ];
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

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(
            Department::class,
            'imported_existing_document_departments',
            'imported_existing_document_id',
            'department_id',
        );
    }

    public function files(): HasMany
    {
        return $this->hasMany(ImportedExistingDocumentFile::class)->orderByDesc('id');
    }

    public function outgoingRelations(): HasMany
    {
        return $this->hasMany(ImportedExistingDocumentRelation::class);
    }

    public function incomingImportedRelations(): HasMany
    {
        return $this->hasMany(ImportedExistingDocumentRelation::class, 'related_imported_existing_document_id');
    }

    public function tDocumentRevisions(): HasMany
    {
        return $this->hasMany(Document::class, 'imported_existing_source_id');
    }
}
