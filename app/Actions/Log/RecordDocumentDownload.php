<?php

namespace App\Actions\Log;

use App\Models\Document;
use App\Models\DocumentDownloadLog;
use App\Models\DocumentFile;
use Illuminate\Http\Request;

class RecordDocumentDownload
{
    public function handle(Request $request, Document $document, ?DocumentFile $file = null): DocumentDownloadLog
    {
        return DocumentDownloadLog::create([
            't_document_id' => $document->id,
            't_document_file_id' => $file?->id,
            'user_id' => $request->user()?->id,
            'downloaded_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
