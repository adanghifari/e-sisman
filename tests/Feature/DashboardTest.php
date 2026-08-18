<?php

namespace Tests\Feature;

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
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_department_warning_popup_is_rendered_when_session_exists(): void
    {
        $user = User::factory()->create(['m_department_id' => null]);
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
}
