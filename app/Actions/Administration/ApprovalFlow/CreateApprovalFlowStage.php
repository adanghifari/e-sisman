<?php

namespace App\Actions\Administration\ApprovalFlow;

use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowStage;
use Illuminate\Support\Facades\Validator;

class CreateApprovalFlowStage
{
    public function handle(ApprovalFlow $approvalFlow, array $data): ApprovalFlowStage
    {
        $validated = Validator::make($data, [
            'keterangan' => [
                'nullable',
                'string',
                'max:255',
            ],
            'nama_tahap' => [
                'required',
                'string',
                'max:255',
            ],
        ])->validate();

        return $approvalFlow->stages()->create([
            'stage_order' => $approvalFlow->stages()->count() + 1,
            'keterangan' => filled($validated['keterangan'] ?? null) ? trim($validated['keterangan']) : null,
            'nama_tahap' => trim($validated['nama_tahap']),
        ]);
    }
}
