<?php

namespace App\Support\FinalDocuments;

use App\Models\Approval;
use App\Models\Document;
use App\Models\DocumentFile;
use App\Models\DocumentFinalArtifact;
use App\Models\StatusDocument;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class FinalArtifactGenerator
{
    /**
     * Prepare a final artifact record and normalized renderer payload.
     *
     * This method intentionally does not render, merge, stamp, or write a PDF.
     */
    public function prepare(
        Document $document,
        ?User $generatedBy = null,
        string $artifactType = DocumentFinalArtifact::TYPE_FINAL_DOCUMENT,
    ): FinalArtifactPreparation {
        $document->loadMissing([
            'status',
            'documentLevel',
            'documentType',
            'businessProcess',
            'businessFunction',
            'departments',
            'officialPreparer.department',
            'files',
            'approvals.status',
        ]);

        $this->assertEligible($document);

        $sourceFile = $this->resolveSourceDocumentFile($document);

        if (! Storage::disk('local')->exists($sourceFile->path_file)) {
            throw new RuntimeException('Source document file is missing from storage.');
        }

        $payload = $this->buildPayload($document, $sourceFile);
        $artifact = $this->createPendingArtifact($document, $sourceFile, $generatedBy, $artifactType);

        return new FinalArtifactPreparation($artifact, $payload);
    }

    public function assertEligible(Document $document): void
    {
        $document->loadMissing('status');

        if ($document->status?->nama_status !== StatusDocument::APPROVED) {
            throw new DomainException('Only approved documents can be prepared as final artifacts.');
        }

        if ($document->request_type === 'obsolete') {
            throw new DomainException('Obsolete request documents cannot be prepared as final artifacts.');
        }
    }

    public function resolveSourceDocumentFile(Document $document): DocumentFile
    {
        $document->loadMissing('files');

        $preferredTypes = $document->request_type === 'revision'
            ? ['revision_content']
            : match ($document->documentLevel?->kode) {
                'level-1' => ['imported_document', 'filled_template'],
                default => ['filled_template', 'imported_document'],
            };

        $sourceFile = $document->files
            ->whereIn('type_file', $preferredTypes)
            ->sortBy(fn (DocumentFile $file): int => array_search($file->type_file, $preferredTypes, true))
            ->first();

        if ($sourceFile === null) {
            throw new DomainException('No main source document file is available for final artifact preparation.');
        }

        return $sourceFile;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(Document $document, DocumentFile $sourceFile): array
    {
        $document->loadMissing([
            'documentLevel',
            'documentType',
            'businessProcess',
            'businessFunction',
            'departments',
            'officialPreparer.department',
            'approvals.status',
        ]);

        return [
            'document' => [
                'id' => $document->id,
                'name' => $document->nama_dokumen,
                'number' => $document->nomor_dokumen,
                'revision' => $document->nomor_revisi,
                'revision_label' => $document->formatted_revision,
                'revision_form_number' => $document->nomor_lembar_revisi,
                'published_at' => $document->tanggal_terbit,
                'approved_at' => $document->approved_at,
                'type' => $document->documentType?->nama_types,
                'level' => [
                    'id' => $document->documentLevel?->id,
                    'code' => $document->documentLevel?->kode,
                    'name' => $document->documentLevel?->nama_level,
                    'document_name' => $document->documentLevel?->nama_dokumen,
                ],
                'business_process' => $document->businessProcess?->nama_proses_bisnis,
                'business_function' => $document->businessFunction?->nama_proses_fungsi,
                'departments' => $document->departments
                    ->map(fn ($department): array => [
                        'id' => $department->id,
                        'name' => $department->nama_department,
                        'code' => $department->kode_department,
                    ])
                    ->values()
                    ->all(),
            ],
            'preparers' => $this->collectPreparers($document),
            'approvals' => $this->collectApprovals($document),
            'source' => [
                'id' => $sourceFile->id,
                'type' => $sourceFile->type_file,
                'path_file' => $sourceFile->path_file,
                'original_file_name' => $sourceFile->original_file_name,
                'stored_file_name' => $sourceFile->stored_file_name,
                'file_size' => $sourceFile->file_size,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function collectPreparers(Document $document): array
    {
        $document->loadMissing('officialPreparer.department');

        if ($document->officialPreparer === null) {
            return [];
        }

        return [[
            'id' => $document->officialPreparer->id,
            'name' => $document->official_preparer_name_snapshot,
            'position' => $document->official_preparer_position_snapshot,
            'department' => $document->official_preparer_department_snapshot,
            'department_code' => null,
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function collectApprovals(Document $document): array
    {
        $document->loadMissing('approvals.status');

        return $document->approvals
            ->filter(fn (Approval $approval): bool => $approval->responded_at !== null)
            ->map(fn (Approval $approval): array => [
                'stage_name' => $approval->stage_name_snapshot ?? $approval->stages,
                'stage_order' => $approval->stage_order_snapshot,
                'sort_order' => $approval->stage_order_snapshot ?? $this->stageOrderFromCurrentFlow($document, $approval),
                'approver' => [
                    'name' => $approval->approver_name_snapshot,
                    'position' => $approval->approver_position_snapshot,
                    'department' => $approval->approver_department_snapshot,
                    'responded_at' => $approval->responded_at,
                ],
            ])
            ->groupBy(fn (array $approval): string => ($approval['sort_order'] ?? 'unknown').'|'.($approval['stage_name'] ?? ''))
            ->map(function (Collection $stageApprovals): array {
                $firstApproval = $stageApprovals->first();

                return [
                    'stage_name' => $firstApproval['stage_name'],
                    'stage_order' => $firstApproval['stage_order'],
                    'approvers' => $stageApprovals
                        ->map(fn (array $approval): array => $approval['approver'])
                        ->values()
                        ->all(),
                    'sort_order' => $firstApproval['sort_order'],
                ];
            })
            ->sortBy(fn (array $stage): string => sprintf(
                '%010d-%s',
                $stage['sort_order'] ?? PHP_INT_MAX,
                $stage['stage_name'] ?? '',
            ))
            ->map(fn (array $stage): array => [
                'stage_name' => $stage['stage_name'],
                'stage_order' => $stage['stage_order'],
                'approvers' => $stage['approvers'],
            ])
            ->values()
            ->all();
    }

    private function createPendingArtifact(
        Document $document,
        DocumentFile $sourceFile,
        ?User $generatedBy,
        string $artifactType,
    ): DocumentFinalArtifact {
        return DB::transaction(function () use ($document, $sourceFile, $generatedBy, $artifactType): DocumentFinalArtifact {
            $generationNumber = ((int) DocumentFinalArtifact::query()
                ->where('t_document_id', $document->id)
                ->where('artifact_type', $artifactType)
                ->lockForUpdate()
                ->max('generation_number')) + 1;
            $fileName = $this->generatedFileName($document, $generationNumber, $artifactType);

            return DocumentFinalArtifact::query()->create([
                't_document_id' => $document->id,
                'source_document_file_id' => $sourceFile->id,
                'artifact_type' => $artifactType,
                'generation_number' => $generationNumber,
                'generation_status' => DocumentFinalArtifact::STATUS_PENDING,
                'path_file' => "documents/final/{$document->id}/{$artifactType}/{$generationNumber}/{$fileName}",
                'generated_file_name' => $fileName,
                'generated_by' => $generatedBy?->id,
            ]);
        });
    }

    private function generatedFileName(Document $document, int $generationNumber, string $artifactType): string
    {
        $baseName = Str::slug($document->nomor_dokumen ?: $document->nama_dokumen) ?: 'document';
        $prefix = $artifactType === DocumentFinalArtifact::TYPE_APPROVAL_SHEET
            ? 'approval-sheet'
            : 'final';

        return "{$prefix}-{$baseName}-g{$generationNumber}.pdf";
    }

    private function stageOrderFromCurrentFlow(Document $document, Approval $approval): ?int
    {
        $document->loadMissing([
            'documentLevel.approvalFlows.stages',
            'revisedFrom.documentLevel.approvalFlows.stages',
        ]);

        $documentLevel = $document->documentLevel?->kode === 'level-4' && $document->revisedFrom?->documentLevel !== null
            ? $document->revisedFrom->documentLevel
            : $document->documentLevel;

        return $documentLevel
            ?->approvalFlows
            ->flatMap(fn ($flow) => $flow->stages)
            ->first(fn ($stage): bool => ($stage->display_label ?: 'Approval') === $approval->stages)
            ?->stage_order;
    }
}
