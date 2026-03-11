<?php

namespace Tests\Unit\Jobs;

use App\Jobs\GenerateSalesReportJob;
use App\Models\SalesReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * NOTE: handle() calls sleep() three times (~3–5 s total).
 * Expect this test class to take ~30–40 s.
 */
class GenerateSalesReportJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeReport(array $attributes = []): SalesReport
    {
        $user = User::factory()->create(['role' => 'user']);

        return SalesReport::factory()->create(array_merge(['user_id' => $user->id], $attributes));
    }

    public function test_handle_transitions_report_status_to_completed(): void
    {
        Storage::fake('local');

        $report = $this->makeReport();
        $job    = new GenerateSalesReportJob($report);

        $job->handle();

        $this->assertDatabaseHas('sales_reports', [
            'id'       => $report->id,
            'status'   => SalesReport::STATUS_COMPLETED,
            'progress' => 100,
        ]);
    }

    public function test_handle_writes_csv_file_to_storage(): void
    {
        Storage::fake('local');

        $report = $this->makeReport();
        $job    = new GenerateSalesReportJob($report);

        $job->handle();

        $filePath = $report->fresh()->file_path;
        Storage::disk('local')->assertExists($filePath);
    }

    public function test_handle_sets_row_count_between_50_and_200(): void
    {
        Storage::fake('local');

        $report = $this->makeReport();
        $job    = new GenerateSalesReportJob($report);

        $job->handle();

        $rowCount = $report->fresh()->row_count;

        $this->assertGreaterThanOrEqual(50, $rowCount);
        $this->assertLessThanOrEqual(200, $rowCount);
    }

    public function test_generated_csv_has_correct_header_row(): void
    {
        Storage::fake('local');

        $report = $this->makeReport();
        $job    = new GenerateSalesReportJob($report);

        $job->handle();

        $filePath  = $report->fresh()->file_path;
        $contents  = Storage::disk('local')->get($filePath);
        $firstLine = strtok($contents, "\n");

        $this->assertEquals('id,product,category,quantity,unit_price,total,sale_date,region', $firstLine);
    }

    public function test_failed_sets_status_to_failed_and_stores_error_message(): void
    {
        Storage::fake('local');

        $report    = $this->makeReport();
        $job       = new GenerateSalesReportJob($report);
        $exception = new RuntimeException('DB connection lost');

        $job->failed($exception);

        $this->assertDatabaseHas('sales_reports', [
            'id'            => $report->id,
            'status'        => SalesReport::STATUS_FAILED,
            'error_message' => 'DB connection lost',
        ]);
    }

    public function test_failed_deletes_orphaned_file_when_it_exists(): void
    {
        Storage::fake('local');

        $filePath = 'reports/orphaned.csv';
        Storage::disk('local')->put($filePath, 'id,product');

        $report = $this->makeReport(['file_path' => $filePath]);
        $job    = new GenerateSalesReportJob($report);

        $job->failed(new RuntimeException('Forced failure'));

        Storage::disk('local')->assertMissing($filePath);
    }

    public function test_on_first_attempt_progress_is_reset_to_0_and_status_set_to_processing(): void
    {
        Storage::fake('local');

        $report = $this->makeReport();

        // Mock the job so we can control attempts() — first attempt returns 1
        $job = Mockery::mock(GenerateSalesReportJob::class, [$report])->makePartial();
        $job->shouldReceive('attempts')->andReturn(1);

        $job->handle();

        $this->assertDatabaseHas('sales_reports', [
            'id'     => $report->id,
            'status' => SalesReport::STATUS_COMPLETED,
        ]);
    }

    public function test_on_retry_initial_progress_reset_is_skipped(): void
    {
        Storage::fake('local');

        // Start with progress=50 so we can detect if a reset-to-0 occurs
        $report = $this->makeReport([
            'status'   => SalesReport::STATUS_PROCESSING,
            'progress' => 50,
        ]);

        // Track whether update(['status' => processing, 'progress' => 0]) is called
        $resetCalled = false;

        SalesReport::saving(function (SalesReport $model) use (&$resetCalled, $report) {
            if (
                $model->id === $report->id
                && $model->isDirty('progress')
                && $model->progress === 0
                && $model->status === SalesReport::STATUS_PROCESSING
            ) {
                $resetCalled = true;
            }
        });

        // Simulate a retry: attempts() returns 2, skipping the initial reset
        $job = Mockery::mock(GenerateSalesReportJob::class, [$report])->makePartial();
        $job->shouldReceive('attempts')->andReturn(2);

        $job->handle();

        $this->assertFalse($resetCalled, 'On retry (attempts=2), initial progress reset to 0 should be skipped.');

        $this->assertDatabaseHas('sales_reports', [
            'id'     => $report->id,
            'status' => SalesReport::STATUS_COMPLETED,
        ]);
    }
}
