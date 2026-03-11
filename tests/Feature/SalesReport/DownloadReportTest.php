<?php

namespace Tests\Feature\SalesReport;

use App\Models\SalesReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DownloadReportTest extends TestCase
{
    use RefreshDatabase;

    private const CSV_CONTENT = "id,product,category,quantity,unit_price,total,sale_date,region\n1,widget,gadgets,5,9.99,49.95,2024-01-01,\"North East\"";

    private function actingAsUser(User $user)
    {
        return $this->actingAs($user, 'web')->withHeader('Origin', 'http://localhost');
    }

    private function seedReportFile(): void
    {
        Storage::disk('local')->put('reports/test.csv', self::CSV_CONTENT);
    }

    public function test_user_can_download_their_own_completed_report(): void
    {
        Storage::fake('local');
        $this->seedReportFile();

        $user   = User::factory()->create(['role' => 'user']);
        $report = SalesReport::factory()->completed()->create(['user_id' => $user->id]);

        $response = $this->actingAsUser($user)
            ->get("/api/v1/reports/sales/{$report->id}/download");

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition');

        $this->assertStringContainsString(
            'sales-report.csv',
            $response->headers->get('Content-Disposition')
        );
    }

    public function test_response_body_contains_valid_csv_with_correct_header_row(): void
    {
        Storage::fake('local');
        $this->seedReportFile();

        $user   = User::factory()->create(['role' => 'user']);
        $report = SalesReport::factory()->completed()->create(['user_id' => $user->id]);

        $response = $this->actingAsUser($user)
            ->get("/api/v1/reports/sales/{$report->id}/download");

        $response->assertOk();

        $body     = $response->streamedContent();
        $firstLine = strtok($body, "\n");

        $this->assertEquals('id,product,category,quantity,unit_price,total,sale_date,region', $firstLine);
    }

    public function test_admin_can_download_regular_users_completed_report(): void
    {
        Storage::fake('local');
        $this->seedReportFile();

        $user   = User::factory()->create(['role' => 'user']);
        $admin  = User::factory()->create(['role' => 'admin']);
        $report = SalesReport::factory()->completed()->create(['user_id' => $user->id]);

        $response = $this->actingAsUser($admin)
            ->get("/api/v1/reports/sales/{$report->id}/download");

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        Storage::fake('local');

        $report = SalesReport::factory()->completed()->create();

        // Use getJson to send Accept: application/json, preventing a login-route redirect
        $response = $this->getJson("/api/v1/reports/sales/{$report->id}/download");

        $response->assertUnauthorized();
    }

    public function test_user_cannot_download_another_users_report(): void
    {
        Storage::fake('local');
        $this->seedReportFile();

        $owner = User::factory()->create(['role' => 'user']);
        $other = User::factory()->create(['role' => 'user']);
        $report = SalesReport::factory()->completed()->create(['user_id' => $owner->id]);

        $response = $this->actingAsUser($other)
            ->get("/api/v1/reports/sales/{$report->id}/download");

        $response->assertForbidden();
    }

    public function test_non_existent_report_returns_404(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAsUser($user)
            ->get('/api/v1/reports/sales/99999/download');

        $response->assertNotFound();
    }

    public function test_pending_report_cannot_be_downloaded(): void
    {
        Storage::fake('local');

        $user   = User::factory()->create(['role' => 'user']);
        $report = SalesReport::factory()->create(['user_id' => $user->id]); // default: pending

        $response = $this->actingAsUser($user)
            ->getJson("/api/v1/reports/sales/{$report->id}/download");

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Report is not ready for download.')
            ->assertJsonPath('status', 'pending');
    }

    public function test_processing_report_cannot_be_downloaded(): void
    {
        Storage::fake('local');

        $user   = User::factory()->create(['role' => 'user']);
        $report = SalesReport::factory()->processing()->create(['user_id' => $user->id]);

        $response = $this->actingAsUser($user)
            ->getJson("/api/v1/reports/sales/{$report->id}/download");

        $response->assertStatus(409)
            ->assertJsonPath('status', 'processing');
    }

    public function test_failed_report_cannot_be_downloaded(): void
    {
        Storage::fake('local');

        $user   = User::factory()->create(['role' => 'user']);
        $report = SalesReport::factory()->failed()->create(['user_id' => $user->id]);

        $response = $this->actingAsUser($user)
            ->getJson("/api/v1/reports/sales/{$report->id}/download");

        $response->assertStatus(409)
            ->assertJsonPath('status', 'failed');
    }

    public function test_admin_cannot_download_another_admins_report(): void
    {
        Storage::fake('local');
        $this->seedReportFile();

        $adminOwner = User::factory()->create(['role' => 'admin']);
        $adminOther = User::factory()->create(['role' => 'admin']);
        $report     = SalesReport::factory()->completed()->create(['user_id' => $adminOwner->id]);

        $response = $this->actingAsUser($adminOther)
            ->get("/api/v1/reports/sales/{$report->id}/download");

        $response->assertForbidden();
    }

    public function test_completed_report_with_missing_file_returns_500(): void
    {
        Storage::fake('local');
        // File intentionally NOT seeded — documents current behaviour (no file-existence guard)

        $user   = User::factory()->create(['role' => 'user']);
        $report = SalesReport::factory()->completed()->create(['user_id' => $user->id]);

        $response = $this->actingAsUser($user)
            ->get("/api/v1/reports/sales/{$report->id}/download");

        $response->assertStatus(500);
    }
}
