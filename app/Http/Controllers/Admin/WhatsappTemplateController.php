<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsappMessageLog;
use App\Models\WhatsappMessageTemplate;
use App\Services\Whatsapp\WhatsappSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * WhatsApp message templates (WhatsApp Tahap 2): one editable
 * text per notification type. Deactivating a type suppresses
 * its messages entirely; the sender falls back to the
 * built-in default while no row is stored.
 */
class WhatsappTemplateController extends Controller
{
    public function __construct(
        private readonly WhatsappSender $sender,
    ) {}

    /**
     * Every templated type with its stored (or default) text
     * and active state.
     */
    public function index(): View
    {
        $templates = collect(array_keys(WhatsappMessageTemplate::TYPES))
            ->map(fn (string $type) => WhatsappMessageTemplate::forType($type));

        return view('admin.whatsapp.templates', compact('templates'));
    }

    public function edit(string $type): View
    {
        $template = $this->templateOrFail($type);
        $variables = WhatsappMessageTemplate::variablesFor($type);

        return view('admin.whatsapp.template-edit', compact('template', 'variables'));
    }

    public function update(Request $request, string $type): RedirectResponse
    {
        $this->templateOrFail($type);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        WhatsappMessageTemplate::updateOrCreate(
            ['type' => $type],
            ['content' => $data['content'], 'is_active' => $request->boolean('is_active')],
        );

        return redirect()->route('admin.whatsapp.templates.index')
            ->with('success', 'Template '.WhatsappMessageTemplate::TYPES[$type].' berhasil disimpan.');
    }

    /**
     * Restore the built-in default text for a type (the active
     * state is left as the admin set it).
     */
    public function restore(string $type): RedirectResponse
    {
        $this->templateOrFail($type);

        WhatsappMessageTemplate::updateOrCreate(
            ['type' => $type],
            ['content' => WhatsappMessageTemplate::defaultFor($type)],
        );

        return back()->with('success', 'Template '.WhatsappMessageTemplate::TYPES[$type].' dikembalikan ke teks bawaan.');
    }

    /**
     * Send the type's message, rendered with sample data, to a
     * phone number through the foundation chain — so admins can
     * see exactly what guardians receive.
     */
    public function test(Request $request, string $type): RedirectResponse
    {
        $this->templateOrFail($type);

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        $log = $this->sender->sendTemplated(null, $data['phone'], $type, WhatsappMessageTemplate::SAMPLES);

        return back()->with(
            $log->status === WhatsappMessageLog::STATUS_SENT ? 'success' : 'error',
            $log->status === WhatsappMessageLog::STATUS_SENT
                ? 'Pesan uji terkirim ke '.$data['phone'].'.'
                : 'Pesan uji tidak terkirim: '.($log->error ?? 'periksa halaman WhatsApp.'),
        );
    }

    private function templateOrFail(string $type): WhatsappMessageTemplate
    {
        abort_unless(WhatsappMessageTemplate::isTemplatedType($type), 404);

        return WhatsappMessageTemplate::forType($type);
    }
}
