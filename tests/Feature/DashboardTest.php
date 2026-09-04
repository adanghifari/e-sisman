<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\ApprovalStatus;
use App\Models\BusinessFunction;
use App\Models\BusinessProcess;
use App\Models\Document;
use App\Models\DocumentDownloadLog;
use App\Models\DocumentLevel;
use App\Models\DocumentType;
use App\Models\StatusDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create([
            'nik' => '000000',
            'email' => 'developer@example.com',
        ]);
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_dashboard_needs_process_table_uses_real_tasks_only(): void
    {
        $user = User::factory()->create([
            'nik' => '000000',
            'email' => 'developer@example.com',
        ]);
        $submitter = User::factory()->create(['name' => 'Pengaju Real']);
        $status = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::PROPOSED]);
        $approvalStatus = ApprovalStatus::query()->firstOrCreate(
            ['kode_status' => ApprovalStatus::PENDING],
            ['nama_status' => 'Menunggu'],
        );
        $level = DocumentLevel::query()->firstOrCreate(
            ['kode' => 'level-2'],
            ['nama_level' => 'Level II', 'nama_dokumen' => 'Level II : Prosedur SKMBS', 'sort_order' => 2],
        );
        $type = DocumentType::query()->firstOrCreate(['nama_types' => 'Prosedur']);
        $businessProcess = BusinessProcess::create([
            'kode' => 'REAL',
            'nama_proses_bisnis' => 'Proses Real',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'REAL',
            'nama_proses_fungsi' => 'Fungsi Real',
        ]);
        $document = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $status->id,
            'm_document_types_id' => $type->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $submitter->id,
            'nama_dokumen' => 'Dokumen Real Dashboard',
            'nomor_dokumen' => 'PS-REAL-01',
            'nomor_revisi' => 0,
            'submitted_at' => now(),
        ]);

        Approval::create([
            't_document_id' => $document->id,
            'user_id' => $user->id,
            'assigned_by' => $submitter->id,
            'm_approval_status_id' => $approvalStatus->id,
            'stages' => 'Verifikasi Real',
            'assigned_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('PS-REAL-01')
            ->assertSee('Dokumen Real Dashboard')
            ->assertDontSee('KBS-PB-PR-001');
    }

    public function test_department_warning_popup_is_rendered_when_session_exists(): void
    {
        $user = User::factory()->create([
            'm_department_id' => null,
            'nik' => '000000',
            'email' => 'developer@example.com',
        ]);
        $this->actingAs($user);

        $response = $this
            ->withSession([
                'department_warning' => [
                    'title' => 'Department Belum Terdaftar',
                    'message' => 'Akun Anda belum terdaftar di department manapun. Silakan hubungi admin untuk melengkapi data department.',
                ],
            ])
            ->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('Department Belum Terdaftar')
            ->assertSee('Akun Anda belum terdaftar di department manapun.');
    }

    public function test_download_activity_keeps_revision_column_out_of_dashboard_text(): void
    {
        $user = User::factory()->create([
            'nik' => '000000',
            'email' => 'developer@example.com',
        ]);
        $status = StatusDocument::query()->firstOrCreate(['nama_status' => StatusDocument::APPROVED]);
        $level = DocumentLevel::query()->where('kode', 'level-2')->firstOrFail();
        $type = DocumentType::query()->firstOrCreate(['nama_types' => 'Prosedur']);
        $businessProcess = BusinessProcess::create([
            'kode' => 'SMR',
            'nama_proses_bisnis' => 'Sistem Manajemen Risiko',
        ]);
        $businessFunction = BusinessFunction::create([
            'kode' => 'QA',
            'nama_proses_fungsi' => 'Quality Assurance',
        ]);
        $document = Document::create([
            'm_document_level_id' => $level->id,
            'm_status_document_id' => $status->id,
            'm_document_types_id' => $type->id,
            'm_proses_bisnis_id' => $businessProcess->id,
            'm_proses_fungsi_id' => $businessFunction->id,
            'user_id' => $user->id,
            'nama_dokumen' => 'Dokumen Revisi Promoted',
            'nomor_dokumen' => 'PS-SMR-SNAP',
            'nomor_revisi' => 1,
        ]);
        DocumentDownloadLog::create([
            't_document_id' => $document->id,
            'document_name_snapshot' => 'Dokumen Revisi Promoted',
            'document_number_snapshot' => 'FMPS-SMR-SNAP-01',
            'document_revision_snapshot' => 1,
            'download_context' => 'approval',
            'user_id' => $user->id,
            'downloaded_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('FMPS-SMR-SNAP-01')
            ->assertDontSee('FMPS-SMR-SNAP-01 00.01');

        $this->actingAs($user)
            ->get(route('activity-log.index'))
            ->assertOk()
            ->assertSee('FMPS-SMR-SNAP-01')
            ->assertSee('00.01');
    }
}
