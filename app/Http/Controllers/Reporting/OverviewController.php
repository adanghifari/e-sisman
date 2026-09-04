<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Models\BusinessFunction;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentLevel;
use App\Models\StatusDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class OverviewController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $this->filters($request);
        $procedureLevelId = $this->documentLevelId('level-2');
        $instructionLevelId = $this->documentLevelId('level-3');

        $procedures = $this->procedureQuery($filters, $procedureLevelId, $instructionLevelId)
            ->with(['businessFunction', 'departments'])
            ->orderBy('nama_dokumen')
            ->orderBy('nomor_dokumen')
            ->paginate(10)
            ->withQueryString();

        return view('reporting.index', [
            'overviewFilters' => $filters,
            'overviewRows' => $this->overviewRows($procedures, $filters, $instructionLevelId),
            'departments' => Department::query()->active()->orderBy('nama_department')->get(),
            'businessFunctions' => BusinessFunction::query()->active()->orderBy('nama_proses_fungsi')->get(),
        ]);
    }

    /**
     * @return array{procedure: string, instruction: string, department_id: string, business_function_id: string}
     */
    private function filters(Request $request): array
    {
        return [
            'procedure' => trim((string) $request->query('procedure', '')),
            'instruction' => trim((string) $request->query('instruction', '')),
            'department_id' => trim((string) $request->query('department_id', '')),
            'business_function_id' => trim((string) $request->query('business_function_id', '')),
        ];
    }

    /**
     * @param  array{procedure: string, instruction: string, department_id: string, business_function_id: string}  $filters
     */
    private function procedureQuery(array $filters, ?int $procedureLevelId, ?int $instructionLevelId): Builder
    {
        return Document::query()
            ->where('m_document_level_id', $procedureLevelId)
            ->where($this->publishedDocumentScope())
            ->when($filters['procedure'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $query
                        ->where('nama_dokumen', 'like', '%'.$filters['procedure'].'%')
                        ->orWhere('nomor_dokumen', 'like', '%'.$filters['procedure'].'%');
                });
            })
            ->when($filters['department_id'] !== '', function (Builder $query) use ($filters): void {
                $query->whereHas('departments', fn (Builder $query) => $query->whereKey($filters['department_id']));
            })
            ->when($filters['business_function_id'] !== '', function (Builder $query) use ($filters): void {
                $query->where('m_proses_fungsi_id', $filters['business_function_id']);
            })
            ->when($filters['instruction'] !== '', function (Builder $query) use ($filters, $instructionLevelId): void {
                $query->whereExists(function ($query) use ($filters, $instructionLevelId): void {
                    $query
                        ->selectRaw('1')
                        ->from('t_document as instructions')
                        ->whereColumn('instructions.reference', 't_document.id')
                        ->where('instructions.m_document_level_id', $instructionLevelId)
                        ->whereIn('instructions.m_status_document_id', $this->publishedStatusIds())
                        ->where(function ($query): void {
                            $query
                                ->whereNull('instructions.request_type')
                                ->orWhere('instructions.request_type', '!=', 'obsolete');
                        })
                        ->where('instructions.nama_dokumen', 'like', '%'.$filters['instruction'].'%');
                });
            });
    }

    /**
     * @param  array{procedure: string, instruction: string, department_id: string, business_function_id: string}  $filters
     */
    private function overviewRows(LengthAwarePaginator $procedures, array $filters, ?int $instructionLevelId): LengthAwarePaginator
    {
        $procedureIds = $procedures->getCollection()->pluck('id');
        $instructions = Document::query()
            ->with(['businessFunction', 'departments'])
            ->whereIn('reference', $procedureIds)
            ->where('m_document_level_id', $instructionLevelId)
            ->where($this->publishedDocumentScope())
            ->when($filters['instruction'] !== '', fn (Builder $query) => $query->where('nama_dokumen', 'like', '%'.$filters['instruction'].'%'))
            ->orderBy('nama_dokumen')
            ->get()
            ->groupBy('reference');

        $procedures->setCollection(
            $procedures->getCollection()->map(fn (Document $procedure): array => $this->formatProcedureRow(
                $procedure,
                $instructions->get($procedure->id, collect()),
            )),
        );

        return $procedures;
    }

    private function formatProcedureRow(Document $procedure, Collection $instructions): array
    {
        return [
            'id' => $procedure->id,
            'procedure' => $procedure->nama_dokumen,
            'number' => $procedure->nomor_dokumen,
            'revision' => Document::formatRevisionNumber((int) $procedure->nomor_revisi),
            'departments' => $procedure->departments->pluck('nama_department')->values(),
            'business_function' => $procedure->businessFunction?->nama_proses_fungsi ?? '-',
            'published_at' => $procedure->tanggal_terbit?->format('d M Y') ?? '-',
            'instructions' => $instructions
                ->values()
                ->map(fn (Document $instruction): array => [
                    'id' => $instruction->id,
                    'name' => $instruction->nama_dokumen,
                    'number' => $instruction->nomor_dokumen,
                    'revision' => Document::formatRevisionNumber((int) $instruction->nomor_revisi),
                    'departments' => $instruction->departments->pluck('nama_department')->values(),
                    'business_function' => $instruction->businessFunction?->nama_proses_fungsi ?? '-',
                    'published_at' => $instruction->tanggal_terbit?->format('d M Y') ?? '-',
                ]),
        ];
    }

    private function publishedDocumentScope(): callable
    {
        $statusIds = $this->publishedStatusIds();

        return fn (Builder $query) => $query
            ->whereIn('m_status_document_id', $statusIds)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('request_type')
                    ->orWhere('request_type', '!=', 'obsolete');
            });
    }

    private function publishedStatusIds(): Collection
    {
        return StatusDocument::query()
            ->whereIn('nama_status', [
                StatusDocument::APPROVED,
                StatusDocument::OBSOLETE,
            ])
            ->pluck('id');
    }

    private function documentLevelId(string $level): ?int
    {
        return DocumentLevel::query()
            ->where('kode', $level)
            ->value('id');
    }
}
