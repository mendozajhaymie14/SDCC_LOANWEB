<?php

namespace App\Console\Commands;

use App\Models\CoopMember;
use App\Support\LoanStatus;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class BackfillMonthlyLimitCommand extends Command
{
    protected $name = 'backfill-monthly-limit';
    protected $description = 'Backfill approved_monthly_credit_limit and monthly_credit_limit_source on coop_members';

    protected function getOptions(): array
    {
        return [
            ['force', null, InputOption::VALUE_NONE, 'Apply live updates to the database'],
            ['dry-run', null, InputOption::VALUE_NONE, 'Show what would change without writing'],
        ];
    }

    public function handle()
    {
        $force = $this->option('force');
        $dryRun = !$force;

        $db = config('database.default');
        $this->info("Running on database: {$db}");
        $this->info("Mode: " . ($force ? 'Live update' : 'Dry run'));

        $members = CoopMember::latest()->get();

        $updated = 0;
        $listed = 0;

        foreach ($members as $member) {
            $oldLimit = $member->getOriginal('approved_monthly_credit_limit');
            $suggested = $member->suggestedMonthlyLimit(); // null if income null

            // Compute new values
            $newLimit = $suggested;
            $newSource = 'auto'; // always auto when recomputing

            // Check if anything actually changed
            $changed = $oldLimit !== $newLimit || $member->getOriginal('monthly_credit_limit_source') !== $newSource;

            if ($changed || $dryRun) {
                $listOld = $oldLimit !== null ? number_format((float) $oldLimit, 2) : 'null';
                $listNew = $newLimit !== null ? number_format((float) $newLimit, 2) : 'null';

                if ($dryRun) {
                    $this->info("Member {$member->member_id}: {$listOld} → {$listNew} (source: {$newSource})");
                } else {
                    if ($force) {
                        $member->approved_monthly_credit_limit = $newLimit;
                        $member->monthly_credit_limit_source = $newSource;
                        $member->save();
                        $updated++;
                    } else {
                        $this->info("Member {$member->member_id}: {$listOld} → {$listNew} (source: {$newSource})");
                    }
                }
                $listed++;
            }
        }

        if ($dryRun) {
            $this->info("Dry run complete: {$listed} members would be affected.");
            $this->warn("Run with --force to apply changes to the database.");
        } else {
            $this->info("Backfill complete: {$updated} members updated, {$listed} total checked.");
        }

        return 0;
    }
}