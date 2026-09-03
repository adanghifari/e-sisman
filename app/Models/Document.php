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
    'official_preparer_name_snapshot',
    'official_preparer_position_snapshot',
    'official_preparer_department_snapshot',
    'reference',
    'revised_from',
    'imported_existing_source_id',
    'resubmitted_from',
    'request_type',
    'nama_dokumen',
    'nomor_dokumen',
    'nomor_lembar_revisi',
    'nomor_revisi',
    'catatan_revisi',
    'created_at',
    'tanggal_terbit',
    'submitted_at',
    'approved_at',
    'obsolete_at',
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
            'obsolete_at' => 'datetime',
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

    public function snapshotOfficialPreparer(): void
    {
        if ($this->official_preparer_id === null) {
            return;
        }

        if (
            $this->official_preparer_name_snapshot !== null
            && $this->official_preparer_position_snapshot !== null
            && $this->official_preparer_department_snapshot !== null
        ) {
            return;
        }

        $this->loadMissing('officialPreparer.department');

        $this->forceFill([
            'official_preparer_name_snapshot' => $this->official_preparer_name_snapshot ?: $this->officialPreparer?->name,
            'official_preparer_position_snapshot' => $this->official_preparer_position_snapshot ?: $this->officialPreparer?->jabatan,
            'official_preparer_department_snapshot' => $this->official_preparer_department_snapshot ?: $this->officialPreparer?->department?->nama_department,
        ])->save();
    }

    public function referenceDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reference');
    }

    public function revisedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revised_from');
    }

    public function importedExistingSource(): BelongsTo
    {
        return $this->belongsTo(ImportedExistingDocument::class, 'imported_existing_source_id');
    }

    public function resubmittedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'resubmitted_from');
    }

    public function resubmissions(): HasMany
    {
        return $this->hasMany(self::class, 'resubmitted_from');
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
        return self::formatRevisionNumber((int) $this->nomor_revisi);
    }

    public static function formatRevisionNumber(int $revision): string
    {
        $revision = max(0, $revision);

        return str_pad((string) intdiv($revision, 100), 2, '0', STR_PAD_LEFT)
            .'.'
            .str_pad((string) ($revision % 100), 2, '0', STR_PAD_LEFT);
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
        // Newer file records win when legacy/concurrent data contains duplicates.
        return $this->hasMany(DocumentFile::class, 't_document_id')->orderByDesc('id');
    }

    public function availableRevisionSourceAttachments(): Collection
    {
        $eligibleStatusIds = StatusDocument::query()
            ->whereIn('nama_status', [StatusDocument::APPROVED, StatusDocument::OBSOLETE])
            ->pluck('id');

        $family = $this->revisionFamily()
            ->filter(fn (self $document): bool => (int) $document->nomor_revisi <= (int) $this->nomor_revisi)
            ->filter(fn (self $document): bool => $document->is($this) || $eligibleStatusIds->contains($document->m_status_document_id))
            ->sortBy([
                ['nomor_revisi', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        if ($family->isEmpty()) {
            return collect();
        }

        $revisionByDocumentId = $family
            ->mapWithKeys(fn (self $document): array => [$document->id => (int) $document->nomor_revisi]);

        $attachments = DocumentFile::query()
            ->whereIn('t_document_id', $family->pluck('id'))
            ->where('type_file', 'attachment')
            ->orderBy('id')
            ->get();

        $attachmentsById = $attachments->keyBy('id');
        $rootFor = function (DocumentFile $file) use ($attachmentsById): int {
            $current = $file;

            while ($current->source_file_id !== null && $attachmentsById->has($current->source_file_id)) {
                $current = $attachmentsById->get($current->source_file_id);
            }

            return (int) ($current->source_file_id ?? $current->id);
        };

        return $attachments
            ->sortBy(fn (DocumentFile $file): string => sprintf(
                '%010d-%010d',
                $revisionByDocumentId->get($file->t_document_id, 0),
                $file->id,
            ))
            ->reduce(function (Collection $carry, DocumentFile $file) use ($rootFor): Collection {
                $carry->put($rootFor($file), $file);

                return $carry;
            }, collect())
            ->values()
            ->sortBy(fn (DocumentFile $file): string => $file->attachmentSortKey())
            ->values();
    }

    public function finalArtifacts(): HasMany
    {
        return $this->hasMany(DocumentFinalArtifact::class, 't_document_id');
    }
}
