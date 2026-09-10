<?php

namespace App\Support\FinalDocuments;

use App\Models\Document;

enum PdfDocumentContext: string
{
    case APPROVAL_PREVIEW = 'approval_preview';
    case FINAL_DOCUMENT = 'final_document';
    case FINAL_DOCUMENT_WITHOUT_APPROVAL_SHEET = 'final_document_without_approval_sheet';

    public static function finalFor(Document $document): self
    {
        return self::effectiveLevelKey($document) === 'level-1'
            ? self::FINAL_DOCUMENT_WITHOUT_APPROVAL_SHEET
            : self::FINAL_DOCUMENT;
    }

    public static function effectiveLevelKey(Document $document): ?string
    {
        $document->loadMissing('documentLevel', 'revisedFrom.documentLevel');

        if ($document->documentLevel?->kode === 'level-4' && $document->revisedFrom?->documentLevel !== null) {
            return $document->revisedFrom->documentLevel->kode;
        }

        return $document->documentLevel?->kode;
    }

    public function includesApprovalSheet(): bool
    {
        return $this === self::FINAL_DOCUMENT;
    }
}
