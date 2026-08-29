<?php

namespace App\Support\FinalDocuments;

enum PdfDocumentContext: string
{
    case APPROVAL_PREVIEW = 'approval_preview';
    case FINAL_DOCUMENT = 'final_document';

    public function includesApprovalSheet(): bool
    {
        return $this === self::FINAL_DOCUMENT;
    }
}
