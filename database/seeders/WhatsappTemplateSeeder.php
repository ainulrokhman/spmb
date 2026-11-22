<?php

namespace Database\Seeders;

use App\Models\WhatsappMessageTemplate;
use Illuminate\Database\Seeder;

/**
 * Stores the built-in WhatsApp message templates
 * (WhatsApp Tahap 2) so admins can edit them. Idempotent:
 * existing rows — including admin edits — are never
 * overwritten; only missing types are created.
 */
class WhatsappTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (WhatsappMessageTemplate::DEFAULTS as $type => $content) {
            WhatsappMessageTemplate::firstOrCreate(
                ['type' => $type],
                ['content' => $content, 'is_active' => true],
            );
        }
    }
}
