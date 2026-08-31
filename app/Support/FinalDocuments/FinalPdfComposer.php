<?php

namespace App\Support\FinalDocuments;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\PdfParser\PdfParserException;
use setasign\Fpdi\Tcpdf\Fpdi;
use Symfony\Component\Process\Process;
use Throwable;

class FinalPdfComposer
{
    private const HEADER_HEIGHT = 30.0;

    private const HEADER_MARGIN_TOP = 8.0;

    private const HORIZONTAL_MARGIN = 9.0;

    private const FOOTER_MARGIN_BOTTOM = 8.0;

    private const MIN_STAMP_WIDTH = 120.0;

    private const BODY_CONTENT_TOP = 45.0;

    private const BODY_CONTENT_BOTTOM = 14.0;

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
            $bodyPageCount = $this->countPdfPages($bodyPdfPath);
            $attachments = $this->attachmentPayloads($payload, $tempFiles);
            $attachmentPageCount = collect($attachments)->sum(fn (array $attachment): int => (int) $attachment['page_count']);
            $attachmentListPageCount = $attachments === [] ? 0 : $this->attachmentListPageCount($attachments);
            $totalBodyPages = $bodyPageCount + $attachmentListPageCount + $attachmentPageCount;

            $bodyPages = $this->importPdf(
                $pdf,
                $bodyPdfPath,
                stampBody: true,
                payload: $payload,
                mode: $mode,
                bodyPageOffset: 0,
                totalBodyPages: $totalBodyPages,
            );

            if ($bodyPages['count'] < 1) {
                throw new PdfCompositionException('Source body PDF contains no pages.');
            }

            $attachmentListPages = $attachments === []
                ? ['count' => 0, 'pages' => []]
                : $this->appendAttachmentListPages(
                    $pdf,
                    $payload,
                    $attachments,
                    $bodyPages['count'],
                    $totalBodyPages,
                );
            $attachmentPages = $attachments === []
                ? ['count' => 0, 'pages' => []]
                : $this->appendAttachmentPdfs(
                    $pdf,
                    $payload,
                    $attachments,
                    $bodyPages['count'] + $attachmentListPages['count'],
                    $totalBodyPages,
                );
            $mergedBodyPages = array_merge(
                $bodyPages['pages'],
                $attachmentListPages['pages'],
                $attachmentPages['pages'],
            );

            return new FinalPdfCompositionResult(
                pdf: $pdf->Output('', 'S'),
                coverPages: $coverPages['count'],
                approvalSheetPages: $approvalSheetPages['count'],
                bodyPagesCount: $totalBodyPages,
                bodyPages: $mergedBodyPages,
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
            top: 23.0,
            right: self::HORIZONTAL_MARGIN,
            bottom: 10.0,
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
    private function importPdf(
        Fpdi $pdf,
        string $path,
        bool $stampBody,
        array $payload,
        PdfCompositionMode $mode,
        int $bodyPageOffset = 0,
        ?int $totalBodyPages = null,
    ): array
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

            $placement = match (true) {
                $stampBody && $mode === PdfCompositionMode::FIT_TO_SAFE_AREA => $this->geometry->fitToSafeArea(
                    $pageWidth,
                    $pageHeight,
                    $pageWidth,
                    $pageHeight,
                    $this->safeArea(),
                ),
                $stampBody && $mode === PdfCompositionMode::FIT_WIDTH_TO_SAFE_TOP => $this->geometry->fitWidthToSafeTop(
                    $pageWidth,
                    $pageHeight,
                    $pageWidth,
                    $pageHeight,
                    $this->safeArea(),
                ),
                default => $this->geometry->preserve($pageWidth, $pageHeight, $pageWidth, $pageHeight),
            };

            $pdf->useImportedPage(
                $template,
                $placement->x,
                $placement->y,
                $placement->width,
                $placement->height,
            );

            if ($stampBody) {
                $bodyPageNumber = $bodyPageOffset + $pageNumber;
                $this->stampBodyHeaderFooter($pdf, $payload, $bodyPageNumber, $totalBodyPages ?? $pageCount, $pageWidth, $pageHeight);
                $pages[] = [
                    'source_page' => $pageNumber,
                    'page_width' => $pageWidth,
                    'page_height' => $pageHeight,
                    'orientation' => $orientation,
                    'mode' => $mode->value,
                    'header' => 'standard',
                    'placement' => [
                        'x' => $placement->x,
                        'y' => $placement->y,
                        'width' => $placement->width,
                        'height' => $placement->height,
                        'scale' => $placement->scale,
                    ],
                    'page_label' => "{$bodyPageNumber} dari ".($totalBodyPages ?? $pageCount),
                ];
            }
        }

