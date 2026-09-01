@php
    $documentType = trim((string) ($document['type'] ?? ''));
    $documentName = trim((string) ($document['name'] ?? ''));
    $documentNumber = trim((string) ($document['number'] ?? ''));
    $revisionLabel = trim((string) ($document['revision_label'] ?? $document['revision'] ?? ''));
    $publishedAt = $document['published_at'] ?? null;
    $qrWriter = new \BaconQrCode\Writer(new \BaconQrCode\Renderer\ImageRenderer(
        new \BaconQrCode\Renderer\RendererStyle\RendererStyle(96, 0),
        new \BaconQrCode\Renderer\Image\SvgImageBackEnd
    ));
    $signatureUrl = app(\App\Support\DigitalSignatures\SignatureVerificationUrl::class);
    $qrDataUri = static function (?int $approvalId) use ($qrWriter, $signatureUrl): ?string {
        if ($approvalId === null) {
            return null;
        }

        $svg = $qrWriter->writeString($signatureUrl->forApproval($approvalId));

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    };
    $formatDate = static function ($value): string {
        if (blank($value)) {
            return '-';
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    };
    $approvalStages = collect($approvalStages);
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Lembar Pengesahan</title>
    <style>
        @page {
            margin: 18mm 18mm 20mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #111111;
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.45;
        }

        .approval-sheet {
            width: 100%;
        }

        .document-header {
            width: 100%;
            margin-bottom: 26pt;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 9pt;
            line-height: 1.25;
        }

        .document-header td {
            border: 0.7pt solid #111111;
            padding: 4pt 5pt;
            vertical-align: middle;
        }

        .header-logo {
            width: 28mm;
            text-align: center;
            font-weight: 700;
        }

        .header-title {
            text-align: center;
            font-weight: 700;
        }

        .header-meta-label {
            width: 22mm;
            font-weight: 700;
        }

        .header-meta-value {
            width: 30mm;
        }

        h1 {
            margin: 0 0 8pt;
            font-size: 21pt;
            font-weight: 700;
            letter-spacing: 0;
            line-height: 1.2;
            text-align: center;
        }

        .intro {
            margin: 0 auto;
            max-width: 150mm;
            text-align: center;
        }

        .intro p {
            margin: 0 0 6pt;
        }

        .document-name {
            font-weight: 700;
        }

        .stages {
            margin-top: 24pt;
        }

        .stage {
            margin: 0 auto 10pt;
            max-width: 130mm;
        }

        .stage.keep-together {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .stage-label {
            background: #eeeeee;
            padding: 2pt 0 2pt 4mm;
            font-size: 9.5pt;
            text-align: left;
        }

        .approvers {
            padding: 10pt 0 0;
        }

        .approver-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .approver-cell {
            width: 50%;
            padding: 0 5mm 10pt;
            text-align: center;
            vertical-align: top;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .approver-grid tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .approver-cell.single {
            width: 100%;
        }

        .approver-name {
            margin: 1pt auto 0;
            font-size: 9.5pt;
            font-weight: 700;
            line-height: 1.25;
        }

        .approver-signature-line {
            width: 42mm;
            max-width: 100%;
            border-top: 0.7pt solid #777777;
            height: 0;
            margin: 2pt auto 3pt;
        }

        .signature-qr {
            width: 16mm;
            height: 16mm;
            margin: 0 auto 2pt;
        }

        .approver-position {
            margin-top: 0;
            font-size: 9pt;
            line-height: 1.25;
        }

        .document-footer {
            position: fixed;
            right: 0;
            bottom: -10mm;
            left: 0;
            border-top: 0.7pt solid #111111;
            padding-top: 4pt;
            font-size: 8.5pt;
            text-align: center;
        }

        .empty-approver {
            padding-top: 28pt;
            color: #444444;
            text-align: center;
        }
    </style>
</head>
<body>
    <footer class="document-footer">
        Sistem Dokumentasi PT Krakatau Bandar Samudera berstandar Sistem Manajemen Terintegrasi
    </footer>

    <main class="approval-sheet">
        <table class="document-header">
            <tr>
                <td class="header-logo" rowspan="4">KBS</td>
                <td class="header-title" rowspan="4">
                    {{ $documentType ?: '-' }}<br>
                    {{ $documentName ?: '-' }}
                </td>
                <td class="header-meta-label">No. Dok</td>
                <td class="header-meta-value">{{ $documentNumber ?: '-' }}</td>
            </tr>
            <tr>
                <td class="header-meta-label">Revisi</td>
                <td class="header-meta-value">{{ $revisionLabel !== '' ? $revisionLabel : '-' }}</td>
            </tr>
            <tr>
                <td class="header-meta-label">Tgl. Terbit</td>
                <td class="header-meta-value">{{ $formatDate($publishedAt) }}</td>
            </tr>
            <tr>
                <td class="header-meta-label">Halaman</td>
                <td class="header-meta-value">Lembar Pengesahan</td>
            </tr>
        </table>

        <h1>LEMBAR PENGESAHAN</h1>

        <div class="intro">
            <p>
                Telah disusun
                <span class="document-name">
                    {{ trim($documentType.' '.$documentName) ?: '-' }}
                </span>
                dan telah disepakati pihak terkait.
            </p>
            <p>Dokumen ini telah memadai untuk dibakukan.</p>
        </div>

        <section class="stages">
            @forelse ($approvalStages as $stage)
                @php
                    $approvers = collect($stage['approvers'] ?? [])->values();
                    $rows = $approvers->chunk(2);
                @endphp

                <div @class(['stage', 'keep-together' => $approvers->count() <= 4])>
                    <div class="stage-label">
                        {{ $stage['stage_name'] ?? '-' }}
                    </div>
                    <div class="approvers">
                        @if ($approvers->isEmpty())
                            <div class="empty-approver">-</div>
                        @else
                            <table class="approver-grid">
                                @foreach ($rows as $row)
                                    <tr>
                                        @foreach ($row as $approver)
                                            <td @class(['approver-cell', 'single' => $approvers->count() === 1])>
                                                @php
                                                    $qr = $qrDataUri($approver['approval_id'] ?? null);
                                                @endphp

                                                @if ($qr !== null)
                                                    <img class="signature-qr" src="{{ $qr }}" alt="QR verifikasi tanda tangan digital">
                                                @endif
                                                <div class="approver-name">{{ $approver['name'] ?? '-' }}</div>
                                                <div class="approver-signature-line"></div>
                                                <div class="approver-position">{{ $approver['position'] ?? '-' }}</div>
                                            </td>
                                        @endforeach

                                        @if ($row->count() === 1 && $approvers->count() > 1)
                                            <td class="approver-cell">&nbsp;</td>
                                        @endif
                                    </tr>
                                @endforeach
                            </table>
                        @endif
                    </div>
                </div>
            @empty
                <div class="stage keep-together">
                    <div class="stage-label">-</div>
                    <div class="approvers">
                        <div class="empty-approver">-</div>
                    </div>
                </div>
            @endforelse
        </section>
    </main>
</body>
</html>
