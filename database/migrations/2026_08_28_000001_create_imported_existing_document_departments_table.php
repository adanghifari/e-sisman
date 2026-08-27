<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('imported_existing_document_departments')) {
            return;
        }

        Schema::create('imported_existing_document_departments', function (Blueprint $table): void {
            $table->foreignId('imported_existing_document_id')
                ->constrained('imported_existing_documents')
                ->cascadeOnDelete();
            $table->foreignId('department_id')
                ->constrained('departments')
                ->restrictOnDelete();

            $table->primary(
                ['imported_existing_document_id', 'department_id'],
                'ied_departments_primary',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imported_existing_document_departments');
    }
};
