<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable([
    'source_document_id',
    'source_imported_existing_document_id',
    'target_document_id',
    'target_imported_existing_document_id',
    'relation_type',
    'keterangan',
    'created_by',
])]
class DocumentRelation extends Model
{
    public const REFERENCES = 'references';

    public const SUPERSEDED_BY = 'superseded_by';

    public const RELATION_TYPES = [
        self::REFERENCES,
        self::SUPERSEDED_BY,
    ];

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'source_document_id');
    }

    public function sourceImportedDocument(): BelongsTo
    {
        return $this->belongsTo(ImportedExistingDocument::class, 'source_imported_existing_document_id');
    }

    public function targetDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'target_document_id');
    }

    public function targetImportedDocument(): BelongsTo
    {
        return $this->belongsTo(ImportedExistingDocument::class, 'target_imported_existing_document_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function source(): Document|ImportedExistingDocument|null
    {
        return $this->sourceDocument ?: $this->sourceImportedDocument;
    }

    public function target(): Document|ImportedExistingDocument|null
    {
        return $this->targetDocument ?: $this->targetImportedDocument;
    }

    public static function targetColumnsForReference(?string $reference): ?array
    {
        if (! filled($reference)) {
            return null;
        }

        $reference = (string) $reference;

        if (ctype_digit($reference)) {
            return [
                'target_document_id' => (int) $reference,
                'target_imported_existing_document_id' => null,
            ];
        }

        $parts = explode('-', $reference, 2);

        if (count($parts) !== 2 || ! ctype_digit($parts[1])) {
            return null;
        }

        return match ($parts[0]) {
            'existing', 'workflow' => [
                'target_document_id' => (int) $parts[1],
                'target_imported_existing_document_id' => null,
            ],
            'imported' => [
                'target_document_id' => null,
                'target_imported_existing_document_id' => (int) $parts[1],
            ],
            default => null,
        };
    }

    public static function referenceValue(?int $documentId, ?int $importedDocumentId): ?string
    {
        if ($documentId !== null) {
            return 'existing-'.$documentId;
        }

        if ($importedDocumentId !== null) {
            return 'imported-'.$importedDocumentId;
        }

        return null;
    }

    public static function targetReferenceValue(self $relation): ?string
    {
        return self::referenceValue(
            $relation->target_document_id,
            $relation->target_imported_existing_document_id,
        );
    }

    /**
     * @return array<string, int|null>
     */
    public static function targetColumnsFromRelation(self $relation): array
    {
        return [
            'target_document_id' => $relation->target_document_id,
            'target_imported_existing_document_id' => $relation->target_imported_existing_document_id,
        ];
    }

    public static function syncDocumentSourceReference(Document $document, ?string $reference, ?int $createdBy = null): ?self
    {
        $targetColumns = self::targetColumnsForReference($reference);

        if ($targetColumns === null) {
            $document->outgoingRelations()
                ->where('relation_type', self::REFERENCES)
                ->delete();

            return null;
        }

        return $document->outgoingRelations()->updateOrCreate(
            ['relation_type' => self::REFERENCES],
            $targetColumns + [
                'keterangan' => 'Dokumen acuan prosedur.',
                'created_by' => $createdBy,
            ],
        );
    }

    public static function copyReferenceToDocumentSource(Document $document, Document|ImportedExistingDocument $source, ?int $createdBy = null): ?self
    {
        $relation = $source->outgoingRelations()
            ->where('relation_type', self::REFERENCES)
            ->first();

        if ($relation === null) {
            $document->outgoingRelations()
                ->where('relation_type', self::REFERENCES)
                ->delete();

            return null;
        }

        return $document->outgoingRelations()->updateOrCreate(
            ['relation_type' => self::REFERENCES],
            self::targetColumnsFromRelation($relation) + [
                'keterangan' => 'Disalin dari relasi acuan dokumen sumber.',
                'created_by' => $createdBy,
            ],
        );
    }

    public static function supersedeImportedSourceWithDocument(ImportedExistingDocument $source, Document $target, ?int $createdBy = null): self
    {
        return self::query()->updateOrCreate(
            [
                'source_imported_existing_document_id' => $source->id,
                'relation_type' => self::SUPERSEDED_BY,
            ],
            [
                'source_document_id' => null,
                'target_document_id' => $target->id,
                'target_imported_existing_document_id' => null,
                'keterangan' => 'Digantikan oleh revisi V2 hasil approval.',
                'created_by' => $createdBy,
            ],
        );
    }

    public static function supersedeDocumentSourceWithDocument(Document $source, Document $target, ?int $createdBy = null): self
    {
        return self::query()->updateOrCreate(
            [
                'source_document_id' => $source->id,
                'relation_type' => self::SUPERSEDED_BY,
            ],
            [
                'source_imported_existing_document_id' => null,
                'target_document_id' => $target->id,
                'target_imported_existing_document_id' => null,
                'keterangan' => 'Digantikan oleh revisi workflow hasil approval.',
                'created_by' => $createdBy,
            ],
        );
    }

    public static function targetDocumentNumber(?string $reference): ?string
    {
        $targetColumns = self::targetColumnsForReference($reference);

        if ($targetColumns === null) {
            return null;
        }

        if ($targetColumns['target_document_id'] !== null) {
            return Document::query()
                ->whereKey($targetColumns['target_document_id'])
                ->value('nomor_dokumen');
        }

        if ($targetColumns['target_imported_existing_document_id'] !== null) {
            return ImportedExistingDocument::query()
                ->whereKey($targetColumns['target_imported_existing_document_id'])
                ->value('nomor_dokumen');
        }

        throw new InvalidArgumentException('Reference target must point to exactly one document source.');
    }
}
