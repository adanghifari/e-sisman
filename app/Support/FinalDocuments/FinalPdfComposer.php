<?php

namespace App\Support\FinalDocuments;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use setasign\Fpdi\PdfParser\PdfParserException;
use setasign\Fpdi\Tcpdf\Fpdi;
use Throwable;

class FinalPdfComposer
{
    private const HEADER_HEIGHT = 30.0;

    private const HEADER_MARGIN_TOP = 8.0;

    private const HORIZONTAL_MARGIN = 9.0;

    private const FOOTER_MARGIN_BOTTOM = 8.0;

    private const MIN_STAMP_WIDTH = 120.0;

    public function __construct(
        private readonly PdfPageGeometry $geometry = new PdfPageGeometry,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function compose(
        array $payload,
        string $coverPdf,
        ?string $approvalSheetPdf,
        string $bodyPdfPath,
        PdfCompositionMode $mode,
        PdfDocumentContext $context = PdfDocumentContext::FINAL_DOCUMENT,
    ): FinalPdfCompositionResult {
        if (! is_file($bodyPdfPath)) {
            throw new PdfCompositionException('Source body PDF is missing.');
        }

        if ($context->includesApprovalSheet() && $approvalSheetPdf === null) {
            throw new PdfCompositionException('Approval sheet PDF is required for final document composition.');
        }

        $tempFiles = [];

        try {
            $coverPath = $this->writeTempPdf($coverPdf, 'cover-', $tempFiles);
            $approvalSheetPath = $approvalSheetPdf !== null
                ? $this->writeTempPdf($approvalSheetPdf, 'approval-sheet-', $tempFiles)
                : null;
            $pdf = $this->makePdf();

            $coverPages = $this->importPdf($pdf, $coverPath, stampBody: false, payload: $payload, mode: $mode);
            $approvalSheetPages = $context->includesApprovalSheet()
                ? $this->importPdf($pdf, (string) $approvalSheetPath, stampBody: false, payload: $payload, mode: $mode)
                : ['count' => 0, 'pages' => []];
            $bodyPages = $this->importPdf($pdf, $bodyPdfPath, stampBody: true, payload: $payload, mode: $mode);

            if ($bodyPages['count'] < 1) {
                throw new PdfCompositionException('Source body PDF contains no pages.');
            }

            return new FinalPdfCompositionResult(
                pdf: $pdf->Output('', 'S'),
                coverPages: $coverPages['count'],
                approvalSheetPages: $approvalSheetPages['count'],
                bodyPagesCount: $bodyPages['count'],
                bodyPages: $bodyPages['pages'],
            );
        } catch (PdfCompositionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new PdfCompositionException('PDF composition failed: '.$this->safeErrorMessage($exception), previous: $exception);
        } finally {
            foreach ($tempFiles as $tempFile) {
                if (is_file($tempFile)) {
                    @unlink($tempFile);
                }
            }

            $tempDirectory = storage_path('app/private/documents/final/tmp');
            if (is_dir($tempDirectory) && count(scandir($tempDirectory) ?: []) <= 2) {
                @rmdir($tempDirectory);
            }
        }
    }

    public function safeArea(): PdfSafeArea
    {
        return new PdfSafeArea(
            left: self::HORIZONTAL_MARGIN,
            top: 45.0,
            right: self::HORIZONTAL_MARGIN,
            bottom: 20.0,
        );
    }

    private function makePdf(): Fpdi
    {
        $pdf = new Fpdi('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->setAutoPageBreak(false, 0);
        $pdf->setMargins(0, 0, 0);
        $pdf->setCellPaddings(0, 0, 0, 0);
        $pdf->setCompression(false);

        return $pdf;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{count: int, pages: array<int, array<string, mixed>>}
     */
    private function importPdf(Fpdi $pdf, string $path, bool $stampBody, array $payload, PdfCompositionMode $mode): array
    {
        $pageCount = $pdf->setSourceFile($path);
        $pages = [];

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $template = $pdf->importPage($pageNumber);
            $size = $pdf->getTemplateSize($template);
            $pageWidth = (float) $size['width'];
            $pageHeight = (float) $size['height'];
            $orientation = (string) $size['orientation'];

            $pdf->AddPage($orientation, [$pageWidth, $pageHeight]);

            $placement = $stampBody && $mode === PdfCompositionMode::FIT_TO_SAFE_AREA
                ? $this->geometry->fitToSafeArea($pageWidth, $pageHeight, $pageWidth, $pageHeight, $this->safeArea())
                : $this->geometry->preserve($pageWidth, $pageHeight, $pageWidth, $pageHeight);

            $pdf->useImportedPage(
                $template,
                $placement->x,
                $placement->y,
                $placement->width,
                $placement->height,
            );

            if ($stampBody) {
                $this->stampBodyHeaderFooter($pdf, $payload, $pageNumber, $pageCount, $pageWidth, $pageHeight);
                $pages[] = [
                    'source_page' => $pageNumber,
                    'page_width' => $pageWidth,
                    'page_height' => $pageHeight,
                    'orientation' => $orientation,
                    'mode' => $mode->value,
                    'placement' => [
                        'x' => $placement->x,
                        'y' => $placement->y,
                        'width' => $placement->width,
                        'height' => $placement->height,
                        'scale' => $placement->scale,
                    ],
                    'page_label' => "{$pageNumber} dari {$pageCount}",
                ];
            }
        }

        return ['count' => $pageCount, 'pages' => $pages];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function stampBodyHeaderFooter(
        Fpdi $pdf,
        array $payload,
        int $currentPage,
        int $totalBodyPages,
        float $pageWidth,
        float $pageHeight,
    ): void {
        $contentWidth = $pageWidth - (self::HORIZONTAL_MARGIN * 2);

        if ($contentWidth < self::MIN_STAMP_WIDTH || $pageHeight < 90.0) {
            throw new PdfCompositionException('Page is too small for official header/footer stamp.');
        }

        $x = self::HORIZONTAL_MARGIN;
        $y = self::HEADER_MARGIN_TOP;
        $leftWidth = $contentWidth * 0.325;
        $centerWidth = $contentWidth * 0.33;
        $labelWidth = $contentWidth * 0.13;
        $valueWidth = $contentWidth - $leftWidth - $centerWidth - $labelWidth;
        $rowHeight = self::HEADER_HEIGHT / 4;

        $document = $payload['document'] ?? [];
        $documentType = $this->upper($document['type'] ?? ($document['level']['document_name'] ?? null));
        $documentName = $this->upper($document['name'] ?? null);
        $documentNumber = $this->value($document['number'] ?? null);
        $revision = $this->value($document['revision_label'] ?? ($document['revision'] ?? null));
        $publishedAt = $this->formatDate($document['published_at'] ?? null);

        $pdf->SetDrawColor(120, 120, 120);
        $pdf->SetTextColor(118, 118, 118);
        $pdf->SetLineWidth(0.2);
        $pdf->Rect($x, $y, $contentWidth, self::HEADER_HEIGHT);
        $pdf->Line($x + $leftWidth, $y, $x + $leftWidth, $y + self::HEADER_HEIGHT);
        $pdf->Line($x + $leftWidth + $centerWidth, $y, $x + $leftWidth + $centerWidth, $y + self::HEADER_HEIGHT);
        $pdf->Line($x + $leftWidth + $centerWidth + $labelWidth, $y, $x + $leftWidth + $centerWidth + $labelWidth, $y + self::HEADER_HEIGHT);

        for ($row = 1; $row <= 3; $row++) {
            $lineY = $y + ($rowHeight * $row);
            $pdf->Line($x + $leftWidth + $centerWidth, $lineY, $x + $contentWidth, $lineY);
        }

        $pdf->Line($x + $leftWidth, $y + (self::HEADER_HEIGHT / 2), $x + $leftWidth + $centerWidth, $y + (self::HEADER_HEIGHT / 2));

        $logoPath = public_path('image/kopsuratlogo.jpeg');
        if (is_file($logoPath)) {
            $pdf->Image($logoPath, $x + 1.8, $y + 5.5, min(60.0, $leftWidth - 3.6), 0, 'JPEG');
        } else {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->MultiCell($leftWidth, 8, 'KRAKATAU INTERNATIONAL PORT', 0, 'C', false, 1, $x, $y + 7);
        }

        $companyLineY = $y + 22.5;
        $pdf->Line($x, $companyLineY, $x + $leftWidth, $companyLineY);
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->MultiCell($leftWidth, 6, 'PT KRAKATAU BANDAR SAMUDERA', 0, 'C', false, 1, $x, $companyLineY + 2.2);

        $centerX = $x + $leftWidth;
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->MultiCell($centerWidth, 5.5, $documentType, 0, 'C', false, 1, $centerX, $y + 3.4);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->MultiCell($centerWidth, 5.5, 'SISTEM MANAJEMEN KBS', 0, 'C', false, 1, $centerX, $y + 8.8);
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->MultiCell($centerWidth, 12, $documentName, 0, 'C', false, 1, $centerX, $y + 18.8);

        $labels = ['No. Dok.', 'Revisi', 'Tgl. Terbit', 'Halaman'];
        $values = [$documentNumber, $revision, $publishedAt, "{$currentPage} dari {$totalBodyPages}"];
        $labelX = $x + $leftWidth + $centerWidth;
        $valueX = $labelX + $labelWidth;

        for ($row = 0; $row < 4; $row++) {
            $rowY = $y + ($rowHeight * $row) + 2;
            $pdf->SetFont('helvetica', '', 9);
            $pdf->MultiCell($labelWidth, 5, $labels[$row], 0, 'L', false, 1, $labelX + 2, $rowY);
            $pdf->MultiCell($valueWidth, 5, ': '.$values[$row], 0, 'L', false, 1, $valueX + 2, $rowY);
        }

        $footerX = self::HORIZONTAL_MARGIN + 22;
        $footerWidth = max(10.0, $pageWidth - (2 * ($footerX)));
        $footerY = $pageHeight - self::FOOTER_MARGIN_BOTTOM;
        $pdf->SetDrawColor(79, 92, 255);
        $pdf->SetLineWidth(0.15);
        $pdf->Line($footerX, $footerY, $footerX + $footerWidth, $footerY);
        $pdf->SetTextColor(102, 102, 102);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->MultiCell(
            $footerWidth,
            4,
            'Sistem Dokumentasi PT Krakatau Bandar Samudera berstandar Sistem Manajemen Terintegrasi',
            0,
            'C',
            false,
            1,
            $footerX,
            $footerY + 1,
        );
    }

    /**
     * @param  array<int, string>  $tempFiles
     */
    private function writeTempPdf(string $contents, string $prefix, array &$tempFiles): string
    {
        if (! str_starts_with($contents, '%PDF-')) {
            throw new PdfCompositionException('Generated system page PDF is invalid.');
        }

        $directory = storage_path('app/private/documents/final/tmp');
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new PdfCompositionException('Temporary PDF directory could not be created.');
        }

        $path = tempnam($directory, $prefix);
        if ($path === false) {
            throw new PdfCompositionException('Temporary PDF file could not be created.');
        }

        $pdfPath = $path.'.pdf';
        rename($path, $pdfPath);
        file_put_contents($pdfPath, $contents);
        $tempFiles[] = $pdfPath;

        return $pdfPath;
    }

    private function safeErrorMessage(Throwable $exception): string
    {
        if ($exception instanceof PdfParserException) {
            return 'Source PDF could not be parsed.';
        }

        return $exception->getMessage() ?: 'Unknown PDF composition error.';
    }

    private function value(mixed $value): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : '-';
    }

    private function upper(mixed $value): string
    {
        return Str::upper($this->value($value));
    }

    private function formatDate(mixed $value): string
    {
        if (blank($value)) {
            return '-';
        }

        try {
            return Carbon::parse($value)->format('d - m - Y');
        } catch (Throwable) {
            return (string) $value;
        }
    }
}
