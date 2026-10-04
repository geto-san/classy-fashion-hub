<?php

namespace ClassyFashion\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\User\Models\Admin;

/**
 * Audit log viewer (report 10.2): who did what, when. Owner-only.
 */
class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(bouncer()->hasPermission('reporting.audit'), 401);

        $filters = $request->validate([
            'event' => ['nullable', 'string', 'max:60'],
            'user'  => ['nullable', 'integer'],
            'start' => ['nullable', 'date'],
            'end'   => ['nullable', 'date', 'after_or_equal:start'],
        ]);

        $query = Activity::query()
            ->where('log_name', 'classy-fashion')
            ->with('causer')
            ->latest('id');

        if (! empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        if (! empty($filters['user'])) {
            $query->where('causer_type', Admin::class)->where('causer_id', $filters['user']);
        }

        if (! empty($filters['start'])) {
            $query->whereDate('created_at', '>=', $filters['start']);
        }

        if (! empty($filters['end'])) {
            $query->whereDate('created_at', '<=', $filters['end']);
        }

        if ($request->query('export') === 'csv') {
            return $this->csv($query);
        }

        return view('classy-fashion::admin.reports.audit', [
            'entries' => $query->paginate(50)->withQueryString(),
            'events'  => Activity::where('log_name', 'classy-fashion')->whereNotNull('event')->distinct()->orderBy('event')->pluck('event'),
            'users'   => Admin::orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    protected function csv($query): StreamedResponse
    {
        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['When', 'Who', 'Event', 'What']);

            foreach ($query->limit(5000)->get() as $entry) {
                fputcsv($out, [
                    $entry->created_at->format('Y-m-d H:i:s'),
                    $entry->causer?->name ?? ($entry->getExtraProperty('actor') ?? 'system'),
                    $entry->event,
                    $entry->description,
                ]);
            }

            fclose($out);
        }, 'audit-log-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }
}
