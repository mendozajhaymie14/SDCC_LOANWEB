<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\CoopMember;
use Illuminate\Console\Command;

class FixApplicantNames extends Command
{
    protected $signature = 'sdcc:fix-applicant-names {--apply}';
    protected $description = 'Fix applicant names in admin applications that show "Applicant" instead of the member\'s real name';

    public function handle(): int
    {
        $applications = Application::where('applicant', 'Applicant')
            ->with(['loanApplication' => function ($q) {
                $q->with('user');
            }])
            ->get();

        $count = $applications->count();
        $this->info("Found {$count} applications with applicant='Applicant'");

        $updated = 0;
        foreach ($applications as $app) {
            $newApplicant = null;

            // Try linked loan application's full_name first
            if ($app->loanApplication && !empty($app->loanApplication->full_name)) {
                $newApplicant = $app->loanApplication->full_name;
            }
            // Try linked user's coop member full_name
            else if ($app->loanApplication && $app->loanApplication->user) {
                $member = $app->loanApplication->user->coopMember;
                if ($member && !empty($member->full_name)) {
                    $newApplicant = $member->full_name;
                }
            }

            if ($newApplicant === null) {
                $this->warn("Skipping {$app->app_id}: no source found for applicant name.");
                continue;
            }

            if ($this->option('apply')) {
                $app->applicant = $newApplicant;
                $app->save();
                $updated++;
                $this->line("  {$app->app_id} → applicant={$newApplicant}");
            } else {
                $this->line("  {$app->app_id} → would set applicant={$newApplicant}");
            }
        }

        if ($this->option('apply')) {
            $this->info("Fix complete: {$updated}/{$count} applications updated.");
        } else {
            $this->info("Run with --apply to update the database.");
        }

        return self::SUCCESS;
    }
}