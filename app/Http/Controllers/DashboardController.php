<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\WccMetrics;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(WccMetrics $metrics): View
    {
        $current = $metrics->month(0);
        $previous = $metrics->month(1);

        // Six-month sparklines for the "this month" KPI cards.
        $history = collect(range(5, 0))->map(fn (int $back) => $metrics->month($back));

        return view('pages.dashboard', [
            'headline' => $metrics->headline(),
            'current' => $current,
            'previous' => $previous,
            'winSpark' => $history->pluck('win')->all(),
            'profitSpark' => $history->pluck('profit')->all(),
            'statusCounts' => $metrics->statusCounts(),
            'departments' => $metrics->departmentPerformance(),
            'managers' => $metrics->managerPerformance(),
            'recentActivity' => AuditLog::latest('created_at')->limit(6)->get(),
        ]);
    }
}
