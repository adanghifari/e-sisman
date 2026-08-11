<x-layouts::app :title="__('Template Dokumen')">
    <x-document-templates.builder
        :document-levels="config('document-levels')"
        :can-edit="auth()->user()->isAdmin()"
    />
</x-layouts::app>
