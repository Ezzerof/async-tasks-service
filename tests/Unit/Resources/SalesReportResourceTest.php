<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\SalesReportResource;
use App\Models\SalesReport;
use Illuminate\Http\Request;
use Tests\TestCase;

class SalesReportResourceTest extends TestCase
{
    private function makeResource(array $attributes): array
    {
        $report   = new SalesReport($attributes);
        $resource = new SalesReportResource($report);

        return $resource->toArray(new Request());
    }

    public function test_has_file_is_false_when_file_path_is_null(): void
    {
        $data = $this->makeResource(['file_path' => null]);

        $this->assertFalse($data['has_file']);
    }

    public function test_has_file_is_true_when_file_path_is_set(): void
    {
        $data = $this->makeResource(['file_path' => 'reports/some-file.csv']);

        $this->assertTrue($data['has_file']);
    }

    public function test_row_count_is_populated_for_completed_report(): void
    {
        $data = $this->makeResource([
            'status'    => SalesReport::STATUS_COMPLETED,
            'progress'  => 100,
            'file_path' => 'reports/test.csv',
            'row_count' => 100,
        ]);

        $this->assertEquals(100, $data['row_count']);
    }

    public function test_file_path_key_is_absent_from_serialised_output(): void
    {
        $data = $this->makeResource(['file_path' => 'reports/test.csv']);

        $this->assertArrayNotHasKey('file_path', $data);
    }
}
