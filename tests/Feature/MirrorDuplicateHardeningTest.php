<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\User;
use App\Services\LoanSubmissionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Hardening tests for the loan_applications → applications mirror.
 *
 * loan_applications is the source of truth; applications is the admin-side
 * workflow record, 1:1 with a loan. These tests prove that idempotent writes
 * plus the UNIQUE index make it impossible to create a second mirror for one
 * loan, and that the reconcile command only ever copies loan → mirror.
 */
class MirrorDuplicateHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function makeLoan(array $overrides = []): LoanApplication
    {
        $user = User::factory()->create([
            'usertype' => 'member',
            'email'    => 'borrower' . uniqid() . '@example.com',
        ]);
        CoopMember::factory()->create([
            'user_id'        => $user->id,
            'email'          => $user->email,
            'monthly_income' => 20000,
        ]);

        return LoanApplication::factory()->create(array_merge([
            'user_id'            => $user->id,
            'email'              => $user->email,
            'full_name'          => 'Idempotent Borrower',
            'loan_type'          => 'regular',
            'amount'             => 5000,
            'term_months'        => 12,
            'status'             => 'pending',
            'risk_level'         => 'Medium',
            'risk_score'         => 70,
            'recommended_amount' => 5000,
            'remaining_balance'  => 0,
        ], $overrides));
    }

    private function evaluation(LoanApplication $loan): array
    {
        return [
            'recommended_amount' => $loan->recommended_amount,
            'risk_level'         => $loan->risk_level,
            'risk_score'         => $loan->risk_score,
            'evaluation_reasons' => ['Looks fine'],
            'details'            => ['note' => 'test'],
        ];
    }

    /** writeMirror() called twice for one loan must leave exactly one mirror. */
    public function test_write_mirror_is_idempotent(): void
    {
        $loan = $this->makeLoan();
        $service = app(LoanSubmissionService::class);

        $first  = $service->writeMirror($loan, $this->evaluation($loan));
        $second = $service->writeMirror($loan, $this->evaluation($loan));

        $this->assertSame($first->id, $second->id, 'Second write must reuse the first mirror row.');
        $this->assertSame(
            1,
            Application::withTrashed()->where('loan_application_id', $loan->id)->count(),
            'Only one mirror row may exist for a loan.'
        );
    }

    /** A direct second insert for the same loan is rejected by the UNIQUE index. */
    public function test_unique_index_rejects_second_mirror(): void
    {
        $loan = $this->makeLoan();

        Application::create([
            'loan_application_id' => $loan->id,
            'app_id'              => 'APP-0001',
            'applicant'           => 'Idempotent Borrower',
            'loan_type'           => 'regular',
            'amount'              => 5000,
            'status'              => 'Pending',
        ]);

        $this->expectException(QueryException::class);

        Application::create([
            'loan_application_id' => $loan->id,
            'app_id'              => 'APP-0002',
            'applicant'           => 'Idempotent Borrower',
            'loan_type'           => 'regular',
            'amount'              => 5000,
            'status'              => 'Pending',
        ]);
    }

    /** A soft-deleted mirror is restored when the parent loan is still active. */
    public function test_write_mirror_restores_soft_deleted_mirror_when_loan_active(): void
    {
        $loan = $this->makeLoan();
        $service = app(LoanSubmissionService::class);

        $mirror = $service->writeMirror($loan, $this->evaluation($loan));
        $mirror->delete();
        $this->assertSoftDeleted('applications', ['id' => $mirror->id]);

        $service->writeMirror($loan, $this->evaluation($loan));

        $this->assertNotSoftDeleted('applications', ['id' => $mirror->id]);
        $this->assertSame(
            1,
            Application::withTrashed()->where('loan_application_id', $loan->id)->count()
        );
    }

    /** A soft-deleted mirror is left deleted when the parent loan is also trashed. */
    public function test_write_mirror_leaves_soft_deleted_mirror_when_loan_trashed(): void
    {
        $loan = $this->makeLoan();
        $service = app(LoanSubmissionService::class);

        $mirror = $service->writeMirror($loan, $this->evaluation($loan));
        $mirror->delete();
        $loan->delete();

        $service->writeMirror($loan, $this->evaluation($loan));

        $this->assertSoftDeleted('applications', ['id' => $mirror->id]);
    }

    /** Reconcile reports orphans and drift, and writes nothing without --apply. */
    public function test_reconcile_reports_drift_and_orphans_without_writing(): void
    {
        $loan = $this->makeLoan([
            'status'            => 'approved',
            'approved_amount'   => 4000,
            'remaining_balance' => 3000,
            'risk_level'        => 'Low',
        ]);

        $mirror = Application::create([
            'loan_application_id' => $loan->id,
            'app_id'              => 'APP-0100',
            'applicant'           => 'Idempotent Borrower',
            'loan_type'           => 'regular',
            'amount'              => 5000,
            'status'              => 'Pending',        // drift
            'approved_amount'     => 0,                // drift
            'remaining_balance'   => 0,                // drift
            'risk_level'          => 'High',           // drift
        ]);

        // Orphan: mirror pointing at a loan that does not exist. FK is disabled
        // in setUp() so this simulates a legacy orphan row.
        $orphan = Application::create([
            'loan_application_id' => 999999,
            'app_id'              => 'APP-0999',
            'applicant'           => 'Ghost',
            'loan_type'           => 'regular',
            'amount'              => 1000,
            'status'              => 'Pending',
        ]);

        $this->artisan('sdcc:reconcile-mirror')
            ->expectsOutputToContain('Orphaned mirrors')
            ->expectsOutputToContain('Drift between')
            ->expectsOutputToContain('risk_level')
            ->assertExitCode(0);

        // Nothing changes without --apply.
        $mirror->refresh();
        $this->assertSame('Pending', $mirror->status);
        $this->assertSame('High', $mirror->risk_level);
        $this->assertDatabaseHas('applications', ['id' => $orphan->id]);
    }

    /** --apply copies loan → mirror only and never touches the loan. */
    public function test_reconcile_apply_copies_in_right_direction_only(): void
    {
        $loan = $this->makeLoan([
            'status'            => 'approved',
            'approved_amount'   => 4000,
            'remaining_balance' => 3000,
            'risk_level'        => 'Low',
        ]);

        $mirror = Application::create([
            'loan_application_id' => $loan->id,
            'app_id'              => 'APP-0101',
            'applicant'           => 'Idempotent Borrower',
            'loan_type'           => 'regular',
            'amount'              => 5000,
            'status'              => 'Pending',
            'approved_amount'     => 0,
            'remaining_balance'   => 0,
            'risk_level'          => 'High',
        ]);

        $this->artisan('sdcc:reconcile-mirror', ['--apply' => true])->assertExitCode(0);

        $mirror->refresh();
        $this->assertSame('Approved', $mirror->status, 'Mirror status copied from loan.');
        $this->assertEquals(4000, (float) $mirror->approved_amount);
        $this->assertEquals(3000, (float) $mirror->remaining_balance);
        $this->assertSame('Low', $mirror->risk_level);

        // Direction check: the loan is untouched by the mirror's (wrong) values.
        $loan->refresh();
        $this->assertSame('approved', $loan->status);
        $this->assertEquals(4000, (float) $loan->approved_amount);
        $this->assertSame('Low', $loan->risk_level);
    }

    /**
     * A QueryException that is NOT a loan_application_id duplicate must be
     * rethrown, never swallowed as if it were a benign race. Here the next
     * generated app_id collides with an existing mirror on a different loan, so
     * the catch-block lookup (by loan_application_id) finds nothing.
     */
    public function test_non_duplicate_query_exception_is_rethrown(): void
    {
        // Existing mirror at id 5 occupies app_id APP-0006.
        $existingLoan = $this->makeLoan();
        Application::factory()->create([
            'id'                  => 5,
            'loan_application_id' => $existingLoan->id,
            'app_id'              => 'APP-0006',
            'applicant'           => 'Existing Borrower',
            'loan_type'           => 'regular',
            'amount'              => 5000,
            'status'              => 'Pending',
        ]);

        // Fresh loan with no mirror: nextAppId() = max(id)+1 = 6 -> APP-0006,
        // colliding on app_id while loan_application_id differs.
        $loan = $this->makeLoan();
        $service = app(LoanSubmissionService::class);

        try {
            $service->writeMirror($loan, $this->evaluation($loan));
            $this->fail('writeMirror swallowed a non-duplicate QueryException.');
        } catch (QueryException $e) {
            $this->assertStringContainsString(
                'app_id',
                $e->getMessage(),
                'Expected the app_id uniqueness violation to surface.'
            );
        }
    }
}