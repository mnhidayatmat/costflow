<?php

namespace App\Services;

use App\Models\WccRecord;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aggregations behind the dashboard and analytics pages.
 *
 * Every query is expressed with portable SQL so the same code runs on SQLite
 * locally and MySQL in production — no DATE_FORMAT / strftime, no window
 * functions. Month buckets are done with plain range predicates.
 */
class WccMetrics
{
    /**
     * Cost actually incurred: WCC2 actual once captured, otherwise WCC1 plan.
     */
    private function effectiveCost(): Expression
    {
        return DB::raw('CASE WHEN actual > 0 THEN actual ELSE planned_cost END');
    }

    private function profitSum(): Expression
    {
        return DB::raw('COALESCE(SUM(selling - (CASE WHEN actual > 0 THEN actual ELSE planned_cost END)), 0) as profit');
    }

    /**
     * Approved jobs whose decision landed in the given month.
     *
     * Bucketed on approved_at, not updated_at: re-saving an old record must
     * never move its selling value into the current month's books.
     *
     * @return array{win: float, profit: float, count: int, margin: float}
     */
    public function month(int $monthsBack = 0): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths($monthsBack);
        $end = (clone $start)->endOfMonth();

        $rows = WccRecord::approved()
            ->whereBetween('approved_at', [$start, $end])
            ->get(['selling', 'planned_cost', 'actual']);

        return $this->summarize($rows);
    }

    /**
     * @return array{win: float, profit: float, count: int, margin: float}
     */
    public function allTime(): array
    {
        return $this->summarize(WccRecord::approved()->get(['selling', 'planned_cost', 'actual']));
    }

    /**
     * @param  Collection<int, WccRecord>  $rows
     * @return array{win: float, profit: float, count: int, margin: float}
     */
    private function summarize(Collection $rows): array
    {
        $win = (float) $rows->sum(fn (WccRecord $r) => (float) $r->selling);
        $profit = (float) $rows->sum(fn (WccRecord $r) => $r->profit());
        $margin = $rows->isNotEmpty()
            ? (float) $rows->avg(fn (WccRecord $r) => $r->marginPercent())
            : 0.0;

        return [
            'win' => $win,
            'profit' => $profit,
            'count' => $rows->count(),
            'margin' => $margin,
        ];
    }

    /**
     * Approved selling value per department, biggest first.
     *
     * @return Collection<string, float>
     */
    public function departmentPerformance(): Collection
    {
        return WccRecord::approved()
            ->selectRaw('dept, COALESCE(SUM(selling), 0) as total')
            ->groupBy('dept')
            ->orderByDesc('total')
            ->pluck('total', 'dept')
            ->map(fn ($v) => (float) $v);
    }

    /**
     * Approved profit credited to each WCC's manager, biggest first.
     *
     * @return Collection<string, float>
     */
    public function managerPerformance(): Collection
    {
        return WccRecord::approved()
            ->select('manager')
            ->addSelect($this->profitSum())
            ->groupBy('manager')
            ->orderByDesc('profit')
            ->get()
            ->mapWithKeys(fn (WccRecord $r) => [
                filled($r->manager) ? $r->manager : 'Unassigned' => (float) $r->profit,
            ]);
    }

    /**
     * Record counts per workflow status, in pipeline order (zeros included).
     *
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $counts = WccRecord::selectRaw('status, COUNT(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        $ordered = [];
        foreach (config('costflow.statuses') as $status) {
            $ordered[$status] = (int) ($counts[$status] ?? 0);
        }

        return $ordered;
    }

    /**
     * Approved selling value for each of the last N months, oldest first.
     *
     * @return list<array{label: string, value: float}>
     */
    public function monthlyTrend(int $months = 6): array
    {
        $trend = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $start = Carbon::now()->startOfMonth()->subMonths($i);

            $trend[] = [
                'label' => $start->format('M'),
                'value' => (float) WccRecord::approved()
                    ->whereBetween('approved_at', [$start, (clone $start)->endOfMonth()])
                    ->sum('selling'),
            ];
        }

        return $trend;
    }

    /**
     * Share of decided jobs that came back for rework.
     */
    public function returnRate(): float
    {
        $decided = WccRecord::whereIn('status', [
            WccRecord::SUBMITTED,
            WccRecord::APPROVED,
            WccRecord::RETURNED,
        ])->count();

        if ($decided === 0) {
            return 0.0;
        }

        return WccRecord::where('status', WccRecord::RETURNED)->count() / $decided * 100;
    }

    /**
     * Headline counters for the dashboard hero and KPI strip.
     *
     * @return array<string, int>
     */
    public function headline(): array
    {
        return [
            'total' => WccRecord::count(),
            'approved' => WccRecord::approved()->count(),
            'awaiting' => WccRecord::where('status', WccRecord::SUBMITTED)->count(),
            'active' => WccRecord::active()->count(),
            'pending_wcc2' => WccRecord::approved()->where('actual', '<=', 0)->count(),
        ];
    }
}
