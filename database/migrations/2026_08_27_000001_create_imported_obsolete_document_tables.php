<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imported_obsolete_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('obsolete_rule_type');
            $table->foreignId('m_document_level_id')->nullable()->constrained('m_document_levels')->restrictOnDelete();
            $table->foreignId('m_document_types_id')->nullable()->constrained('m_document_types')->restrictOnDelete();
            $table->foreignId('m_proses_bisnis_id')->nullable()->constrained('m_proses_bisnis')->restrictOnDelete();
            $table->foreignId('m_proses_fungsi_id')->nullable()->constrained('m_proses_fungsi')->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('nama_dokumen');
            $table->string('nomor_dokumen')->nullable();
            $table->string('nomor_revisi')->nullable();
            $table->date('tanggal_terbit')->nullable();
            $table->date('tanggal_obsolete')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index('obsolete_rule_type');
            $table->index('nomor_dokumen');
        });

        Schema::create('imported_obsolete_document_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('imported_obsolete_document_id')
                ->constrained('imported_obsolete_documents')
                ->cascadeOnDelete();
            $table->string('type_file');
            $table->string('path_file');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('original_file_name');
            $table->string('stored_file_name');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();

            $table->index(['imported_obsolete_document_id', 'type_file'], 'imported_obsolete_files_document_type_index');
        });

        Schema::create('imported_obsolete_document_relations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('imported_obsolete_document_id')
                ->constrained('imported_obsolete_documents')
                ->cascadeOnDelete();
            $table->foreignId('related_imported_obsolete_document_id')
                ->nullable()
                ->constrained('imported_obsolete_documents')
                ->restrictOnDelete();
            $table->foreignId('related_document_id')
                ->nullable()
                ->constrained('t_document')
                ->restrictOnDelete();
            $table->string('relation_type');
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('relation_type');
        });

        $this->addRelationCheckConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('imported_obsolete_document_relations');
        Schema::dropIfExists('imported_obsolete_document_files');
        Schema::dropIfExists('imported_obsolete_documents');
    }

    private function addRelationCheckConstraints(): void
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                'alter table imported_obsolete_document_relations add constraint imported_obsolete_relations_one_target_check check ((related_imported_obsolete_document_id is null) <> (related_document_id is null))',
            );
            DB::statement(
                'alter table imported_obsolete_document_relations add constraint imported_obsolete_relations_no_self_check check (related_imported_obsolete_document_id is null or imported_obsolete_document_id <> related_imported_obsolete_document_id)',
            );
        }

        if ($driver === 'pgsql') {
            DB::statement(
                'alter table imported_obsolete_document_relations add constraint imported_obsolete_relations_one_target_check check ((related_imported_obsolete_document_id is null) <> (related_document_id is null))',
            );
            DB::statement(
                'alter table imported_obsolete_document_relations add constraint imported_obsolete_relations_no_self_check check (related_imported_obsolete_document_id is null or imported_obsolete_document_id <> related_imported_obsolete_document_id)',
            );
        }
    }
};
