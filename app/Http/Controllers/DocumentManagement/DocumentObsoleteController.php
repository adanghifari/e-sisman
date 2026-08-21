<?php

namespace App\Http\Controllers\DocumentManagement;

use App\Http\Controllers\Controller;
use App\Models\BusinessProcess;
use App\Models\Document;
use App\Models\DocumentLevel;
use App\Models\StatusDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DocumentObsoleteController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'type' => (string) $request->query('type', ''),
            'process' => (string) $request->query('process', ''),
            'sort' => (string) $request->query('sort', 'newest'),
        ];

        $obsoleteStatusId = StatusDocument::query()
            ->where('nama_status', StatusDocument::OBSOLETE)
            ->value('id');

        $query = Document::query()
            ->with([
                'status',
                'documentLevel',
                'businessProcess',
                'businessFunction',
                'departments',
                'revisedFrom',
            ])
            ->where('m_status_document_id', $obsoleteStatusId);

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($query) use ($search): void {
                $query
                    ->where('nama_dokumen', 'like', "%{$search}%")
                    ->orWhere('nomor_dokumen', 'like', "%{$search}%")
                    ->orWhereHas('documentLevel', fn ($query) => $query->where('nama_dokumen', 'like', "%{$search}%"))
                    ->orWhereHas('businessProcess', fn ($query) => $query->where('nama_proses_bisnis', 'like', "%{$search}%"))
                    ->orWhereHas('businessFunction', fn ($query) => $query->where('nama_proses_fungsi', 'like', "%{$search}%"))
                    ->orWhereHas('departments', fn ($query) => $query->where('nama_department', 'like', "%{$search}%"));
            });
        }

        if ($filters['type'] !== '') {
            $query->where('m_document_level_id', $filters['type']);
        }

        if ($filters['process'] !== '') {
            $query->where('m_proses_bisnis_id', $filters['process']);
        }

        match ($filters['sort']) {
            'oldest' => $query->orderBy('approved_at')->orderBy('tanggal_terbit')->orderBy('id'),
            'name_asc' => $query->orderBy('nama_dokumen')->orderBy('nomor_dokumen'),
            'name_desc' => $query->orderByDesc('nama_dokumen')->orderByDesc('nomor_dokumen'),
            'revision_desc' => $query->orderByDesc('nomor_revisi')->orderByDesc('approved_at')->orderByDesc('id'),
            default => $query->orderByDesc('approved_at')->orderByDesc('tanggal_terbit')->orderByDesc('id'),
        };

        $documents = $query->get();

        $typeOptions = ['' => 'Semua Level'] + DocumentLevel::query()
            ->orderBy('id')
            ->pluck('nama_dokumen', 'id')
            ->all();

        $processOptions = ['' => 'Semua Proses'] + BusinessProcess::query()
            ->orderBy('nama_proses_bisnis')
            ->pluck('nama_proses_bisnis', 'id')
            ->all();

        return view('document-management.obsolete', [
            'documents' => $documents,
            'totalDocuments' => $documents->count(),
            'filters' => $filters,
            'typeOptions' => $typeOptions,
            'processOptions' => $processOptions,
            'sortOptions' => [
                'newest' => 'Terbaru',
                'oldest' => 'Terlama',
                'name_asc' => 'Nama A-Z',
                'name_desc' => 'Nama Z-A',
                'revision_desc' => 'Revisi Tertinggi',
            ],
        ]);
    }
}
