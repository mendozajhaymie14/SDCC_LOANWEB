<?php

namespace App\Services;

use App\Models\Application;
use App\Models\LoanApplication;

/**
 * Shared submission path for both member self-submission and staff-encoded
 * applications. A single LoanApplication row is created, evaluated through the
 * LoanEvaluationService engine, and mirrored into the admin applications table
 * — identical data flows, so a staff-typed application differs from a member's
 * only by encoded_by.
 */
class LoanSubmissionService
{
    /**
     * Submit (create + evaluate + mirror) a loan application.
     *
     * @param  array  $data  LoanApplication fillable fields; include
     *   'encoded_by' (staff userId) when typed in on a member's behalf.
     */
    public function submit(array $data): LoanApplication
    {
        $application = LoanApplication::create($data);

        $evaluation = app(LoanEvaluationService::class)->evaluate($application);

        $riskFactors = is_array($evaluation['details'] ?? null)
            ? json_encode($evaluation['details'])
            : null;

        $application->update([
            'recommended_amount' => $evaluation['recommended_amount'],
            'risk_level'         => $evaluation['risk_level'],
            'risk_score'         => $evaluation['risk_score'],
            'risk_factors'       => $riskFactors,
        ]);

        // Mirror into the admin applications table. app_id must be unique, so
        // take max('id') including soft-deleted rows — same convention as
        // LoanApplication::makeReference().
        $lastId = Application::withTrashed()->max('id') ?? 0;
        $appId  = 'APP-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);

        Application::create([
            'loan_application_id' => $application->id,
            'app_id'              => $appId,
            'applicant'           => $application->full_name,
            'loan_type'           => $application->loan_type,
            'amount'              => $application->amount,
            'status'              => 'Pending',
            'recommended_amount'  => $evaluation['recommended_amount'],
            'risk_level'          => $evaluation['risk_level'],
            'risk_score'          => $evaluation['risk_score'],
            'risk_factors'        => $riskFactors,
            'ai_score'            => null,
        ]);

        return $application;
    }
}
