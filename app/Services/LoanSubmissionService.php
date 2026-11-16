<?php

namespace App\Services;

use App\Models\Application;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use Illuminate\Support\Facades\DB;

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
        return DB::transaction(function () use ($data) {
            // Last-line guard: one loan request at a time per member. The
            // controllers check first; this closes the race for two
            // concurrent submissions by locking the member row.
            $member = $this->lockMemberRow($data);
            if ($member) {
                $pending = $member->pendingLoanApplication();
                if ($pending) {
                    $ref = Application::where('loan_application_id', $pending->id)->value('app_id')
                        ?? $pending->reference;
                    throw new \RuntimeException(
                        'This member already has an application under review (' . $ref . '). It must be approved or rejected first.'
                    );
                }
            }

            $application = LoanApplication::create($data);

            $evaluation = app(LoanEvaluationService::class)->evaluate($application);

            $riskFactors = is_array($evaluation['details'] ?? null)
                ? json_encode($evaluation['details'])
                : null;

            $application->update([
                'recommended_amount'  => $evaluation['recommended_amount'],
                'risk_level'          => $evaluation['risk_level'],
                'risk_score'          => $evaluation['risk_score'],
                'risk_factors'        => $riskFactors,
                'evaluation_reasons'  => json_encode($evaluation['evaluation_reasons'] ?? $evaluation['reasons']),
            ]);

            // Mirror into the admin applications table. app_id must be unique, so
            // take max('id') including soft-deleted rows — same convention as
            // LoanApplication::makeReference().
            $lastId = Application::withTrashed()->max('id') ?? 0;
            $appId  = 'APP-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);

            Application::create([
                'loan_application_id'   => $application->id,
                'app_id'                => $appId,
                'applicant'             => $data['full_name'],
                'loan_type'             => $application->loan_type,
                'amount'                => $application->amount,
                'term_months'           => $application->term_months,
                'status'                => 'Pending',
                'recommended_amount'    => $evaluation['recommended_amount'],
                'risk_level'            => $evaluation['risk_level'],
                'risk_score'            => $evaluation['risk_score'],
                'risk_factors'          => $riskFactors,
                'evaluation_reasons'    => json_encode($evaluation['evaluation_reasons'] ?? $evaluation['reasons']),
                'ai_score'              => null,
            ]);

            return $application;
        });
    }

    /**
     * Resolve the CoopMember a submission belongs to, locking the row so two
     * concurrent submissions for the same member serialise on this guard.
     * Same scoping as monthlyUsed(): user_id with email fallback for legacy
     * staff-encoded loans, email only for portal-less members. Returns null
     * when the submission carries neither a user_id nor an email.
     */
    private function lockMemberRow(array $data): ?CoopMember
    {
        if (!empty($data['user_id'])) {
            $member = CoopMember::where('user_id', $data['user_id'])->lockForUpdate()->first();
            if ($member) {
                return $member;
            }
        }
        if (!empty($data['email'])) {
            return CoopMember::whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($data['email']))])
                ->lockForUpdate()->first();
        }
        return null;
    }
}
