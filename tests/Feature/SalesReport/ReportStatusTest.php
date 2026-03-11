<?php

namespace Tests\Feature\SalesReport;

use App\Models\SalesReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportStatusTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(User $user)
    {
        return $this->actingAs($user, 'web')->withHeader('Origin', 'http://localhost');
    }

    public function test_user_can_check_status_of_their_own_report(): void
    {
        $user   = User::factory()->create(['role' => 'user']);
        $report = SalesReport::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAsUser($user)
            ->getJson("/api/v1/reports/sales/{$report->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'status', 'progress', 'row_count', 'has_file', 'created_at', 'updated_at'],
            ]);
    }

    public function test_admin_can_check_status_of_a_regular_user_report(): void
    {
        $user   = User::factory()->create(['role' => 'user']);
        $admin  = User::factory()->create(['role' => 'admin']);
        $report = SalesReport::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAsUser($admin)
            ->getJson("/api/v1/reports/sales/{$report->id}");

        $response->assertOk();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $report = SalesReport::factory()->create();

        $response = $this->getJson("/api/v1/reports/sales/{$report->id}");

        $response->assertUnauthorized();
    }

    public function test_user_cannot_access_another_users_report(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $other = User::factory()->create(['role' => 'user']);
        $report = SalesReport::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAsUser($other)
            ->getJson("/api/v1/reports/sales/{$report->id}");

        $response->assertForbidden();
    }

    public function test_non_existent_report_returns_404(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAsUser($user)
            ->getJson('/api/v1/reports/sales/99999');

        $response->assertNotFound();
    }

    public function test_admin_cannot_access_another_admins_report(): void
    {
        $adminOwner  = User::factory()->create(['role' => 'admin']);
        $adminOther  = User::factory()->create(['role' => 'admin']);
        $report      = SalesReport::factory()->create(['user_id' => $adminOwner->id]);

        $response = $this->actingAsUser($adminOther)
            ->getJson("/api/v1/reports/sales/{$report->id}");

        $response->assertForbidden();
    }
}
