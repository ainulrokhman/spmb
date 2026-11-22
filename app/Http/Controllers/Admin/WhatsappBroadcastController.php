<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\SchoolUnit;
use App\Models\WhatsappBroadcast;
use App\Models\WhatsappMessageTemplate;
use App\Services\Whatsapp\WhatsappBroadcastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin WhatsApp broadcasts: compose one message, target it by
 * unit / wave / application status / payment status (or a
 * manual pick from the filtered list), then send it through a
 * paced queue chain that can be paused, resumed, or cancelled.
 */
class WhatsappBroadcastController extends Controller
{
    public function __construct(private WhatsappBroadcastService $broadcasts) {}

    public function index(Request $request): View
    {
        $unitIds = $this->accessibleUnitIds($request);

        $broadcasts = WhatsappBroadcast::query()
            ->with(['schoolUnit', 'admissionWave', 'creator'])
            ->when($unitIds !== null, fn ($q) => $q->whereIn('school_unit_id', $unitIds))
            ->latest()
            ->paginate(15);

        return view('admin.whatsapp.broadcasts', compact('broadcasts'));
    }

    public function create(Request $request): View
    {
        $unitIds = $this->accessibleUnitIds($request);

        $units = SchoolUnit::query()
            ->when($unitIds !== null, fn ($q) => $q->whereIn('id', $unitIds))
            ->orderBy('name')
            ->get();
        $waves = AdmissionWave::query()
            ->when($unitIds !== null, fn ($q) => $q->whereIn('school_unit_id', $unitIds))
            ->orderBy('name')
            ->get(['id', 'school_unit_id', 'name']);
        $statuses = Application::STATUSES;
        $paymentFilters = WhatsappBroadcastService::PAYMENT_FILTERS;
        $variables = WhatsappBroadcastService::VARIABLES;

        $templates = [];
        foreach (WhatsappMessageTemplate::TYPES as $type => $label) {
            $template = WhatsappMessageTemplate::activeFor($type);
            if ($template !== null) {
                $templates[$type] = ['label' => $label, 'content' => $template->content];
            }
        }

        return view('admin.whatsapp.broadcast-create', compact('units', 'waves', 'statuses', 'paymentFilters', 'variables', 'templates'));
    }

    /** JSON preview of who would receive the chosen filters. */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_unit_id' => ['required', 'integer', 'exists:school_units,id'],
            'admission_wave_id' => ['nullable', 'integer', 'exists:admission_waves,id'],
            'application_status' => ['nullable', Rule::in(array_keys(Application::STATUSES))],
            'payment_filter' => ['nullable', Rule::in(array_keys(WhatsappBroadcastService::PAYMENT_FILTERS))],
        ]);

        abort_unless($request->user()->hasUnitAccess((int) $data['school_unit_id']), 403);

        $applications = $this->broadcasts->resolveApplications($data, $this->accessibleUnitIds($request));

        return response()->json([
            'count' => $applications->count(),
            'recipients' => $applications->take(300)->map(fn (Application $application) => [
                'id' => $application->id,
                'child_name' => $application->child_name,
                'guardian_name' => $application->user?->name,
                'phone' => $application->user?->phone,
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'school_unit_id' => ['required', 'integer', 'exists:school_units,id'],
            'admission_wave_id' => ['nullable', 'integer', 'exists:admission_waves,id'],
            'application_status' => ['nullable', Rule::in(array_keys(Application::STATUSES))],
            'payment_filter' => ['nullable', Rule::in(array_keys(WhatsappBroadcastService::PAYMENT_FILTERS))],
            'message' => ['required', 'string', 'max:2000'],
            'delay_min' => ['required', 'integer', 'min:0', 'max:120'],
            'delay_max' => ['required', 'integer', 'min:0', 'max:300'],
            'application_ids' => ['nullable', 'array'],
            'application_ids.*' => ['integer'],
            'confirm' => ['required', 'accepted'],
        ], [
            'confirm.accepted' => 'Centang konfirmasi jumlah penerima sebelum membuat broadcast.',
        ]);

        abort_unless($request->user()->hasUnitAccess((int) $data['school_unit_id']), 403);

        if (! empty($data['admission_wave_id'])) {
            $wave = AdmissionWave::find($data['admission_wave_id']);
            if ($wave !== null && (int) $wave->school_unit_id !== (int) $data['school_unit_id']) {
                return back()->withInput()->with('error', 'Gelombang yang dipilih bukan milik unit tersebut.');
            }
        }

        $broadcast = $this->broadcasts->createBroadcast($data, $request->user(), $this->accessibleUnitIds($request));

        if ($broadcast->total_recipients === 0) {
            return redirect()->route('admin.whatsapp.broadcasts.show', $broadcast)
                ->with('error', 'Broadcast dibuat, tetapi tidak ada penerima yang cocok dengan filter (atau nomor WhatsApp wali kosong).');
        }

        return redirect()->route('admin.whatsapp.broadcasts.show', $broadcast)
            ->with('success', "Broadcast dibuat dengan {$broadcast->total_recipients} penerima. Tekan Mulai Kirim untuk memulai pengiriman.");
    }

    public function show(Request $request, WhatsappBroadcast $broadcast): View
    {
        abort_unless($request->user()->hasUnitAccess($broadcast->school_unit_id), 403);

        $broadcast->load(['schoolUnit', 'admissionWave', 'creator']);
        $recipients = $broadcast->recipients()->orderBy('id')->paginate(25);

        return view('admin.whatsapp.broadcast-show', compact('broadcast', 'recipients'));
    }

    /** Live tallies for the progress polling on the detail page. */
    public function status(Request $request, WhatsappBroadcast $broadcast): JsonResponse
    {
        abort_unless($request->user()->hasUnitAccess($broadcast->school_unit_id), 403);

        return response()->json([
            'status' => $broadcast->status,
            'status_label' => $broadcast->statusLabel(),
            'total' => $broadcast->total_recipients,
            'sent' => $broadcast->sent_count,
            'failed' => $broadcast->failed_count,
            'percent' => $broadcast->progressPercent(),
        ]);
    }

    public function start(Request $request, WhatsappBroadcast $broadcast): RedirectResponse
    {
        abort_unless($request->user()->hasUnitAccess($broadcast->school_unit_id), 403);

        $this->broadcasts->start($broadcast);

        return back()->with('success', 'Pengiriman broadcast dimulai. Pastikan queue worker berjalan di server.');
    }

    public function pause(Request $request, WhatsappBroadcast $broadcast): RedirectResponse
    {
        abort_unless($request->user()->hasUnitAccess($broadcast->school_unit_id), 403);

        $this->broadcasts->pause($broadcast);

        return back()->with('success', 'Broadcast dijeda. Penerima yang belum terkirim menunggu sampai dilanjutkan.');
    }

    public function cancel(Request $request, WhatsappBroadcast $broadcast): RedirectResponse
    {
        abort_unless($request->user()->hasUnitAccess($broadcast->school_unit_id), 403);

        $this->broadcasts->cancel($broadcast);

        return back()->with('success', 'Broadcast dibatalkan. Pesan yang sudah terkirim tidak dapat ditarik.');
    }

    /**
     * Unit ids the current user may work with, or null for
     * global (foundation-level) access.
     *
     * @return list<int>|null
     */
    private function accessibleUnitIds(Request $request): ?array
    {
        $user = $request->user();

        return $user->hasGlobalUnitAccess() ? null : $user->accessibleUnitIds();
    }
}
