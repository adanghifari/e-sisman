<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable([
    'm_status_document_id',
    'm_document_level_id',
    'm_document_types_id',
    'm_proses_bisnis_id',
    'm_proses_fungsi_id',
    'user_id',
    'official_preparer_id',
    'reference',
    'revised_from',
    'request_type',
    'nama_dokumen',
    'nomor_dokumen',
    'nomor_revisi',
    'catatan_revisi',
    'created_at',
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
            'created_at' => 'datetime',
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

    public function officialPreparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'official_preparer_id');
    }

    public function referenceDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reference');
    }

    public function revisedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revised_from');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'revised_from');
    }

    public function revisionRootId(): int
    {
        $document = $this;

        while ($document->revised_from !== null) {
            $document = self::query()
                ->select(['id', 'revised_from'])
                ->findOrFail($document->revised_from);
        }

        return $document->id;
    }

    public function revisionFamily(): Collection
    {
        $documents = self::query()
            ->select(['id', 'revised_from'])
            ->get();
        $rootId = $this->revisionRootId();
        $familyIds = collect([$rootId]);
        $previousCount = 0;

        while ($familyIds->count() !== $previousCount) {
            $previousCount = $familyIds->count();
            $childIds = $documents
                ->whereIn('revised_from', $familyIds)
                ->pluck('id');
            $familyIds = $familyIds->merge($childIds)->unique()->values();
        }

        return self::query()
            ->whereIn('id', $familyIds)
            ->get();
    }

    public function obsoleteRevisions(): HasMany
    {
        return $this->hasMany(self::class, 'revised_from')
            ->whereHas('status', fn ($query) => $query->where('nama_status', StatusDocument::OBSOLETE));
    }

    public function getFormattedRevisionAttribute(): string
    {
        return '00.'.str_pad((string) $this->nomor_revisi, 2, '0', STR_PAD_LEFT);
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
