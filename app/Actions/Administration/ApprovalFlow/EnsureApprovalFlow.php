<?php

namespace App\Actions\Administration\ApprovalFlow;

use App\Models\ApprovalFlow;
use App\Models\DocumentType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EnsureApprovalFlow
{
    public function handle(array $data): ApprovalFlow
    {
        $validated = Validator::make($data, [
            'm_document_types_id' => [
                'required',
                'integer',
                Rule::exists('m_document_types', 'id'),
            ],
            'nama_flow' => [
                'nullable',
                'string',
                'max:255',
            ],
        ])->validate();

        $documentType = DocumentType::findOrFail($validated['m_document_types_id']);
        $flowName = trim($validated['nama_flow'] ?? '') ?: 'Flow '.$documentType->nama_types;

        return ApprovalFlow::firstOrCreate(
            ['m_document_types_id' => $documentType->id],
            ['nama_flow' => $flowName],
        );
    }
}
