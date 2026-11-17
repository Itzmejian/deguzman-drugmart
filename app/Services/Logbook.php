<?php

namespace App\Services;

use App\Models\DailyStock;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ang Daily Logbook ng botika (Branded | Generic | Grocery).
 * - Ngayong araw: live (nagbabago habang may benta).
 * - Nakaraang mga araw: naka-save sa `daily_stocks`, kaya makikita ang "last stock" kahit lumipas na ang araw.
 */
class Logbook
{
    public const TYPES = ['branded' => 'Branded', 'generic' => 'Generic', 'grocery' => 'Grocery'];

    /** Hanggang ilang araw pabalik ang awtomatikong ise-save kung may na-miss (hal. sarado ang PC). */
    private const BACKFILL_DAYS = 90;

    /** Buong pahina ng isang araw. */
    public function day(Carbon $day): array
    {
        $day = $day->copy()->startOfDay();
        $isToday = $day->isSameDay(today());

        if (! $isToday) {
            $this->saveDay($day);
        }

        $rows = $isToday ? $this->compute($day) : $this->load($day);
        $rows = $this->decorate($rows, $day);

        $entries = $this->entries($day);
        $columns = collect(self::TYPES)->map(fn ($label, $k) => [
            'label'    => $label,
            'entries'  => $entries->get($k, collect()),
            'products' => $rows->where('type', $k)->values(),
            'total'    => round((float) $entries->get($k, collect())->sum('net'), 2),
            'qty'      => (int) $entries->get($k, collect())->sum('qty'),
        ]);

        return [
            'day'     => $day,
            'isToday' => $isToday,
            'saved'   => ! $isToday && DailyStock::whereDate('date', $day)->exists(),
            'rows'    => $rows,
            'columns' => $columns,
            'total'   => round($columns->sum('total'), 2),
            'orders'  => Sale::completed()->whereBetween('created_at', [$day, $day->copy()->endOfDay()])->count(),
            'counts'  => [
                'sold'     => $rows->where('sold', '>', 0)->count(),
                'low'      => $rows->where('low', true)->count(),
                'expiring' => $rows->where('expiring', true)->count(),
                'expired'  => $rows->where('expired', true)->count(),
            ],
        ];
    }

    /** I-save ang lahat ng araw na hindi pa naka-save, hanggang kahapon. */
    public function saveMissingDays(): int
    {
        $yesterday = today()->subDay();
        $last = DailyStock::max('date');
        $first = $last ? Carbon::parse($last)->addDay() : $this->firstActivityDay();
        if (! $first) {
            return 0;
        }
        $first = $first->max($yesterday->copy()->subDays(self::BACKFILL_DAYS));

        $n = 0;
        for ($d = $first->copy(); $d->lte($yesterday); $d->addDay()) {
            $n += $this->saveDay($d) ? 1 : 0;
        }

        return $n;
    }

    /** I-save ang isang (nakaraang) araw kung wala pa. */
    public function saveDay(Carbon $day): bool
    {
        $day = $day->copy()->startOfDay();
        if (! $day->lt(today()) || DailyStock::whereDate('date', $day)->exists()) {
            return false;
        }

        $rows = $this->compute($day);
        if ($rows->isEmpty()) {
            return false;
        }

        $now = now();
        DB::transaction(function () use ($rows, $day, $now) {
            foreach ($rows->chunk(200) as $chunk) {
                DailyStock::insert($chunk->map(fn ($r) => [
                    'date' => $day->toDateString(), 'product_id' => $r->id, 'product_name' => $r->name, 'unit' => $r->unit,
                    'type' => $r->type, 'rx' => $r->rx, 'price' => $r->price, 'expiration_date' => $r->expiration_date?->toDateString(),
                    'opening' => $r->opening, 'sold' => $r->sold, 'added' => $r->added, 'removed' => $r->removed,
                    'closing' => $r->closing, 'sales_amount' => $r->amount, 'created_at' => $now, 'updated_at' => $now,
                ])->values()->all());
            }
        });

        return true;
    }

