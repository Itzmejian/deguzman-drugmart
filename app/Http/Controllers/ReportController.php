<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public const TYPES = [
        'detailed'   => 'Sales Report (Detailed)',
        'by_product' => 'Sales by Product (Summary)',
    ];

    /** Pang-filter ng report: ayon sa logbook (Branded/Generic/Grocery) o Classification (Rx/OTC). */
    public const CATEGORIES = [
        'all'     => 'All Products',
        'branded' => 'Branded',
        'generic' => 'Generic',
        'grocery' => 'Grocery',
    ];

    public function index(Request $request)
    {
        [$type, $from, $to, $cat] = $this->filters($request);

        $rows = $this->query($type, $from, $to, $cat)->paginate(10)->withQueryString();
        $breakdown = $this->breakdown($from, $to);

        return view('reports.index', [
            'types'      => self::TYPES,
            'categories' => self::CATEGORIES,
            'type'       => $type,
            'cat'        => $cat,
            'from'       => $from,
            'to'         => $to,
            'rows'       => $rows,
            'breakdown'  => $breakdown,
            'revenue'    => $cat === 'all' ? $this->revenue($from, $to) : $this->filteredRevenue($from, $to, $cat),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$type, $from, $to, $cat] = $this->filters($request);

        $rows = $this->query($type, $from, $to, $cat)->get();

        $filename = sprintf('dg-drugmart-%s-%s-%s_to_%s.csv', $type, $cat, $from->toDateString(), $to->toDateString());

        return response()->streamDownload(function () use ($rows, $type) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM para sa Excel
            fputcsv($out, $type === 'detailed'
                ? ['Date', 'Invoice', 'Product', 'Type', 'Quantity', 'Amount (before discount)', 'Senior/PWD Discount', 'Net Amount']
                : ['Product', 'Type', 'Total Quantity', 'Amount (before discount)', 'Senior/PWD Discount', 'Net Amount']);

            foreach ($rows as $r) {
                fputcsv($out, $type === 'detailed'
                    ? [Carbon::parse($r->sold_at)->format('Y-m-d H:i'), $r->invoice_no, $r->product_name, ucfirst($r->product_type), $r->quantity, $r->line_total, $r->discount, $r->net_total]
                    : [$r->product_name, ucfirst($r->product_type), $r->quantity, $r->line_total, $r->discount, $r->net_total]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'type' => ['nullable', 'in:' . implode(',', array_keys(self::TYPES))],
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date'],
            'cat'  => ['nullable', 'in:' . implode(',', array_keys(self::CATEGORIES))],
        ]);
        $cat = $validated['cat'] ?? 'all';

        $type = $validated['type'] ?? 'detailed';
        $from = isset($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : now()->startOfMonth();
        $to   = isset($validated['to'])   ? Carbon::parse($validated['to'])->endOfDay()     : now()->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$type, $from, $to, $cat];
    }

    /** Lahat ng nabentang item sa petsang pinili, kasama ang product (kahit na-delete na). */
    private function items(Carbon $from, Carbon $to, string $cat = 'all')
    {
        $q = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$from, $to]);

        return match ($cat) {
            'branded', 'generic', 'grocery' => $q->whereRaw("COALESCE(products.type, 'generic') = ?", [$cat]),
            default => $q,
        };
    }

    private function query(string $type, Carbon $from, Carbon $to, string $cat = 'all')
    {
        $base = $this->items($from, $to, $cat);

        if ($type === 'by_product') {
            return $base
                ->select(
                    'sale_items.product_name',
                    DB::raw("MAX(COALESCE(products.type, 'generic')) as product_type"),
                    DB::raw('SUM(sale_items.quantity) as quantity'),
                    DB::raw('SUM(sale_items.line_total) as line_total'),
                    DB::raw('SUM(sale_items.discount) as discount'),
                    DB::raw('SUM(sale_items.line_total - sale_items.discount) as net_total')
                )
                ->groupBy('sale_items.product_name')
                ->orderByDesc('net_total');
        }

        return $base
            ->select(
                'sale_items.product_name', 'sale_items.quantity', 'sale_items.line_total', 'sale_items.discount',
                DB::raw('(sale_items.line_total - sale_items.discount) as net_total'),
                'sales.id as sale_id', 'sales.invoice_no', 'sales.created_at as sold_at',
                'products.subtitle', 'products.unit',
                DB::raw("COALESCE(products.type, 'generic') as product_type")
            )
            ->orderByDesc('sales.created_at')->orderByDesc('sale_items.id');
    }

    /** Parang sa logbook: kabuuang benta (net) ng Branded, Generic at Grocery. */
    private function breakdown(Carbon $from, Carbon $to): array
    {
        $sums = $this->items($from, $to)
            ->selectRaw("COALESCE(products.type, 'generic') as t, SUM(sale_items.line_total - sale_items.discount) as net")
            ->groupBy('t')->pluck('net', 't');

        return collect(['branded', 'generic', 'grocery'])
            ->mapWithKeys(fn ($k) => [$k => round((float) ($sums[$k] ?? 0), 2)])->all();
    }

    private function filteredRevenue(Carbon $from, Carbon $to, string $cat): float
    {
        return round((float) $this->items($from, $to, $cat)->sum(DB::raw('sale_items.line_total - sale_items.discount')), 2);
    }

    private function revenue(Carbon $from, Carbon $to): float
    {
        return (float) Sale::completed()->whereBetween('created_at', [$from, $to])->sum('total');
    }
}
