<?php

namespace App\Http\Controllers;

use App\Services\Logbook;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Daily Logbook: Branded | Generic | Grocery — benta at stock bawat araw. */
class LogbookController extends Controller
{
    public function __construct(private Logbook $logbook) {}

    public function index(Request $request)
    {
        $day = $this->day($request);
        $this->logbook->saveMissingDays();

        return view('logbook.index', ['d' => $this->logbook->day($day)]);
    }

    public function export(Request $request): StreamedResponse
    {
        $d = $this->logbook->day($this->day($request));

        return response()->streamDownload(function () use ($d) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['De Guzman Drugmart - Daily Logbook', $d['day']->format('F j, Y')]);
            fputcsv($out, []);
            fputcsv($out, ['SALES']);
            fputcsv($out, ['Type', 'Time', 'Invoice', 'Item', 'Qty', 'Amount']);
            foreach ($d['columns'] as $c) {
                foreach ($c['entries'] as $e) {
                    fputcsv($out, [$c['label'], Carbon::parse($e->sold_at)->format('h:i A'), $e->invoice_no, $e->product_name, $e->qty, $e->net]);
                }
            }
            fputcsv($out, []);
            foreach ($d['columns'] as $c) {
                fputcsv($out, [$c['label'], $c['total']]);
            }
            fputcsv($out, ['TOTAL', $d['total']]);
            fputcsv($out, []);
            fputcsv($out, ['STOCK']);
            fputcsv($out, ['Type', 'Product', 'Price', 'Expiration', 'Last stock (start of day)', 'Sold', 'Added', 'Removed', 'Stock left (end of day)']);
            foreach ($d['rows'] as $r) {
                fputcsv($out, [ucfirst($r->type), $r->name, $r->price, $r->expiration_date?->format('Y-m-d') ?? 'No expiry', $r->opening, $r->sold, $r->added, $r->removed, $r->closing]);
            }
            fclose($out);
        }, 'dg-drugmart-logbook-' . $d['day']->toDateString() . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function day(Request $request): Carbon
    {
        $v = $request->validate(['day' => ['nullable', 'date']]);
        $day = isset($v['day']) ? Carbon::parse($v['day']) : today();

        return $day->isFuture() ? today() : $day->startOfDay();
    }
}
