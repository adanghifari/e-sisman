<?php

namespace App\Support\FinalDocuments;

use App\Models\Document;
use App\Models\DocumentFinalArtifact;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class FinalDocumentArtifactGenerator
{
    public function __construct(
        private readonly FinalArtifactGenerator $finalArtifactGenerator,
        private readonly CoverPdfRenderer $coverPdfRenderer,
        private readonly ApprovalSheetPdfRenderer $approvalSheetPdfRenderer,
        private readonly FinalPdfComposer $finalPdfComposer,
    ) {}

    public function generate(
        Document $document,
        ?User $generatedBy = null,
        PdfCompositionMode $mode = PdfCompositionMode::PRESERVE,
    ): DocumentFinalArtifact {
        $preparation = $this->finalArtifactGenerator->prepare(
            $document,
            $generatedBy,
            DocumentFinalArtifact::TYPE_FINAL_DOCUMENT,
        );
        $artifact = $preparation->artifact;

        $artifact->update([
            'generation_status' => DocumentFinalArtifact::STATUS_PROCESSING,
            'generation_error' => null,
        ]);

        try {
            $sourcePath = Storage::disk('local')->path($preparation->payload['source']['path_file']);
            $composition = $this->finalPdfComposer->compose(
                payload: $preparation->payload,
                coverPdf: $this->coverPdfRenderer->render($preparation->payload),
                approvalSheetPdf: $this->approvalSheetPdfRenderer->render($preparation->payload),
                bodyPdfPath: $sourcePath,
                mode: $mode,
            );

            if (! Storage::disk('local')->put($artifact->path_file, $composition->pdf)) {
                throw new RuntimeException('Final document PDF could not be written to storage.');
            }

            $artifact->update([
                'generation_status' => DocumentFinalArtifact::STATUS_GENERATED,
                'checksum_sha256' => hash('sha256', $composition->pdf),
                'file_size' => strlen($composition->pdf),
                'generated_at' => now(),
                'generation_error' => null,
            ]);

            return $artifact->refresh();
        } catch (Throwable $exception) {
            $artifact->update([
                'generation_status' => DocumentFinalArtifact::STATUS_FAILED,
                'generation_error' => $this->safeGenerationError($exception),
            ]);

            throw $exception;
        }
    }

    private function safeGenerationError(Throwable $exception): string
    {
        if ($exception instanceof PdfCompositionException) {
            return $exception->getMessage();
        }

        return 'Final document generation failed.';
    }
}
