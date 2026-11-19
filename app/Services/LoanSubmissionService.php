<?php

namespace App\Services;

use App\Models\Application;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

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

            // Mirror into the admin applications table — idempotent on
            // loan_application_id so a second write for the same loan can never
            // create a duplicate mirror row.
            $this->writeMirror($application, $evaluation);

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

    /**
     * Idempotent writer for the admin-side mirror row.
     *
     * loan_applications is the source of truth; applications is the admin-side
     * workflow record, 1:1 with a loan. Writes are keyed on loan_application_id
     * so a second write for the same loan reuses the existing mirror instead of
     * creating a duplicate.
     *
     * Handles the race where two concurrent writes both race past firstOrCreate:
     * the losing writer's QueryException is caught and the already-created row
     * is returned instead.
     *
     * If an existing mirror found (incl. soft-deleted via withTrashed) is itself
     * soft-deleted, it is restored — but only when the parent LoanApplication is
     * not trashed; otherwise we leave the mirror deleted alongside its parent.
     */
    public function writeMirror(LoanApplication $loan, array $evaluation): Application
    {
        $riskFactors = is_array($evaluation['details'] ?? null)
            ? json_encode($evaluation['details'])
            : null;

        $attrs = [
            'app_id'                => $this->nextAppId(),
            'applicant'             => $loan->full_name,
            'loan_type'             => $loan->loan_type,
            'amount'                => $loan->amount,
            'term_months'           => $loan->term_months,
            'status'                => 'Pending',
            'recommended_amount'    => $evaluation['recommended_amount'],
            'risk_level'            => $evaluation['risk_level'],
            'risk_score'            => $evaluation['risk_score'],
            'risk_factors'          => $riskFactors,
            'evaluation_reasons'    => json_encode($evaluation['evaluation_reasons'] ?? $evaluation['reasons']),
            'ai_score'              => null,
        ];

        try {
            $mirror = Application::withTrashed()->firstOrCreate(
                ['loan_application_id' => $loan->id],
                $attrs
            );
        } catch (QueryException $e) {
            // Race: two concurrent submissions both created a row. Re-look up the
            // competing writer's committed row under lockForUpdate so this
            // transaction sees it. If the lookup still finds nothing, the
            // exception was NOT a loan_application_id duplicate (e.g. an app_id
            // collision or another DB error) — rethrow it instead of swallowing
            // a genuinely different failure.
            $mirror = Application::withTrashed()
                ->lockForUpdate()
                ->where('loan_application_id', $loan->id)
                ->orderByDesc('id')
                ->first();

            if (! $mirror) {
                throw $e;
            }
        }

        if (!$mirror->wasRecentlyCreated && $mirror->trashed()) {
            // The found row was a soft-deleted mirror. Restore it only when the
            // parent loan is still active.
            if (! $loan->trashed()) {
                $mirror->restore();
            }
        }

        return $mirror;
    }

    /**
     * Next unique app_id using the max('id')+1 convention (incl. soft-deleted),
     * matching LoanApplication::makeReference().
     */
    private function nextAppId(): string
    {
        $lastId = Application::withTrashed()->max('id') ?? 0;

        return 'APP-' . str_pad((string) ($lastId + 1), 4, '0', STR_PAD_LEFT);
    }
}