        return ['count' => $pageCount, 'pages' => $pages];
    }

    /**
     * @param  array<int, array<string, mixed>>  $attachments
     * @return array{count: int, pages: array<int, array<string, mixed>>}
     */
    private function appendAttachmentListPages(
        Fpdi $pdf,
        array $payload,
        array $attachments,
        int $bodyPageOffset,
        int $totalBodyPages,
    ): array {
        $pageWidth = 210.0;
        $pageHeight = 297.0;
        $pages = [];
        $chunks = array_chunk($attachments, 22);

        foreach ($chunks as $chunkIndex => $chunk) {
            $bodyPageNumber = $bodyPageOffset + $chunkIndex + 1;
            $printedAttachmentTitles = [];
            $pdf->AddPage('P', [$pageWidth, $pageHeight]);
            $this->stampBodyHeaderFooter($pdf, $payload, $bodyPageNumber, $totalBodyPages, $pageWidth, $pageHeight);

            $x = self::HORIZONTAL_MARGIN + 8;
            $y = self::BODY_CONTENT_TOP + 4;
            $numberWidth = 18.0;
            $contentWidth = $pageWidth - ($x * 2);

            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->MultiCell($numberWidth, 7, '7.', 0, 'L', false, 0, $x, $y);
            $pdf->MultiCell($contentWidth - $numberWidth, 7, 'LAMPIRAN', 0, 'L', false, 1, $x + $numberWidth, $y);

            $pdf->SetFont('helvetica', '', 10);
            $rowY = $y + 11;

            foreach ($chunk as $attachment) {
                $number = (int) ($attachment['number'] ?? 0);
                $title = $this->attachmentListTitle($attachment, $payload);
                $printedAttachmentTitles[] = $title;
                $pdf->MultiCell($numberWidth, 7, "7.{$number}", 0, 'L', false, 0, $x, $rowY);
                $pdf->MultiCell($contentWidth - $numberWidth, 7, $title, 0, 'L', false, 1, $x + $numberWidth, $rowY);
                $rowY += max(7.0, $pdf->getLastH());
            }

            $pages[] = [
                'source_page' => null,
                'page_width' => $pageWidth,
                'page_height' => $pageHeight,
                'orientation' => 'P',
                'mode' => 'generated_attachment_list',
                'header' => 'standard',
                'attachment_titles' => $printedAttachmentTitles,
                'placement' => null,
                'page_label' => "{$bodyPageNumber} dari {$totalBodyPages}",
            ];
        }

        return ['count' => count($chunks), 'pages' => $pages];
    }

    /**
     * @param  array<int, array<string, mixed>>  $attachments
     * @return array{count: int, pages: array<int, array<string, mixed>>}
     */
    private function appendAttachmentPdfs(
        Fpdi $pdf,
        array $payload,
        array $attachments,
        int $bodyPageOffset,
        int $totalBodyPages,
    ): array {
        $pages = [];
        $appendedPages = 0;

        foreach ($attachments as $attachment) {
            $pageCount = (int) $attachment['page_count'];

            if (! ($attachment['mergeable'] ?? false)) {
                for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                    $appendedPages++;
                    $header = $this->attachmentHeaderType($attachment);
                    $this->appendAttachmentFallbackPage(
                        $pdf,
                        $payload,
                        $attachment,
                        $bodyPageOffset + $appendedPages,
                        $totalBodyPages,
                        $pageNumber,
                        $pageCount,
                    );

                    $pages[] = [
                        'source_page' => null,
                        'page_width' => 210.0,
                        'page_height' => 297.0,
                        'orientation' => 'P',
                        'mode' => 'attachment_fallback',
                        'header' => $header,
                        'header_page_label' => $this->attachmentHeaderPageLabel(
                            $attachment,
                            $bodyPageOffset + $appendedPages,
                            $totalBodyPages,
                            $pageNumber,
                            $pageCount,
                        ),
                        'attachment_number' => $attachment['number'] ?? null,
                        'attachment_title' => $attachment['title'] ?? null,
                        'placement' => null,
                        'page_label' => ($bodyPageOffset + $appendedPages)." dari {$totalBodyPages}",
                    ];
                }

                continue;
            }

            $path = (string) $attachment['resolved_path'];
            $pdf->setSourceFile($path);

            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $template = $pdf->importPage($pageNumber);
                $size = $pdf->getTemplateSize($template);
                $pageWidth = (float) $size['width'];
                $pageHeight = (float) $size['height'];
                $orientation = (string) $size['orientation'];
                $bodyPageNumber = $bodyPageOffset + $appendedPages + 1;

                $pdf->AddPage($orientation, [$pageWidth, $pageHeight]);
                $header = $this->stampAttachmentHeaderFooter(
                    $pdf,
                    $payload,
                    $attachment,
                    $bodyPageNumber,
                    $totalBodyPages,
                    $pageWidth,
                    $pageHeight,
                    $pageNumber,
                    $pageCount,
                );
                $this->stampAttachmentTitle($pdf, $attachment, $pageWidth);

                $availableHeight = $pageHeight - self::BODY_CONTENT_TOP - self::BODY_CONTENT_BOTTOM - 10;
                $placement = $this->geometry->fitToSafeArea(
                    $pageWidth,
                    $pageHeight,
                    $pageWidth,
                    $pageHeight,
                    new PdfSafeArea(
                        left: self::HORIZONTAL_MARGIN,
                        top: self::BODY_CONTENT_TOP + 10,
                        right: self::HORIZONTAL_MARGIN,
                        bottom: max(self::BODY_CONTENT_BOTTOM, $pageHeight - (self::BODY_CONTENT_TOP + 10 + $availableHeight)),
                    ),
                );

                $pdf->useImportedPage(
                    $template,
                    $placement->x,
                    $placement->y,
                    $placement->width,
                    $placement->height,
                );

                $pages[] = [
                    'source_page' => $pageNumber,
                    'page_width' => $pageWidth,
                    'page_height' => $pageHeight,
                    'orientation' => $orientation,
                    'mode' => 'attachment',
                    'header' => $header,
                    'header_page_label' => $this->attachmentHeaderPageLabel(
                        $attachment,
                        $bodyPageNumber,
                        $totalBodyPages,
                        $pageNumber,
                        $pageCount,
                    ),
                    'attachment_number' => $attachment['number'] ?? null,
                    'attachment_title' => $attachment['title'] ?? null,
                    'placement' => [
                        'x' => $placement->x,
                        'y' => $placement->y,
                        'width' => $placement->width,
                        'height' => $placement->height,
                        'scale' => $placement->scale,
                    ],
                    'page_label' => "{$bodyPageNumber} dari {$totalBodyPages}",
                ];
                $appendedPages++;
            }
        }

        return ['count' => $appendedPages, 'pages' => $pages];
    }

    /**
     * @param  array<string, mixed>  $attachment
     */
    private function appendAttachmentFallbackPage(
        Fpdi $pdf,
        array $payload,
        array $attachment,
        int $bodyPageNumber,
        int $totalBodyPages,
        int $attachmentPageNumber = 1,
        int $attachmentPageCount = 1,
    ): void {
        $pageWidth = 210.0;
        $pageHeight = 297.0;

        $pdf->AddPage('P', [$pageWidth, $pageHeight]);
        $this->stampAttachmentHeaderFooter(
            $pdf,
            $payload,
            $attachment,
            $bodyPageNumber,
            $totalBodyPages,
            $pageWidth,
            $pageHeight,
            $attachmentPageNumber,
            $attachmentPageCount,
        );
        $this->stampAttachmentTitle($pdf, $attachment, $pageWidth);

        $x = self::HORIZONTAL_MARGIN + 8;
        $y = self::BODY_CONTENT_TOP + 20;
        $width = $pageWidth - ($x * 2);

        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->MultiCell(
            $width,
            6,
            'File lampiran ini terdaftar, tetapi format PDF-nya belum bisa digabung otomatis oleh sistem. Silakan buka file lampiran asli pada daftar dokumen.',
            0,
            'L',
            false,
            1,
            $x,
            $y,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function attachmentPayloads(array $payload, array &$tempFiles): array
    {
        return collect($payload['attachments'] ?? [])
            ->filter(fn (mixed $attachment): bool => is_array($attachment) && filled($attachment['path_file'] ?? null))
            ->values()
            ->map(function (array $attachment, int $index) use (&$tempFiles): ?array {
                $attachment['number'] = (int) ($attachment['number'] ?? ($index + 1));
                $path = Storage::disk('local')->path((string) $attachment['path_file']);

                if (! $this->isPdfAttachment($attachment)) {
                    return null;
                }

                $attachment['mergeable'] = false;
                $attachment['page_count'] = 1;
                $attachment['resolved_path'] = $path;

                if (! is_file($path)) {
                    return $attachment;
                }

                try {
                    $attachment['page_count'] = (new Fpdi)->setSourceFile($path);
                    $attachment['mergeable'] = true;
                } catch (Throwable) {
                    $normalizedPath = $this->normalizePdfForImport($path, $tempFiles);

                    if ($normalizedPath !== null) {
                        try {
                            $attachment['page_count'] = (new Fpdi)->setSourceFile($normalizedPath);
                            $attachment['resolved_path'] = $normalizedPath;
                            $attachment['mergeable'] = true;
                        } catch (Throwable) {
                            $attachment['mergeable'] = false;
                        }
                    }
                }

                return $attachment;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $tempFiles
     */
    private function normalizePdfForImport(string $path, array &$tempFiles): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $binary = $this->qpdfBinary();

        if ($binary === null) {
            return null;
        }

        $directory = storage_path('app/private/documents/final/tmp');
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return null;
        }

        $tempPath = tempnam($directory, 'qpdf-');
        if ($tempPath === false) {
            return null;
        }

        $normalizedPath = $tempPath.'.pdf';
        rename($tempPath, $normalizedPath);

        $process = new Process([
            $binary,
            '--object-streams=disable',
            '--decode-level=generalized',
            $path,
            $normalizedPath,
        ]);
        $process->setTimeout((float) config('final_documents.qpdf_timeout', 30));
        $process->run();

        if (! $process->isSuccessful() || ! is_file($normalizedPath) || filesize($normalizedPath) === 0) {
            @unlink($normalizedPath);

            return null;
        }

        $tempFiles[] = $normalizedPath;

        return $normalizedPath;
    }

    private function qpdfBinary(): ?string
    {
        $configured = trim((string) config('final_documents.qpdf_binary', 'qpdf'));
        $candidates = [];

        if ($configured !== '' && $configured !== 'qpdf') {
            $candidates[] = $configured;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $candidates = array_merge(
                $candidates,
                glob('C:\Program Files\qpdf *\bin\qpdf.exe') ?: [],
                glob('C:\Program Files (x86)\qpdf *\bin\qpdf.exe') ?: [],
            );
        }

        if ($configured === 'qpdf') {
            $candidates[] = $configured;
        }

        foreach (array_unique($candidates) as $candidate) {
            if ($candidate === 'qpdf' || is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $attachment
     */
    private function isPdfAttachment(array $attachment): bool
    {
        return collect([
            $attachment['original_file_name'] ?? null,
            $attachment['stored_file_name'] ?? null,
            $attachment['path_file'] ?? null,
        ])
            ->filter()
            ->contains(fn (mixed $value): bool => Str::of((string) $value)->lower()->endsWith('.pdf'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $attachments
     */
    private function attachmentListPageCount(array $attachments): int
    {
        return max(1, (int) ceil(count($attachments) / 22));
    }

    private function countPdfPages(string $path): int
    {
        if (! is_file($path)) {
            throw new PdfCompositionException('Source PDF is missing.');
        }

        return (new Fpdi)->setSourceFile($path);
    }

    /**
     * @param  array<string, mixed>  $attachment
     * @param  array<string, mixed>  $payload
     */
    private function attachmentListTitle(array $attachment, array $payload): string
    {
        $number = (int) ($attachment['number'] ?? 0);

        if ($this->attachmentHeaderType($attachment) === 'revision_form') {
            $document = $payload['document'] ?? [];
            $revisionFormNumber = $this->value($document['revision_form_number'] ?? ($document['number'] ?? null));

            return 'Form Lembar Revisi ('.$revisionFormNumber.')';
        }

        return 'Lampiran '.$number.'. '.$this->value($attachment['title'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $attachment
     */
    private function stampAttachmentTitle(Fpdi $pdf, array $attachment, float $pageWidth): void
    {
        $number = (int) ($attachment['number'] ?? 0);
        $title = $this->value($attachment['title'] ?? null);
        $x = self::HORIZONTAL_MARGIN + 8;
        $y = self::BODY_CONTENT_TOP + 1.5;
        $width = $pageWidth - ($x * 2);

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetX($x);
        $pdf->SetY($y);
        $pdf->writeHTMLCell(
            $width,
            7,
            $x,
            $y,
            '<strong>Lampiran '.$number.'.</strong> '.e($title),
            0,
            1,
            false,
            true,
            'L',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $attachment
     */
    private function stampAttachmentHeaderFooter(
        Fpdi $pdf,
        array $payload,
        array $attachment,
        int $currentPage,
        int $totalBodyPages,
        float $pageWidth,
        float $pageHeight,
        int $attachmentPageNumber = 1,
        int $attachmentPageCount = 1,
    ): string {
        if ($this->attachmentHeaderType($attachment) === 'revision_form') {
            $this->stampRevisionFormHeaderFooter(
                $pdf,
                $payload,
                $attachmentPageNumber,
                $attachmentPageCount,
                $pageWidth,
                $pageHeight,
            );

            return 'revision_form';
        }

        $this->stampBodyHeaderFooter($pdf, $payload, $currentPage, $totalBodyPages, $pageWidth, $pageHeight);

        return 'standard';
    }

    /**
     * @param  array<string, mixed>  $attachment
     */
    private function attachmentHeaderType(array $attachment): string
    {
        return ($attachment['type'] ?? null) === 'revision_form' ? 'revision_form' : 'standard';
    }

    /**
     * @param  array<string, mixed>  $attachment
     */
    private function attachmentHeaderPageLabel(
        array $attachment,
        int $currentPage,
        int $totalBodyPages,
        int $attachmentPageNumber,
        int $attachmentPageCount,
    ): string {
        if ($this->attachmentHeaderType($attachment) === 'revision_form') {
            return "{$attachmentPageNumber} dari {$attachmentPageCount}";
        }

        return "{$currentPage} dari {$totalBodyPages}";
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
        $this->stampHeaderLogo($pdf, $logoPath, $x, $y + 5.5, $leftWidth, $y + 7);

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

        $this->stampFooter($pdf, $pageWidth, $pageHeight);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function stampRevisionFormHeaderFooter(
        Fpdi $pdf,
        array $payload,
        int $currentPage,
        int $totalPages,
        float $pageWidth,
        float $pageHeight,
    ): void {
        $contentWidth = $pageWidth - (self::HORIZONTAL_MARGIN * 2);

        if ($contentWidth < self::MIN_STAMP_WIDTH || $pageHeight < 90.0) {
            throw new PdfCompositionException('Page is too small for revision form header/footer stamp.');
        }

        $x = self::HORIZONTAL_MARGIN;
        $y = self::HEADER_MARGIN_TOP;
        $leftWidth = $contentWidth * 0.335;
        $centerWidth = $contentWidth * 0.335;
        $rightWidth = $contentWidth - $leftWidth - $centerWidth;
        $halfHeight = self::HEADER_HEIGHT / 2;
        $document = $payload['document'] ?? [];
        $revisionFormNumber = $this->value($document['revision_form_number'] ?? ($document['number'] ?? null));

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetLineWidth(0.45);
        $pdf->Rect($x, $y, $contentWidth, self::HEADER_HEIGHT);
        $pdf->Line($x + $leftWidth, $y, $x + $leftWidth, $y + self::HEADER_HEIGHT);
        $pdf->Line($x + $leftWidth + $centerWidth, $y, $x + $leftWidth + $centerWidth, $y + self::HEADER_HEIGHT);
        $pdf->Line($x, $y + 22.5, $x + $leftWidth, $y + 22.5);
        $pdf->Line($x + $leftWidth, $y + $halfHeight, $x + $contentWidth, $y + $halfHeight);

        $this->stampHeaderLogo($pdf, public_path('image/kopsuratlogo.jpeg'), $x, $y + 3.5, $leftWidth, $y + 7);

        $pdf->SetFont('helvetica', '', 10);
        $pdf->MultiCell($leftWidth, 6, 'PT. KRAKATAU BANDAR SAMUDERA', 0, 'C', false, 1, $x, $y + 24);

        $centerX = $x + $leftWidth;
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->MultiCell($centerWidth, 8, 'FORM', 0, 'C', false, 1, $centerX, $y + 5.7);
        $pdf->MultiCell($centerWidth, 8, 'LEMBAR REVISI', 0, 'C', false, 1, $centerX, $y + $halfHeight + 5.7);

        $rightX = $centerX + $centerWidth;
        $pdf->SetFont('helvetica', '', 10.5);
        $pdf->MultiCell($rightWidth, 7, 'No. Dok.  :  '.$revisionFormNumber, 0, 'L', false, 1, $rightX + 3, $y + 5.8);
        $pdf->MultiCell($rightWidth, 7, 'Halaman  :  '.$currentPage.' dari '.$totalPages, 0, 'L', false, 1, $rightX + 3, $y + $halfHeight + 5.8);

        $this->stampFooter($pdf, $pageWidth, $pageHeight);
    }

    private function stampHeaderLogo(
        Fpdi $pdf,
        string $logoPath,
        float $x,
        float $y,
        float $leftWidth,
        float $fallbackY,
    ): void {
        if (is_file($logoPath)) {
            $pdf->Image($logoPath, $x + 1.8, $y, min(60.0, $leftWidth - 3.6), 0, 'JPEG');

            return;
        }

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->MultiCell($leftWidth, 8, 'KRAKATAU INTERNATIONAL PORT', 0, 'C', false, 1, $x, $fallbackY);
    }

    private function stampFooter(Fpdi $pdf, float $pageWidth, float $pageHeight): void
    {
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
