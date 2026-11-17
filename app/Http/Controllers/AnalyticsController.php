<?php

namespace App\Http\Controllers;

use App\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Analytics: benta ng Branded / Generic / Grocery sa Today, This Week, This Month at This Year.
 * Ang pag-grupo ay ginagawa sa PHP para gumana sa SQLite at MySQL.
 */
class AnalyticsController extends Controller
{
    public const PERIODS = [
        'today' => 'Today',
        'week'  => 'This Week',
        'month' => 'This Month',
        'year'  => 'This Year',
    ];

    public const TYPES = ['branded' => 'Branded', 'generic' => 'Generic', 'grocery' => 'Grocery'];

    public function index(Request $request)
    {
        $period = $request->validate(['period' => ['nullable', 'in:' . implode(',', array_keys(self::PERIODS))]])['period'] ?? 'week';
        [$from, $to, $prevFrom, $prevTo] = $this->range($period);

        $items = self::items($from, $to);
        $buckets = $this->buckets($period, $from);

        // Bawat bucket: benta ng bawat type
        $series = [];
        foreach ($buckets as $key => $label) {
            $series[$key] = ['label' => $label] + array_fill_keys(array_keys(self::TYPES), 0.0);
        }
        foreach ($items as $i) {
            $key = $this->bucketKey($period, Carbon::parse($i->sold_at));
            if (isset($series[$key])) {
                $series[$key][$i->type] = round($series[$key][$i->type] + (float) $i->net, 2);
            }
        }

        $totals = array_fill_keys(array_keys(self::TYPES), 0.0);
        $qty = array_fill_keys(array_keys(self::TYPES), 0);
        foreach ($items as $i) {
            $totals[$i->type] = round($totals[$i->type] + (float) $i->net, 2);
            $qty[$i->type] += (int) $i->quantity;
        }

        $prevTotals = array_fill_keys(array_keys(self::TYPES), 0.0);
        foreach (self::items($prevFrom, $prevTo) as $i) {
            $prevTotals[$i->type] = round($prevTotals[$i->type] + (float) $i->net, 2);
        }

        $grand = round(array_sum($totals), 2);
        $prevGrand = round(array_sum($prevTotals), 2);

        $top = $items->groupBy('product_name')
            ->map(fn ($g, $name) => ['name' => $name, 'type' => $g->first()->type, 'qty' => (int) $g->sum('quantity'), 'net' => round((float) $g->sum('net'), 2)])
            ->sortByDesc('net')->take(5)->values();

        return view('analytics.index', [
            'periods'    => self::PERIODS,
            'types'      => self::TYPES,
            'period'     => $period,
            'from'       => $from,
            'to'         => $to,
            'series'     => array_values($series),
            'totals'     => $totals,
            'qty'        => $qty,
            'prevTotals' => $prevTotals,
            'grand'      => $grand,
            'prevGrand'  => $prevGrand,
            'orders'     => $items->pluck('sale_id')->unique()->count(),
            'top'        => $top,
        ]);
    }

    /** [from, to, previous from, previous to] */
    private function range(string $period): array
    {
        $now = now();

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay(), $now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), $now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'year'  => [$now->copy()->startOfYear(), $now->copy()->endOfYear(), $now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            default => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay(), $now->copy()->subDays(13)->startOfDay(), $now->copy()->subDays(7)->endOfDay()],
        };
    }

    /** Mga hanay sa chart: oras (Today), araw (Week/Month) o buwan (Year). */
    private function buckets(string $period, Carbon $from): array
    {
        $out = [];

        if ($period === 'today') {
            for ($h = 6; $h <= 22; $h++) {
                $out[sprintf('%02d', $h)] = Carbon::createFromTime($h)->format('g A');
            }
        } elseif ($period === 'year') {
            for ($m = 1; $m <= 12; $m++) {
                $out[sprintf('%02d', $m)] = Carbon::create($from->year, $m, 1)->format('M');
            }
        } else {
            $days = $period === 'month' ? $from->daysInMonth : 7;
            for ($d = 0; $d < $days; $d++) {
                $day = $from->copy()->addDays($d);
                $out[$day->toDateString()] = $period === 'month' ? $day->format('j') : $day->format('D j');
            }
        }

        return $out;
    }

    private function bucketKey(string $period, Carbon $at): string
    {
        return match ($period) {
            'today' => sprintf('%02d', min(22, max(6, $at->hour))), // bago 6AM / pagkatapos 10PM: isinasama sa una/huling hanay
            'year'  => $at->format('m'),
            default => $at->toDateString(),
        };
    }

    /** Mga nabentang item (net ng discount) na may type; ginagamit din ng Dashboard. */
    public static function items(Carbon $from, Carbon $to)
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$from, $to])
            ->selectRaw("sale_items.product_name, sale_items.quantity, sales.id as sale_id, sales.created_at as sold_at,
                (sale_items.line_total - sale_items.discount) as net,
                COALESCE(products.type, 'generic') as type")
            ->get()
            ->each(fn ($i) => $i->type = array_key_exists($i->type, self::TYPES) ? $i->type : 'generic');
    }
}
