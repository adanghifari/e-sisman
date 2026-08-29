<?php

namespace App\Support\FinalDocuments;

readonly class PdfSafeArea
{
    public function __construct(
        public float $left = 9.0,
        public float $top = 45.0,
        public float $right = 9.0,
        public float $bottom = 20.0,
    ) {}
}
