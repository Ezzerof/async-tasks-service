<?php

namespace Database\Factories;

use App\Models\SalesReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesReport>
 */
class SalesReportFactory extends Factory
{
    protected $model = SalesReport::class;

    public function definition(): array
    {
        return [
            'user_id'       => User::factory(['role' => 'user']),
            'status'        => SalesReport::STATUS_PENDING,
            'progress'      => 0,
            'file_path'     => null,
            'row_count'     => null,
            'error_message' => null,
        ];
    }

    public function processing(): static
    {
        return $this->state([
            'status'   => SalesReport::STATUS_PROCESSING,
            'progress' => 50,
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status'    => SalesReport::STATUS_COMPLETED,
            'progress'  => 100,
            'file_path' => 'reports/test.csv',
            'row_count' => 100,
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status'        => SalesReport::STATUS_FAILED,
            'progress'      => 0,
            'error_message' => 'Something went wrong',
        ]);
    }
}
