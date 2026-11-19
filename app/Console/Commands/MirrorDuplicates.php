<?php

namespace App\Console\Commands;

use App\Models\Application;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class MirrorDuplicates extends Command
{
    protected $signature = 'sdcc:mirror-duplicates';
    protected $description = 'List loans with more than one mirror row in applications table (read-only dry run)';

    public function handle(): int
    {
        // Safety: print DB_DATABASE first, abort if real 'sdcc'
        $db = Config::get('database.connections.' . Config::get('database.default') . '.database');
        $this->info("DB_DATABASE: {$db}");
        if ($db === 'sdcc') {
            $this->error('ABORT: This command refuses to run against the real sdcc database.');
            return self::FAILURE;
        }

        $duplicates = Application::withTrashed()
            ->selectRaw('loan_application_id, COUNT(*) as cnt, GROUP_CONCAT(id) as mirror_ids, GROUP_CONCAT(app_id) as app_ids, GROUP_CONCAT(created_at) as created_ats, GROUP_CONCAT(COALESCE(deleted_at, "NULL")) as deleted_ats, MAX(id) as newest_id')
            ->whereNotNull('loan_application_id')
            ->groupBy('loan_application_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('No duplicate mirrors found.');
            return self::SUCCESS;
        }

        $this->warn("Found {$duplicates->count()} loan(s) with duplicate mirrors:\n");

        foreach ($duplicates as $dup) {
            $loanId = $dup->loan_application_id;
            $mirrorIds = explode(',', $dup->mirror_ids);
            $appIds = explode(',', $dup->app_ids);
            $createdAts = explode(',', $dup->created_ats);
            $deletedAts = explode(',', $dup->deleted_ats);
            $newestId = $dup->newest_id;

            $this->line("<info>Loan ID: {$loanId}</info>");
            $this->line('  Mirrors:');
            foreach ($mirrorIds as $i => $mid) {
                $marker = ((int) $mid === (int) $newestId) ? ' <-- newest' : '';
                $this->line("    id={$mid} app_id={$appIds[$i]} created_at={$createdAts[$i]} deleted_at={$deletedAts[$i]}{$marker}");
            }
            $this->line('');
        }

        $this->warn("Total duplicate groups: {$duplicates->count()}");
        return self::SUCCESS;
    }
}