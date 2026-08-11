<?php

namespace App\Support\DocumentTemplates;

use Illuminate\Validation\Rule;

class DocumentTemplateUploadRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $upload = config('document-templates.upload');

        return [
            'document_level' => [
                'required',
                'string',
                Rule::in(array_keys(config('document-levels'))),
            ],
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'template_files' => [
                'required',
                'array',
                'min:1',
                'max:'.$upload['max_files'],
            ],
            'template_files.*' => [
                'required',
                'file',
                'max:'.$upload['max_file_size_kb'],
                'mimes:'.implode(',', $upload['allowed_extensions']),
            ],
        ];
    }
}
