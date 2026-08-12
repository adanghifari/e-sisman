<?php

namespace App\Http\Controllers\DocumentManagement;

use App\Http\Controllers\Controller;
use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
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

        $validated = $request->validate($this->validationRulesForLevel($level));

        if ($level === 'level-1') {
            $validated = array_merge($validated, $this->defaultDocumentContext());
            $validated['submit_action'] = 'draft';
        }

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
                'official_preparer_id' => $validated['official_preparer_id'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'nama_dokumen' => $validated['nama_dokumen'],
                'nomor_dokumen' => $this->buildDocumentNumber($documentLevel, $validated),
                'nomor_revisi' => $this->normalizeRevision($validated['nomor_revisi'] ?? null),
                'catatan_revisi' => $validated['catatan_revisi'] ?? null,
                'tanggal_terbit' => $validated['tanggal_terbit'] ?? null,
                'submitted_at' => $validated['submit_action'] === 'submit' ? now() : null,
            ]);

            $document->departments()->sync($validated['department_ids'] ?? []);

            if ($request->hasFile('imported_document')) {
                $this->storeDocumentFile($document, $request->file('imported_document'), 'imported_document', $request->user()->id);
            }

            if ($request->hasFile('filled_template')) {
                $this->storeDocumentFile($document, $request->file('filled_template'), 'filled_template', $request->user()->id);
            }

            foreach ($request->file('attachments', []) as $attachment) {
                $this->storeDocumentFile($document, $attachment, 'attachment', $request->user()->id);
            }

        });

        if ($validated['submit_action'] === 'submit') {
            return redirect()
                ->route('documents.create')
                ->with('document_success', [
                    'title' => 'Dokumen berhasil disubmit',
                    'message' => 'Dokumen akan segera diproses oleh tim terkait.',
                ]);
        }

        return redirect()
            ->route('documents.create.level', $level)
            ->with('status', 'Dokumen berhasil disimpan sebagai draft.');
    }

    protected function documentTypeNameForLevel(string $level): string
    {
        return [
            'level-1' => 'Manual',
            'level-2' => 'Prosedur',
            'level-3' => 'IK',
        ][$level] ?? 'IK';
    }

    protected function validationRulesForLevel(string $level): array
    {
        if ($level === 'level-1') {
            return [
                'nama_dokumen' => ['required', 'string', 'max:255'],
                'nomor_dokumen_suffix' => ['required', 'string', 'max:50'],
                'nomor_revisi' => ['nullable', 'string', 'max:20'],
                'tanggal_terbit' => ['nullable', 'date'],
                'catatan_revisi' => ['nullable', 'string', 'max:1000'],
                'imported_document' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            ];
        }

        return [
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
        ];
    }

    protected function defaultDocumentContext(): array
    {
        return [
            'm_proses_bisnis_id' => BusinessProcess::query()->active()->orderBy('id')->value('id')
                ?? BusinessProcess::query()->orderBy('id')->value('id'),
            'm_proses_fungsi_id' => BusinessFunction::query()->active()->orderBy('id')->value('id')
                ?? BusinessFunction::query()->orderBy('id')->value('id'),
            'department_ids' => [],
        ];
    }

    protected function buildDocumentNumber(DocumentLevel $documentLevel, array $validated): ?string
    {
        $suffix = $validated['nomor_dokumen_suffix'] ?? null;

        if (! filled($suffix)) {
            return null;
        }

        $segments = [$documentLevel->prefix];

        if ($documentLevel->kode === 'level-1') {
            $segments[] = Str::upper(trim($suffix));
        } elseif ($documentLevel->kode === 'level-2') {
            $businessProcessCode = BusinessProcess::query()
                ->whereKey($validated['m_proses_bisnis_id'])
                ->value('kode');

            $segments[] = $businessProcessCode ?: 'SMR';
            $segments[] = Str::upper(trim($suffix));
        } else {
            $segments[] = 'XXX';
            $segments[] = 'YY';
            $segments[] = Str::upper(trim($suffix));
        }

        return collect($segments)
            ->filter()
            ->implode('-');
    }

    protected function normalizeRevision(?string $revision): int
    {
        if (! filled($revision)) {
            return 0;
        }

        return (int) preg_replace('/\D+/', '', Str::before($revision, '.'));
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
