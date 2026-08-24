<?php

namespace App\Support;

use App\Models\Document;
use Illuminate\Support\Collection;

class DocumentHistory
{
    public function forDocument(Document $document): Collection
    {
        $family = $document->revisionFamily()
            ->load(['status'])
            ->sortBy(fn (Document $familyDocument): string => sprintf(
                '%010d-%010d',
                $familyDocument->nomor_revisi,
                $familyDocument->id,
            ))
            ->values();

        $events = collect();

        $family->each(function (Document $familyDocument) use ($events): void {
            foreach ($this->baseEvents($familyDocument) as $event) {
                $events->push($event);
            }
        });

        $this->manualObsoleteEvents($family)->each(fn (array $event) => $events->push($event));
        $this->automaticObsoleteEvents($family)->each(fn (array $event) => $events->push($event));

        return $events
            ->sortBy(fn (array $event): string => sprintf(
                '%010d-%010d-%s',
                $event['timestamp']?->timestamp ?? PHP_INT_MAX,
                $event['document_id'],
                $event['label'],
            ))
            ->values();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function baseEvents(Document $document): array
    {
        return [
            $this->event($document, 'Dibuat', 'Dokumen dibuat', $document->created_at, 'slate'),
            $this->event($document, 'Diajukan', 'Dokumen diajukan', $document->submitted_at, 'sky'),
            $this->event($document, 'Disetujui', $document->request_type === 'revision' ? 'Revisi menjadi master' : 'Dokumen disetujui', $document->approved_at, 'emerald'),
            $this->event($document, 'Ditolak', 'Dokumen ditolak', $document->rejected_at, 'red'),
            $this->event($document, 'Dibatalkan', 'Dokumen dibatalkan', $document->cancelled_at, 'amber'),
        ];
    }

    private function manualObsoleteEvents(Collection $family): Collection
    {
        return $family
            ->filter(fn (Document $document): bool => $document->request_type === 'obsolete' && $document->approved_at !== null)
            ->map(function (Document $obsoleteRequest) use ($family): array {
                $source = $family->firstWhere('id', $obsoleteRequest->revised_from) ?? $obsoleteRequest;

                return $this->event(
                    $source,
                    'Obsolete',
                    'Dokumen diobsoletekan lewat pengajuan '.$this->documentNumber($obsoleteRequest),
                    $obsoleteRequest->approved_at,
                    'red',
                );
            })
            ->values();
    }

    private function automaticObsoleteEvents(Collection $family): Collection
    {
        $approvedRevisions = $family
            ->filter(fn (Document $document): bool => $document->request_type !== 'obsolete' && $document->approved_at !== null)
            ->sortBy('nomor_revisi')
            ->values();

        return $approvedRevisions
            ->filter(fn (Document $document): bool => $approvedRevisions
                ->contains(fn (Document $revision): bool => $revision->nomor_revisi > $document->nomor_revisi))
            ->map(function (Document $document) use ($approvedRevisions): array {
                $nextRevision = $approvedRevisions
                    ->first(fn (Document $revision): bool => $revision->nomor_revisi > $document->nomor_revisi);

                return $this->event(
                    $document,
                    'Obsolete',
                    'Otomatis obsolete saat revisi '.$nextRevision->formatted_revision.' menjadi master',
                    $nextRevision?->approved_at,
                    'red',
                );
            })
            ->values();
    }

    private function event(Document $document, string $label, string $description, mixed $timestamp, string $tone): array
    {
        return [
            'document_id' => $document->id,
            'document_number' => $this->documentNumber($document),
            'revision' => $document->formatted_revision,
            'label' => $label,
            'description' => $description,
            'timestamp' => $timestamp,
            'tone' => $tone,
        ];
    }

    private function documentNumber(Document $document): string
    {
        return $document->nomor_dokumen ?: '-';
    }
}
