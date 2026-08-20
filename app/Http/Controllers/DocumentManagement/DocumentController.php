<?php

namespace App\Http\Controllers\DocumentManagement;

use App\Http\Controllers\Controller;
use App\Models\ApprovalStatus;
use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Document;
use App\Models\DocumentLevel;
use App\Models\DocumentType;
use App\Models\Role;
use App\Models\StatusDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DocumentController extends Controller
{
    public function create(Request $request, string $level): View
    {
        $revisionSource = $this->revisionSourceForRequest($request, $level);

        abort_if($level === 'level-4' && $revisionSource === null, 404);

        return view('document-management.create.level', [
            'revisionSource' => $revisionSource,
        ]);
    }

    public function store(Request $request, string $level): RedirectResponse
    {
        $documentLevel = DocumentLevel::query()
            ->where('kode', $level)
            ->firstOrFail();

        $documentType = DocumentType::query()
            ->where('nama_types', $this->documentTypeNameForLevel($level))
            ->firstOrFail();

        $validated = $request->validate($this->validationRulesForLevel($level));
        $revisionSource = $this->revisionSourceForRequest($request, $level);

        abort_if($level === 'level-4' && $revisionSource === null, 404);

        if ($level === 'level-1') {
            $validated = array_merge($validated, $this->defaultDocumentContext());
            $validated['submit_action'] = 'draft';
        }

        if ($revisionSource !== null) {
            $validated['m_proses_bisnis_id'] = $revisionSource->m_proses_bisnis_id;
            $validated['m_proses_fungsi_id'] = $revisionSource->m_proses_fungsi_id;
            $validated['department_ids'] = $revisionSource->departments->pluck('id')->all();
            $validated['reference'] = $revisionSource->reference;
            $validated['nama_dokumen'] = $validated['nama_dokumen'] ?? $revisionSource->nama_dokumen;
        }

        $documentNumber = $revisionSource !== null
            ? $this->buildRevisionDocumentNumber($revisionSource, $documentLevel)
            : $this->buildDocumentNumber($documentLevel, $validated);
        $documentRevision = $revisionSource !== null
            ? $this->nextRevisionNumber($revisionSource)
            : $this->normalizeRevision($validated['nomor_revisi'] ?? null);

        if (
            $revisionSource === null
            && $documentNumber !== null
            && Document::query()->where('nomor_dokumen', $documentNumber)->exists()
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'nomor_dokumen_suffix' => 'Nomor dokumen sudah digunakan.',
                ]);
        }

        $status = StatusDocument::findByName(
            $validated['submit_action'] === 'submit'
                ? StatusDocument::PROPOSED
                : StatusDocument::DRAFT,
        );

        DB::transaction(function () use ($request, $validated, $documentNumber, $documentRevision, $documentLevel, $documentType, $status, $level, $revisionSource): void {
            $submittedAt = $validated['submit_action'] === 'submit' ? now() : null;
            $document = Document::create([
                'm_document_level_id' => $documentLevel->id,
                'm_status_document_id' => $status->id,
                'm_document_types_id' => $documentType->id,
                'm_proses_bisnis_id' => $validated['m_proses_bisnis_id'],
                'm_proses_fungsi_id' => $validated['m_proses_fungsi_id'],
                'user_id' => $request->user()->id,
                'official_preparer_id' => $validated['official_preparer_id'] ?? null,
                'reference' => $level === 'level-3' ? $validated['reference'] : null,
                'revised_from' => $revisionSource?->id,
                'request_type' => $revisionSource !== null ? 'revision' : null,
                'nama_dokumen' => $validated['nama_dokumen'],
                'nomor_dokumen' => $documentNumber,
                'nomor_revisi' => $documentRevision,
                'catatan_revisi' => $validated['catatan_revisi'] ?? null,
                'tanggal_terbit' => $validated['tanggal_terbit'] ?? null,
                'submitted_at' => $submittedAt,
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

            if ($submittedAt !== null) {
                $this->recordOfficialPreparerApproval($document, $request->user()->id, $submittedAt);
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
            'level-4' => 'Form',
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
                'imported_document' => ['required', 'file', 'mimes:pdf', 'max:10240'],
                'revised_from' => ['nullable', 'integer', Rule::exists('t_document', 'id')],
            ];
        }

        return [
            'nama_dokumen' => ['required', 'string', 'max:255'],
            'm_proses_bisnis_id' => ['required', 'integer', Rule::exists('m_proses_bisnis', 'id')],
            'm_proses_fungsi_id' => ['required', 'integer', Rule::exists('m_proses_fungsi', 'id')],
            'reference' => $this->referenceRulesForLevel($level),
            'department_ids' => ['required', 'array', 'min:1'],
            'department_ids.*' => ['required', 'integer', Rule::exists('departments', 'id')],
            'official_preparer_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'nomor_dokumen_suffix' => ['required', 'string', 'max:50'],
            'filled_template' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:pdf', 'max:10240'],
            'submit_action' => ['required', Rule::in(['draft', 'submit'])],
            'revised_from' => ['nullable', 'integer', Rule::exists('t_document', 'id')],
        ];
    }

    private function revisionSourceForRequest(Request $request, string $level): ?Document
    {
        $sourceId = $request->input('revised_from') ?: $request->query('revised_from');

        if (! filled($sourceId)) {
            return null;
        }

        $source = Document::query()
            ->with(['status', 'documentLevel', 'businessProcess', 'businessFunction', 'departments', 'referenceDocument'])
            ->whereKey($sourceId)
            ->firstOrFail();

        abort_unless($level === 'level-4' || $source->documentLevel?->kode === $level, 404);
        abort_unless(
            in_array($source->status?->nama_status, [StatusDocument::APPROVED, StatusDocument::OBSOLETE], true),
            404,
        );
        abort_unless($this->userCanRequestRevision($request, $source), 403);

        return $source;
    }

    private function userCanRequestRevision(Request $request, Document $document): bool
    {
        $user = $request->user();

        if ($user?->isDeveloper() || $user?->isAdmin()) {
            return true;
        }

        if ($user?->m_department_id === null) {
            return false;
        }

        if ($document->relationLoaded('departments')) {
            return $document->departments->contains('id', $user->m_department_id);
        }

        return $document->departments()
            ->whereKey($user->m_department_id)
            ->exists();
    }

    protected function referenceRulesForLevel(string $level): array
    {
        if ($level !== 'level-3') {
            return ['nullable', 'integer', Rule::exists('t_document', 'id')];
        }

        $procedureLevelId = DocumentLevel::query()
            ->where('kode', 'level-2')
            ->value('id');

        $approvedStatusId = StatusDocument::query()
            ->where('nama_status', StatusDocument::APPROVED)
            ->value('id');

        return [
            'required',
            'integer',
            Rule::exists('t_document', 'id')
                ->where('m_document_level_id', $procedureLevelId)
                ->where('m_status_document_id', $approvedStatusId)
                ->where('m_proses_bisnis_id', request('m_proses_bisnis_id'))
                ->where('m_proses_fungsi_id', request('m_proses_fungsi_id')),
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
        } elseif ($documentLevel->kode === 'level-4') {
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

    protected function buildRevisionDocumentNumber(Document $source, DocumentLevel $documentLevel): string
    {
        $revisionPrefix = match ($documentLevel->kode) {
            'level-2' => 'FMPS',
            'level-3' => 'FMIK',
            'level-4' => match ($source->documentLevel?->kode) {
                'level-2' => 'FMPS',
                'level-3' => 'FMIK',
                'level-1' => 'FMSM',
                default => 'FM',
            },
            default => 'FM'.$documentLevel->prefix,
        };
        $sourceSegments = collect(explode('-', (string) $source->nomor_dokumen))
            ->filter()
            ->values();

        if ($sourceSegments->isNotEmpty()) {
            $sourceSegments->shift();
        }

        return collect([$revisionPrefix])
            ->merge($sourceSegments)
            ->filter()
            ->implode('-');
    }

    protected function nextRevisionNumber(Document $source): int
    {
        $rootDocumentId = $source->revised_from ?: $source->id;

        return ((int) Document::query()
            ->where(function ($query) use ($rootDocumentId): void {
                $query
                    ->whereKey($rootDocumentId)
                    ->orWhere('revised_from', $rootDocumentId);
            })
            ->max('nomor_revisi')) + 1;
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

    private function recordOfficialPreparerApproval(Document $document, int $assignedBy, mixed $respondedAt): void
    {
        if ($document->official_preparer_id === null) {
            return;
        }

        $role = Role::query()->firstOrCreate(['nama_role' => 'Penyusun Resmi']);
        $approvedStatus = ApprovalStatus::findByCode(ApprovalStatus::APPROVED);

        $document->approvals()->create([
            'm_approval_status_id' => $approvedStatus->id,
            'user_id' => $document->official_preparer_id,
            'role_id' => $role->id,
            'assigned_by' => $assignedBy,
            'assigned_at' => $respondedAt,
            'responded_at' => $respondedAt,
            'stages' => 'TTD Penyusun Resmi',
            'catatan' => 'Tanda tangan penyusun resmi tercatat saat submit dokumen.',
        ]);
    }
}
