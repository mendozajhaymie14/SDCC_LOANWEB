<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\PaymentReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendPaymentReminders extends Command
{
    protected $signature = 'reminders:send-due';
    protected $description = 'Send upcoming and overdue payment reminders to members';

    public function handle(PaymentReminderService $service): int
    {
        $this->info('Starting payment reminder job...');

        try {
            AuditLog::log('Payment Reminder Job Started',
                "Job initiated at " . now()->toDateTimeString()
                . ". Processing loans for due soon, due today, and overdue reminders.",
                null
            );

            $result = $service->run();

            $this->newLine();
            $this->info("Reminder job completed:");
            $this->line("  Due soon (3 days):    {$result['details']['due_soon']}");
            $this->line("  Due today:            {$result['details']['due_today']}");
            $this->line("  Overdue:              {$result['details']['overdue']}");
            $this->line("  Total sent:           {$result['sent']}");
            $this->line("  Skipped (duplicates): {$result['skipped']}");
            $this->line("  Errors:               {$result['errors']}");

            AuditLog::log('Payment Reminder Job Completed',
                "Job finished successfully at " . now()->toDateTimeString()
                . ". Sent {$result['sent']} reminders "
                . "({$result['details']['due_soon']} due soon, {$result['details']['due_today']} due today, {$result['details']['overdue']} overdue). "
                . "Skipped {$result['skipped']} duplicates. Errors: {$result['errors']}.",
                null
            );

            return self::SUCCESS;

        } catch (\Throwable $e) {
            Log::error('Payment reminder job failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            AuditLog::log('Payment Reminder Job Failed',
                "Job failed with error: {$e->getMessage()} at " . now()->toDateTimeString(),
                null
            );
            $this->error('Job failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}