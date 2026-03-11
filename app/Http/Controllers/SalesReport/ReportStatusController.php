<?php

namespace App\Http\Controllers\SalesReport;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalesReportResource;
use App\Models\SalesReport;
use Illuminate\Http\Request;

class ReportStatusController extends Controller
{
    public function __invoke(Request $request, SalesReport $report): SalesReportResource
    {
        $user      = $request->user();
        $canAccess = $report->user_id === $user->id
            || ($user->isAdmin() && $report->user->role === 'user');

        abort_if(! $canAccess, 403);

        return new SalesReportResource($report);
    }
}
