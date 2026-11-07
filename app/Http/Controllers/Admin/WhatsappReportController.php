<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolUnit;
use App\Models\WhatsappDevice;
use App\Models\WhatsappMessageLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * WhatsApp report: every send attempt the sender service
 * logged, with filters (period, unit, device, type, status,
 * search), summary counters, and a CSV export. Restricted to
 * whatsapp-manage holders because rows contain message bodies
 * and guardian phone numbers.
 */
class WhatsappReportController extends Controller
{
    /**
     * Report page: filter form, summary cards, and the log
     * table (newest first).
     */
    public function index(Request $request): View
    {
        $query = $this->filteredQuery($request);

        $summary = [
            'total' => (clone $query)->count(),
            'sent' => (clone $query)->where('status', WhatsappMessageLog::STATUS_SENT)->count(),
            'failed' => (clone $query)->where('status', WhatsappMessageLog::STATUS_FAILED)->count(),
            'skipped' => (clone $query)->where('status', WhatsappMessageLog::STATUS_SKIPPED)->count(),
            'via_foundation' => (clone $query)
                ->where('status', WhatsappMessageLog::STATUS_SENT)
                ->whereNotNull('school_unit_id')
                ->whereHas('device', fn (Builder $device) => $device->whereNull('school_unit_id'))
                ->count(),
        ];

        $logs = $query
            ->with(['device', 'schoolUnit'])
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $units = SchoolUnit::query()->orderBy('name')->get();
        $devices = WhatsappDevice::query()->orderBy('id')->get();
        $types = WhatsappMessageLog::TYPES;
        $statuses = WhatsappMessageLog::STATUSES;
        $filters = $request->only(['date_from', 'date_to', 'school_unit_id', 'whatsapp_device_id', 'type', 'status', 'q']);

        return view('admin.whatsapp.report', compact('logs', 'summary', 'units', 'devices', 'types', 'statuses', 'filters'));
    }

    /**
     * CSV export of the log rows matching the filters.
     */
    public function export(Request $request): StreamedResponse
    {
        $logs = $this->filteredQuery($request)
            ->with(['device', 'schoolUnit'])
            ->oldest('id')
            ->get();

        return response()->streamDownload(function () use ($logs) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Waktu', 'Penerima', 'No HP', 'Unit', 'Device Pengirim', 'Provider', 'Jenis', 'Status', 'Isi Pesan', 'Keterangan']);

            foreach ($logs as $log) {
                fputcsv($out, [
                    $log->created_at?->format('Y-m-d H:i:s'),
                    $log->recipient_name,
                    $log->recipient_phone,
                    $log->schoolUnit?->name ?? 'Yayasan',
                    $log->device?->label(),
                    $log->device?->providerLabel(),
                    $log->typeLabel(),
                    $log->statusLabel(),
                    $log->message,
                    $log->error,
                ]);
            }

            fclose($out);
        }, 'laporan-whatsapp.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * The log query narrowed by the report filters. The unit
     * filter accepts 'yayasan' for foundation-context rows.
     */
    private function filteredQuery(Request $request): Builder
    {
        return WhatsappMessageLog::query()
            ->when($request->filled('date_from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->query('date_to')))
            ->when($request->query('school_unit_id') === 'yayasan', fn (Builder $query) => $query->whereNull('school_unit_id'))
            ->when($request->filled('school_unit_id') && $request->query('school_unit_id') !== 'yayasan', fn (Builder $query) => $query->where('school_unit_id', (int) $request->query('school_unit_id')))
            ->when($request->filled('whatsapp_device_id'), fn (Builder $query) => $query->where('whatsapp_device_id', (int) $request->query('whatsapp_device_id')))
            ->when($request->filled('type'), fn (Builder $query) => $query->where('type', $request->query('type')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->query('status')))
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $term = '%'.$request->query('q').'%';
                $query->where(fn (Builder $inner) => $inner
                    ->where('recipient_phone', 'like', $term)
                    ->orWhere('recipient_name', 'like', $term)
                    ->orWhere('message', 'like', $term));
            });
    }
}
