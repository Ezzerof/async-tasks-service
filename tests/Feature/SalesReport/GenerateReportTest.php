<?php

namespace Tests\Feature\SalesReport;

use App\Jobs\GenerateSalesReportJob;
use App\Models\SalesReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GenerateReportTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(User $user)
    {
        return $this->actingAs($user, 'web')->withHeader('Origin', 'http://localhost');
    }

    public function test_authenticated_user_can_trigger_report_generation(): void
    {
        Queue::fake();

        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAsUser($user)->postJson('/api/v1/reports/sales');

        $response->assertStatus(202)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.progress', 0)
            ->assertJsonPath('data.has_file', false)
            ->assertJsonPath('data.row_count', null);
    }

    public function test_report_row_is_created_in_database_with_correct_user_and_status(): void
    {
        Queue::fake();

        $user = User::factory()->create(['role' => 'user']);

        $this->actingAsUser($user)->postJson('/api/v1/reports/sales');

        $this->assertDatabaseHas('sales_reports', [
            'user_id' => $user->id,
            'status'  => 'pending',
        ]);
    }

    public function test_job_is_dispatched_to_the_queue(): void
    {
        Queue::fake();

        $user = User::factory()->create(['role' => 'user']);

        $this->actingAsUser($user)->postJson('/api/v1/reports/sales');

        Queue::assertPushed(GenerateSalesReportJob::class);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/reports/sales');

        $response->assertUnauthorized();
    }

    public function test_user_with_pending_report_receives_409(): void
    {
        Queue::fake();

        $user = User::factory()->create(['role' => 'user']);
        SalesReport::factory()->create(['user_id' => $user->id, 'status' => SalesReport::STATUS_PENDING]);

        $response = $this->actingAsUser($user)->postJson('/api/v1/reports/sales');

        $response->assertStatus(409)
            ->assertJsonPath('message', 'A report is already being generated.');
    }

    public function test_user_with_processing_report_receives_409(): void
    {
        Queue::fake();

        $user = User::factory()->create(['role' => 'user']);
        SalesReport::factory()->processing()->create(['user_id' => $user->id]);

        $response = $this->actingAsUser($user)->postJson('/api/v1/reports/sales');

        $response->assertStatus(409)
            ->assertJsonPath('message', 'A report is already being generated.');
    }

    public function test_user_with_completed_report_can_generate_a_new_one(): void
    {
        Queue::fake();

        $user = User::factory()->create(['role' => 'user']);
        SalesReport::factory()->completed()->create(['user_id' => $user->id]);

        $response = $this->actingAsUser($user)->postJson('/api/v1/reports/sales');

        $response->assertStatus(202);
    }

    public function test_user_with_failed_report_can_generate_a_new_one(): void
    {
        Queue::fake();

        $user = User::factory()->create(['role' => 'user']);
        SalesReport::factory()->failed()->create(['user_id' => $user->id]);

        $response = $this->actingAsUser($user)->postJson('/api/v1/reports/sales');

        $response->assertStatus(202);
    }

    public function test_two_different_users_can_generate_reports_simultaneously(): void
    {
        Queue::fake();

        $userA = User::factory()->create(['role' => 'user']);
        $userB = User::factory()->create(['role' => 'user']);

        // Seed a pending report for userA to simulate they already triggered one
        SalesReport::factory()->create(['user_id' => $userA->id, 'status' => SalesReport::STATUS_PENDING]);

        // userB should not be blocked by userA's pending report
        $responseB = $this->actingAsUser($userB)->postJson('/api/v1/reports/sales');
        $responseB->assertStatus(202);

        // userA IS blocked (their own pending report)
        $responseA = $this->actingAsUser($userA)->postJson('/api/v1/reports/sales');
        $responseA->assertStatus(409);
    }

    public function test_response_does_not_expose_file_path_key(): void
    {
        Queue::fake();

        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAsUser($user)->postJson('/api/v1/reports/sales');

        $response->assertStatus(202)
            ->assertJsonMissingPath('data.file_path');
    }
}