    /** Kinukuwenta ang stock ng bawat produkto para sa isang araw mula sa StockMovement. */
    private function compute(Carbon $day): Collection
    {
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();

        $after = StockMovement::where('created_at', '>', $end)
            ->groupBy('product_id')->selectRaw('product_id, SUM(`change`) as c')->pluck('c', 'product_id');

        $moves = StockMovement::whereBetween('created_at', [$start, $end])
            ->groupBy('product_id')
            ->selectRaw("product_id,
                SUM(CASE WHEN reason = 'sale' THEN -`change` ELSE 0 END) as sold,
                SUM(CASE WHEN reason <> 'sale' AND `change` > 0 THEN `change` ELSE 0 END) as added,
                SUM(CASE WHEN reason <> 'sale' AND `change` < 0 THEN -`change` ELSE 0 END) as removed")
            ->get()->keyBy('product_id');

        $sales = $this->items($start, $end)
            ->groupBy('sale_items.product_id')
            ->selectRaw('sale_items.product_id, SUM(sale_items.line_total - sale_items.discount) as net')
            ->pluck('net', 'sale_items.product_id');

        return Product::withTrashed()
            ->where('created_at', '<=', $end)
            ->where(fn ($q) => $q->whereNull('deleted_at')->orWhere('deleted_at', '>', $start))
            ->orderBy('name')->get()
            ->map(function (Product $p) use ($after, $moves, $sales) {
                $m = $moves->get($p->id);
                $closing = max(0, (int) $p->stock - (int) ($after[$p->id] ?? 0));
                $sold = (int) ($m->sold ?? 0);
                $added = (int) ($m->added ?? 0);
                $removed = (int) ($m->removed ?? 0);

                return (object) [
                    'id' => $p->id, 'name' => $p->name, 'unit' => $p->unit,
                    'type' => array_key_exists($p->type, self::TYPES) ? $p->type : 'generic',
                    'rx' => $p->category === 'rx', 'price' => (float) $p->price, 'expiration_date' => $p->expiration_date,
                    'opening' => max(0, $closing + $sold - $added + $removed),
                    'sold' => $sold, 'added' => $added, 'removed' => $removed, 'closing' => $closing,
                    'amount' => round((float) ($sales[$p->id] ?? 0), 2),
                ];
            });
    }

    private function load(Carbon $day): Collection
    {
        return DailyStock::whereDate('date', $day)->orderBy('product_name')->get()->map(fn (DailyStock $s) => (object) [
            'id' => $s->product_id, 'name' => $s->product_name, 'unit' => $s->unit, 'type' => $s->type, 'rx' => $s->rx,
            'price' => (float) $s->price, 'expiration_date' => $s->expiration_date,
            'opening' => $s->opening, 'sold' => $s->sold, 'added' => $s->added, 'removed' => $s->removed,
            'closing' => $s->closing, 'amount' => (float) $s->sales_amount,
        ]);
    }

    /** Mga kulay/paalala: expired, malapit nang mag-expire, low stock. */
    private function decorate(Collection $rows, Carbon $day): Collection
    {
        $lowAt = (int) Setting::get('low_stock_below');
        $warnDays = (int) Setting::get('expiry_warn_days');

        return $rows->map(function ($r) use ($day, $lowAt, $warnDays) {
            $left = $r->expiration_date ? (int) $day->diffInDays($r->expiration_date->copy()->startOfDay(), false) : null;
            $r->days_left = $left;
            $r->expired = $left !== null && $left < 0;
            $r->expiring = $left !== null && $left > 0 && $left <= $warnDays;
            $r->low = $r->closing <= $lowAt;

            return $r;
        })->sortBy([fn ($a, $b) => ($b->sold <=> $a->sold) ?: strcasecmp($a->name, $b->name)])->values();
    }

    /** Bawat benta bilang isang linya, ayon sa oras, hinati sa Branded / Generic / Grocery. */
    private function entries(Carbon $day): Collection
    {
        return $this->items($day->copy()->startOfDay(), $day->copy()->endOfDay())
            ->selectRaw("sale_items.product_name, sale_items.quantity as qty, (sale_items.line_total - sale_items.discount) as net,
                COALESCE(products.type, 'generic') as t, sales.created_at as sold_at, sales.invoice_no")
            ->orderBy('sales.created_at')->orderBy('sale_items.id')->get()
            ->each(fn ($e) => $e->t = array_key_exists($e->t, self::TYPES) ? $e->t : 'generic')
            ->groupBy('t');
    }

    private function items(Carbon $from, Carbon $to)
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$from, $to]);
    }

    private function firstActivityDay(): ?Carbon
    {
        $dates = array_filter([Product::withTrashed()->min('created_at'), StockMovement::min('created_at')]);

        return $dates ? Carbon::parse(min($dates))->startOfDay() : null;
    }
}
