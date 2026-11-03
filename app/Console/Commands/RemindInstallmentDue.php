<?php

namespace App\Console\Commands;

use App\Contracts\GuardianNotificationServiceInterface;
use App\Models\InvoiceInstallment;
use Illuminate\Console\Command;

/**
 * Daily installment due-date reminders for guardians: a
 * term falling due in 3 days and a term falling due today
 * each produce one notification per guardian (the service
 * deduplicates, so re-running the command is safe).
 */
class RemindInstallmentDue extends Command
{
    protected $signature = 'notifications:remind-installments';

    protected $description = 'Remind guardians about installment terms due in 3 days or today';

    public function handle(GuardianNotificationServiceInterface $notifications): int
    {
        $sent = 0;

        foreach ([3, 0] as $daysBefore) {
            $due = InvoiceInstallment::query()
                ->where('status', '!=', 'paid')
                ->whereDate('due_date', now()->addDays($daysBefore)->toDateString())
                ->with('invoice.application')
                ->get();

            foreach ($due as $installment) {
                $sent += $notifications->notifyInstallmentDue($installment, $daysBefore);
            }
        }

        $this->info("Mengirim {$sent} pengingat jatuh tempo termin.");

        return self::SUCCESS;
    }
}
