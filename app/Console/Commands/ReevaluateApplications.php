<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Services\LoanEvaluationService;
use Illuminate\Console\Command;

class ReevaluateApplications extends Command
{
    protected $signature = 'applications:reevaluate';
    protected $description = 'Re-evaluate all existing applications using LoanEvaluationService';

    public function handle(LoanEvaluationService $evaluator): int
    {
        $applications = Application::with('loanApplication')->get();
        $count = $applications->count();

        $this->info("Re-evaluating {$count} applications...");

        $updated = 0;
        foreach ($applications as $app) {
            $loanApp = $app->loanApplication;
            if (!$loanApp) {
                $this->warn("Skipping {$app->app_id}: no linked loan application.");
                continue;
            }

            $eval = $evaluator->evaluate($loanApp);

            $app->update([
                'recommended_amount' => $eval['recommended_amount'],
                'risk_level'         => $eval['risk_level'],
                'risk_score'         => $eval['risk_score'],
                'risk_factors'       => is_array($eval['reasons'] ?? null) ? json_encode($eval['reasons']) : null,
            ]);

            $updated++;
            $this->line("  {$app->app_id} → risk_level={$eval['risk_level']} risk_score={$eval['risk_score']} recommended=₱" . number_format($eval['recommended_amount'], 2));
        }

        $this->info("Re-evaluation complete: {$updated}/{$count} updated.");
        return self::SUCCESS;
    }
}
