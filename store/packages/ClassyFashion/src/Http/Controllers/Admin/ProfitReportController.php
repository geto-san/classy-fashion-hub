<?php

namespace ClassyFashion\Http\Controllers\Admin;

use ClassyFashion\Models\Sales\Order as ClassyOrder;
use ClassyFashion\Support\Profit;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;

/**
 * Profit summary for a selected period (report 9.4).
 *
 * Totals: sales, transactions, profit. Canceled orders excluded.
 * Costs come from current product costs (illustrative seed data).
 */
class ProfitReportController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(bouncer()->hasPermission('reporting.profit'), 401);

        $validated = $request->validate([
            'start' => ['nullable', 'date'],
            'end'   => ['nullable', 'date', 'after_or_equal:start'],
        ]);

        $start = $validated['start'] ?? now()->subDays(30)->toDateString();
        $end = $validated['end'] ?? now()->toDateString();

        $orders = ClassyOrder::query()
            ->with(['items.product'])
            ->whereDate('created_at', '>=', $start)
            ->whereDate('created_at', '<=', $end)
            ->where('status', '!=', ClassyOrder::STATUS_CANCELED)
            ->orderBy('created_at')
            ->get();

        $rows = $orders
            ->groupBy(fn ($order) => $order->created_at->toDateString())
            ->map(fn ($day, $date) => [
                'date'         => $date,
                'transactions' => $day->count(),
                'sales'        => $day->sum('base_grand_total'),
                'profit'       => $day->sum(fn ($order) => Profit::orderProfit($order)),
            ])
            ->values();

        $totals = [
            'transactions' => $orders->count(),
            'sales'        => $orders->sum('base_grand_total'),
            'profit'       => $orders->sum(fn ($order) => Profit::orderProfit($order)),
        ];

        if ($request->query('export') === 'csv') {
            return $this->csv($rows, $totals, $start, $end);
        }

        return view('classy-fashion::admin.reports.profit', [
            'rows'   => $rows,
            'totals' => $totals,
            'start'  => $start,
            'end'    => $end,
        ]);
    }

    protected function csv($rows, array $totals, string $start, string $end): StreamedResponse
    {
        $filename = "profit-report-{$start}_{$end}.csv";

        return response()->streamDownload(function () use ($rows, $totals) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Date', 'Transactions', 'Sales (UGX)', 'Profit (UGX)']);

            foreach ($rows as $row) {
                fputcsv($out, [$row['date'], $row['transactions'], $row['sales'], round($row['profit'], 2)]);
            }

            fputcsv($out, ['Total', $totals['transactions'], $totals['sales'], round($totals['profit'], 2)]);

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
