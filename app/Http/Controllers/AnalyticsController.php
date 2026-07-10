<?php

namespace App\Http\Controllers;

use App\Services\WccMetrics;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function __invoke(WccMetrics $metrics): View
    {
        $departments = $metrics->departmentPerformance();

        return view('pages.analytics', [
            'allTime' => $metrics->allTime(),
            'departments' => $departments,
            'topDepartment' => $departments->keys()->first(),
            'topDepartmentValue' => $departments->first() ?? 0.0,
            'managers' => $metrics->managerPerformance(),
            'statusCounts' => $metrics->statusCounts(),
            'trend' => $metrics->monthlyTrend(6),
            'returnRate' => $metrics->returnRate(),
        ]);
    }
}
