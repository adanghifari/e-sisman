<?php

namespace App\Support\DocumentTemplates;

use Illuminate\Validation\Rule;

class DocumentTemplateUploadRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(bool $requireFiles = true): array
    {
        $upload = config('document-templates.upload');

        return [
            'document_level' => [
                'required',
                'string',
                Rule::in(array_keys(config('document-levels'))),
            ],
            'title' => [
                'nullable',
                'string',
                'max:255',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'template_files' => [
                $requireFiles ? 'required' : 'nullable',
                'array',
                $requireFiles ? 'min:1' : 'min:0',
                'max:'.$upload['max_files'],
            ],
            'template_files.*' => [
                $requireFiles ? 'required' : 'nullable',
                'file',
                'max:'.$upload['max_file_size_kb'],
                'mimes:'.implode(',', $upload['allowed_extensions']),
            ],
            'retained_template_file_ids_present' => [
                'nullable',
                'boolean',
            ],
            'retained_template_file_ids' => [
                'nullable',
                'array',
                'max:'.$upload['max_files'],
            ],
            'retained_template_file_ids.*' => [
                'integer',
            ],
        ];
    }
}
