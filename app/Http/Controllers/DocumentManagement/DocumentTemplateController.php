<?php

namespace App\Http\Controllers\DocumentManagement;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateFile;
use App\Support\DocumentTemplates\DocumentTemplateUploadRules;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentTemplateController extends Controller
{
    public function index(): View
    {
        $templates = DocumentTemplate::query()
            ->active()
            ->with(['files' => fn ($query) => $query->orderBy('file_order')])
            ->get()
            ->keyBy('document_level');

        return view('document-management.templates.index', [
            'templates' => $templates,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyPermission(['document-templates.edit', 'document-templates.manage']), 403);

        $validated = $request->validate(DocumentTemplateUploadRules::rules());

        DB::transaction(function () use ($request, $validated): void {
            DocumentTemplate::query()
                ->forLevel($validated['document_level'])
                ->update([
                    'is_active' => false,
                    'active_template_key' => null,
                ]);

            $nextVersion = ((int) DocumentTemplate::query()
                ->forLevel($validated['document_level'])
                ->max('version_number')) + 1;

            $template = DocumentTemplate::query()->create([
                'document_level' => $validated['document_level'],
                'version_number' => $nextVersion,
                'title' => $validated['title'],
                'notes' => $validated['notes'] ?? null,
                'uploaded_by' => $request->user()->id,
                'is_active' => true,
                'active_template_key' => $validated['document_level'],
                'activated_at' => now(),
            ]);

            foreach ($request->file('template_files', []) as $index => $file) {
                $path = $file->store("document-templates/{$template->id}", 'local');

                $template->files()->create([
                    'file_order' => $index + 1,
                    'disk' => 'local',
                    'path_file' => $path,
                    'original_file_name' => $file->getClientOriginalName(),
                    'stored_file_name' => basename($path),
                    'mime_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        });

        return redirect()
            ->route('document-templates.index')
            ->with('status', 'Template dokumen berhasil disimpan.')
            ->with('active_template_level', $validated['document_level']);
    }

    public function file(Request $request, DocumentTemplateFile $file): BinaryFileResponse
    {
        abort_unless($request->user()->hasAnyPermission(['document-templates.view', 'document-templates.edit', 'document-templates.manage']), 403);
        abort_unless($file->documentTemplate()->where('is_active', true)->exists(), 404);

        $path = Storage::disk($file->disk)->path($file->path_file);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="'.$file->original_file_name.'"',
        ]);
    }
}
