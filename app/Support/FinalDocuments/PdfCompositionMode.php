<?php

namespace App\Support\FinalDocuments;

enum PdfCompositionMode: string
{
    case PRESERVE = 'preserve';
    case FIT_TO_SAFE_AREA = 'fit_to_safe_area';
}
