<?php

namespace App\Http\Controllers\DocumentManagement;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentLevel;
use App\Models\DocumentType;
use App\Models\StatusDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DocumentController extends Controller
{
    public function store(Request $request, string $level): RedirectResponse
    {
        $documentLevel = DocumentLevel::query()
            ->where('kode', $level)
            ->firstOrFail();

        $documentType = DocumentType::query()
            ->where('nama_types', $this->documentTypeNameForLevel($level))
            ->firstOrFail();

        $validated = $request->validate([
            'nama_dokumen' => ['required', 'string', 'max:255'],
            'm_proses_bisnis_id' => ['required', 'integer', Rule::exists('m_proses_bisnis', 'id')],
            'm_proses_fungsi_id' => ['required', 'integer', Rule::exists('m_proses_fungsi', 'id')],
            'reference' => ['nullable', 'integer', Rule::exists('t_document', 'id')],
            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => ['required', 'integer', Rule::exists('departments', 'id')],
            'official_preparer_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'nomor_dokumen_suffix' => ['required', 'string', 'max:50'],
            'filled_template' => ['required', 'file', 'mimes:doc,docx', 'max:10240'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:pdf,doc,docx', 'max:10240'],
            'submit_action' => ['required', Rule::in(['draft', 'submit'])],
        ]);

        $status = StatusDocument::findByName(
            $validated['submit_action'] === 'submit'
                ? StatusDocument::PROPOSED
                : StatusDocument::DRAFT,
        );

        DB::transaction(function () use ($request, $validated, $documentLevel, $documentType, $status): void {
            $document = Document::create([
                'm_document_level_id' => $documentLevel->id,
                'm_status_document_id' => $status->id,
                'm_document_types_id' => $documentType->id,
                'm_proses_bisnis_id' => $validated['m_proses_bisnis_id'],
                'm_proses_fungsi_id' => $validated['m_proses_fungsi_id'],
                'user_id' => $request->user()->id,
                'reference' => $validated['reference'] ?? null,
                'nama_dokumen' => $validated['nama_dokumen'],
                'nomor_dokumen' => $this->buildDocumentNumber($documentLevel->prefix, $validated['nomor_dokumen_suffix'] ?? null),
                'nomor_revisi' => 0,
                'submitted_at' => $validated['submit_action'] === 'submit' ? now() : null,
            ]);

            $document->departments()->sync($validated['department_ids']);

            if ($request->hasFile('filled_template')) {
                $this->storeDocumentFile($document, $request->file('filled_template'), 'filled_template', $request->user()->id);
            }

            foreach ($request->file('attachments', []) as $attachment) {
                $this->storeDocumentFile($document, $attachment, 'attachment', $request->user()->id);
            }

        });

        return redirect()
            ->route('documents.create.level', $level)
            ->with('status', 'Dokumen berhasil disimpan.');
    }

    protected function documentTypeNameForLevel(string $level): string
    {
        return [
            'level-1' => 'Manual',
            'level-2' => 'Prosedur',
            'level-3' => 'IK',
        ][$level] ?? 'IK';
    }

    protected function buildDocumentNumber(?string $prefix, ?string $suffix): ?string
    {
        if (! filled($suffix)) {
            return null;
        }

        return collect([$prefix, 'XXX', 'YY', Str::upper(trim($suffix))])
            ->filter()
            ->implode('-');
    }

    protected function storeDocumentFile(Document $document, mixed $file, string $type, int $uploadedBy): void
    {
        $path = $file->store("documents/{$document->id}", 'local');

        $document->files()->create([
            'type_file' => $type,
            'path_file' => $path,
            'uploaded_by' => $uploadedBy,
            'updated_at' => now(),
            'original_file_name' => $file->getClientOriginalName(),
            'stored_file_name' => basename($path),
            'file_size' => $file->getSize(),
        ]);
    }

}
