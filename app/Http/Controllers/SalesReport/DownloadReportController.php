<?php

namespace App\Http\Controllers\SalesReport;

use App\Http\Controllers\Controller;
use App\Models\SalesReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadReportController extends Controller
{
    public function __invoke(Request $request, SalesReport $report): StreamedResponse|JsonResponse
    {
        $user      = $request->user();
        $canAccess = $report->user_id === $user->id
            || ($user->isAdmin() && $report->user->role === 'user');

        abort_if(! $canAccess, 403);

        if ($report->status !== SalesReport::STATUS_COMPLETED) {
            return response()->json([
                'message' => 'Report is not ready for download.',
                'status'  => $report->status,
            ], 409);
        }

        return Storage::disk('local')->download($report->file_path, 'sales-report.csv');
    }
}
