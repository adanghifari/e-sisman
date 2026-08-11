<x-layouts::app :title="__('Approval Flow')">
    <x-approval-flows.builder :document-levels="config('document-levels')" />
</x-layouts::app>
