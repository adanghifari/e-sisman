<x-layouts::app :title="__('Template Dokumen')">
    <x-document-templates.builder
        :document-levels="config('document-levels')"
        :can-edit="auth()->user()->hasAnyPermission(['document-templates.edit', 'document-templates.manage'])"
        :upload-limits="config('document-templates.upload')"
    />
</x-layouts::app>
