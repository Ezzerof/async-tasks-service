<?php

namespace App\Http\Controllers\SalesReport;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalesReportResource;
use App\Jobs\GenerateSalesReportJob;
use App\Models\SalesReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GenerateReportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $active = SalesReport::where('user_id', $request->user()->id)
            ->whereIn('status', [SalesReport::STATUS_PENDING, SalesReport::STATUS_PROCESSING])
            ->exists();

        if ($active) {
            return response()->json(['message' => 'A report is already being generated.'], 409);
        }

        $report = SalesReport::create([
            'user_id' => $request->user()->id,
            'status'  => SalesReport::STATUS_PENDING,
        ]);

        GenerateSalesReportJob::dispatch($report);

        return (new SalesReportResource($report))->response()->setStatusCode(202);
    }
}
