<?php

namespace App\Jobs;

use App\Models\SalesReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Faker\Factory as Faker;
use Throwable;

class GenerateSalesReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(public readonly SalesReport $report)
    {
        $this->queue = 'reports';
    }

    public function handle(): void
    {
        // Only reset progress on the first attempt
        if ($this->attempts() === 1) {
            $this->report->update([
                'status'   => SalesReport::STATUS_PROCESSING,
                'progress' => 0,
            ]);
        }

        // Stage 1 — simulate data fetch
        sleep(rand(1, 2));
        $this->report->update(['progress' => 25]);

        // Stage 2 — simulate data processing
        sleep(rand(1, 2));
        $this->report->update(['progress' => 50]);

        // Stage 3 — generate CSV
        $faker   = Faker::create();
        $rowCount = rand(50, 200);

        $lines   = ["id,product,category,quantity,unit_price,total,sale_date,region"];
        for ($i = 1; $i <= $rowCount; $i++) {
            $quantity   = rand(1, 100);
            $unitPrice  = round($faker->randomFloat(2, 5, 500), 2);
            $total      = round($quantity * $unitPrice, 2);
            $lines[]    = implode(',', [
                $i,
                '"' . str_replace('"', '""', $faker->word()) . '"',
                '"' . str_replace('"', '""', $faker->word()) . '"',
                $quantity,
                $unitPrice,
                $total,
                $faker->date(),
                '"' . $faker->state() . '"',
            ]);
        }

        $filePath = 'reports/' . Str::uuid() . '.csv';
        Storage::disk('local')->put($filePath, implode("\n", $lines));
        $this->report->update(['progress' => 75]);

        // Stage 4 — finalise
        sleep(1);
        $this->report->update([
            'status'    => SalesReport::STATUS_COMPLETED,
            'progress'  => 100,
            'file_path' => $filePath,
            'row_count' => $rowCount,
        ]);
    }

    public function failed(Throwable $e): void
    {
        if ($this->report->file_path && Storage::disk('local')->exists($this->report->file_path)) {
            Storage::disk('local')->delete($this->report->file_path);
        }

        $this->report->update([
            'status'        => SalesReport::STATUS_FAILED,
            'error_message' => $e->getMessage(),
        ]);
    }
}
