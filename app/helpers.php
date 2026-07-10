<?php

use App\Models\WccRecord;

if (! function_exists('money')) {
    /**
     * Ringgit, rounded to whole units — how every figure reads in the UI.
     */
    function money(float|int|string|null $value): string
    {
        return 'RM '.number_format((float) $value, 0);
    }
}

if (! function_exists('money_precise')) {
    function money_precise(float|int|string|null $value): string
    {
        return 'RM '.number_format((float) $value, 2);
    }
}

if (! function_exists('status_color')) {
    /**
     * Pipeline colours, matching the workflow stages in the original design.
     */
    function status_color(string $status): string
    {
        return match ($status) {
            WccRecord::DRAFT => '#a9bdd0',
            WccRecord::COSTED => '#38bdf8',
            WccRecord::SUBMITTED => '#f5a623',
            WccRecord::APPROVED => '#2dd4a7',
            WccRecord::RETURNED => '#f0716d',
            default => '#a9bdd0',
        };
    }
}

if (! function_exists('delta_chip')) {
    /**
     * Month-over-month change, as the class + label the KPI chip expects.
     *
     * @return array{class: string, label: string}
     */
    function delta_chip(float $current, float $previous): array
    {
        if ($previous == 0.0 && $current == 0.0) {
            return ['class' => 'z', 'label' => '—'];
        }

        if ($previous == 0.0) {
            return ['class' => 'up', 'label' => '▲ new'];
        }

        $pct = ($current - $previous) / $previous * 100;

        return [
            'class' => $pct >= 0 ? 'up' : 'dn',
            'label' => ($pct >= 0 ? '▲ +' : '▼ ').number_format($pct, 0).'%',
        ];
    }
}
