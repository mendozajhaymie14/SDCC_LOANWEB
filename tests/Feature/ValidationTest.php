<?php

namespace Tests\Feature;

use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Models\User;
use App\Rules\PhilippineMobileNumber;
use App\Services\LoanEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function philippine_mobile_number_rule_accepts_valid_format()
    {
        $rule = new PhilippineMobileNumber();
        $failCalled = false;

        $rule->validate('contact_number', '09171234567', function ($message) use (&$failCalled) {
            $failCalled = true;
        });

        $this->assertFalse($failCalled);
    }

    /** @test */
    public function philippine_mobile_number_rule_rejects_plus63_format()
    {
        $rule = new PhilippineMobileNumber();
        $failCalled = false;

        $rule->validate('contact_number', '+639171234567', function ($message) use (&$failCalled) {
            $failCalled = true;
        });

        $this->assertTrue($failCalled);
    }

    /** @test */
    public function philippine_mobile_number_rule_rejects_10_digits()
    {
        $rule = new PhilippineMobileNumber();
        $failCalled = false;

        $rule->validate('contact_number', '0917123456', function ($message) use (&$failCalled) {
            $failCalled = true;
        });

        $this->assertTrue($failCalled);
    }

    /** @test */
    public function philippine_mobile_number_rule_rejects_letters()
    {
        $rule = new PhilippineMobileNumber();
        $failCalled = false;

        $rule->validate('contact_number', '09171234abc', function ($message) use (&$failCalled) {
            $failCalled = true;
        });

        $this->assertTrue($failCalled);
    }

    /** @test */
    public function philippine_mobile_number_rule_rejects_invalid_prefix()
    {
        $rule = new PhilippineMobileNumber();
        $failCalled = false;

        $rule->validate('contact_number', '08171234567', function ($message) use (&$failCalled) {
            $failCalled = true;
        });

        $this->assertTrue($failCalled);
    }

    /** @test */
    public function philippine_mobile_number_rule_rejects_639_format()
    {
        $rule = new PhilippineMobileNumber();
        $failCalled = false;

        $rule->validate('contact_number', '639171234567', function ($message) use (&$failCalled) {
            $failCalled = true;
        });

        $this->assertTrue($failCalled);
    }

    /** @test */
    public function philippine_mobile_number_rule_normalizes_9_format()
    {
        $rule = new PhilippineMobileNumber();
        $failCalled = false;

        $rule->validate('contact_number', '9171234567', function ($message) use (&$failCalled) {
            $failCalled = true;
        });

        $this->assertFalse($failCalled);
    }

    /** @test */
    public function loan_evaluation_warns_when_amount_exceeds_70_percent_of_income()
    {
        $service = app(LoanEvaluationService::class);

        $user = User::factory()->asLoanOfficer()->create();
        CoopMember::factory()->create([
            'user_id' => $user->id,
            'savings_balance' => 10000,
        ]);
        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'amount' => 8000, // 80% of typical income exceeds 70%
            'monthly_income' => 10000,
            'loan_type' => 'personal',
            'term_months' => 12,
            'existing_monthly_obligations' => 0,
            'share_capital' => 50000,
            'status' => 'pending',
        ]);

        $result = $service->evaluate($loanApp);

        $this->assertTrue($result['eligible']);
        $this->assertNotEmpty($result['reasons']);

        // Check for 70% warning in reasons
        $hasWarning = false;
        foreach ($result['reasons'] as $reason) {
            if (stripos($reason, '70%') !== false || stripos($reason, '70 percent') !== false) {
                $hasWarning = true;
                break;
            }
        }
        $this->assertTrue($hasWarning, 'Expected 70% income threshold warning in reasons');
    }

    /** @test */
    public function loan_evaluation_no_warning_when_amount_below_70_percent()
    {
        $service = app(LoanEvaluationService::class);

        $user = User::factory()->asLoanOfficer()->create();
        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'amount' => 5000, // 50% of 10000 = below 70%
            'monthly_income' => 10000,
            'loan_type' => 'personal',
            'term_months' => 12,
            'existing_monthly_obligations' => 0,
            'share_capital' => 50000,
            'status' => 'pending',
        ]);

        $result = $service->evaluate($loanApp);

        $hasWarning = false;
        foreach ($result['reasons'] as $reason) {
            if (stripos($reason, '70%') !== false || stripos($reason, '70 percent') !== false) {
                $hasWarning = true;
                break;
            }
        }
        $this->assertFalse($hasWarning, 'Should not have 70% warning when amount is below threshold');
    }

    /** @test */
    public function store_repayment_blocks_duplicate_amount_and_date()
    {
        $user = User::factory()->asLoanOfficer()->create();
        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'status' => 'approved',
            'amount' => 100000,
            'remaining_balance' => 100000,
        ]);

        $application = \App\Models\Application::factory()->create([
            'loan_application_id' => $loanApp->id,
            'status' => 'Approved',
            'remaining_balance' => 100000,
        ]);

        // Create first payment
        Payment::create([
            'loan_application_id' => $loanApp->id,
            'user_id' => $user->id,
            'amount_paid' => 10000,
            'remaining_balance_after' => 90000,
            'payment_date' => '2024-01-15',
            'payment_method' => 'Cash',
            'encoded_by' => $user->id,
        ]);

        // Try to create duplicate payment with same loan, amount, and date
        $response = $this->actingAs($user, 'web')
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/admin/repayments', [
                'application_id' => $application->id,
                'amount' => 10000,
                'payment_date' => '2024-01-15',
                'payment_method' => 'Cash',
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'A payment with the same amount and date already exists for this loan.',
        ]);
    }

    /** @test */
    public function store_repayment_allows_different_amount_same_date()
    {
        $user = User::factory()->asLoanOfficer()->create();
        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'status' => 'approved',
            'amount' => 100000,
            'remaining_balance' => 100000,
        ]);

        $application = \App\Models\Application::factory()->create([
            'loan_application_id' => $loanApp->id,
            'status' => 'Approved',
            'remaining_balance' => 100000,
        ]);

        // Create first payment
        Payment::create([
            'loan_application_id' => $loanApp->id,
            'user_id' => $user->id,
            'amount_paid' => 10000,
            'remaining_balance_after' => 90000,
            'payment_date' => '2024-01-15',
            'payment_method' => 'Cash',
            'encoded_by' => $user->id,
        ]);

        // Try to create payment with different amount same date - should work
        $response = $this->actingAs($user, 'web')
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/admin/repayments', [
                'application_id' => $application->id,
                'amount' => 5000,
                'payment_date' => '2024-01-15',
                'payment_method' => 'Cash',
            ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function store_repayment_allows_same_amount_different_date()
    {
        $user = User::factory()->asLoanOfficer()->create();
        $loanApp = LoanApplication::factory()->create([
            'user_id' => $user->id,
            'status' => 'approved',
            'amount' => 100000,
            'remaining_balance' => 100000,
        ]);

        $application = \App\Models\Application::factory()->create([
            'loan_application_id' => $loanApp->id,
            'status' => 'Approved',
            'remaining_balance' => 100000,
        ]);

        // Create first payment
        Payment::create([
            'loan_application_id' => $loanApp->id,
            'user_id' => $user->id,
            'amount_paid' => 10000,
            'remaining_balance_after' => 90000,
            'payment_date' => '2024-01-15',
            'payment_method' => 'Cash',
            'encoded_by' => $user->id,
        ]);

        // Try to create payment with same amount different date - should work
        $response = $this->actingAs($user, 'web')
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/admin/repayments', [
                'application_id' => $application->id,
                'amount' => 10000,
                'payment_date' => '2024-02-15',
                'payment_method' => 'Cash',
            ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function loan_product_rejected_character_member_path()
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        $this->withoutExceptionHandling();

        $user = User::factory()->create(['usertype' => 'user']);
        $member = CoopMember::factory()->create([
            'user_id' => $user->id,
            'full_name' => 'Juan Santos',
            'status' => CoopMember::STATUS_ACTIVE,
        ]);
        $user->forceFill(['coop_member_id' => $member->id])->save();
        $this->actingAs($user);

        $response = $this->post(route('user.loans.store'), [
            'loan_type' => 'character',
            'amount' => 5000,
            'monthly_income' => 20000,
            'existing_monthly_obligations' => 2000,
            'purpose_of_loan' => 'test',
            'source_of_income' => 'Salary',
            'document_consent' => '1',
            '_token'           => csrf_token(),
        ]);

        $response->assertSessionHasErrors('loan_type');
        $response->assertStatus(302);
    }

    /** @test */
    public function loan_product_rejected_recovery_member_path()
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        $this->withoutExceptionHandling();

        $user = User::factory()->create(['usertype' => 'user']);
        $member = CoopMember::factory()->create([
            'user_id' => $user->id,
            'full_name' => 'Carlos Ray',
            'status' => CoopMember::STATUS_ACTIVE,
        ]);
        $user->forceFill(['coop_member_id' => $member->id])->save();
        $this->actingAs($user);

        $response = $this->post(route('user.loans.store'), [
            'loan_type' => 'recovery',
            'amount' => 5000,
            'monthly_income' => 20000,
            'existing_monthly_obligations' => 2000,
            'purpose_of_loan' => 'test',
            'source_of_income' => 'Salary',
            'document_consent' => '1',
            '_token'           => csrf_token(),
        ]);

        $response->assertSessionHasErrors('loan_type');
        $response->assertStatus(302);
    }

    /** @test */
    public function loan_product_rejected_secured_member_path()
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        $this->withoutExceptionHandling();

        $user = User::factory()->create(['usertype' => 'user']);
        $member = CoopMember::factory()->create([
            'user_id' => $user->id,
            'full_name' => 'Amir Security',
            'status' => CoopMember::STATUS_ACTIVE,
        ]);
        $user->forceFill(['coop_member_id' => $member->id])->save();
        $this->actingAs($user);

        $response = $this->post(route('user.loans.store'), [
            'loan_type' => 'secured',
            'amount' => 5000,
            'monthly_income' => 20000,
            'existing_monthly_obligations' => 2000,
            'purpose_of_loan' => 'test',
            'source_of_income' => 'Salary',
            'document_consent' => '1',
            '_token'           => csrf_token(),
        ]);

        $response->assertSessionHasErrors('loan_type');
        $response->assertStatus(302);
    }

    /** @test */
    public function loan_product_rejected_share_cap_member_path()
    {
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        $this->withoutExceptionHandling();

        $user = User::factory()->create(['usertype' => 'user']);
        $member = CoopMember::factory()->create([
            'user_id' => $user->id,
            'full_name' => 'Sora Share Capital',
            'status' => CoopMember::STATUS_ACTIVE,
        ]);
        $user->forceFill(['coop_member_id' => $member->id])->save();
        $this->actingAs($user);

        $response = $this->post(route('user.loans.store'), [
            'loan_type' => 'share_cap',
            'amount' => 5000,
            'monthly_income' => 20000,
            'existing_monthly_obligations' => 2000,
            'purpose_of_loan' => 'test',
            'source_of_income' => 'Salary',
            'document_consent' => '1',
            '_token'           => csrf_token(),
        ]);

        $response->assertSessionHasErrors('loan_type');
        $response->assertStatus(302);
    }

    /** @test */
    public function user_without_coop_member_gets_ineligible_result()
    {
        /** Member path (UserController::storeApplication):
         *  A registered user WITHOUT a coop_member_id link should be rejected
         *  because isActiveCoopMember() returns false, redirecting with an error.
         *  The loan application should not be created and the user should see
         *  an appropriate error (not a missing-session-errors failure).
         */
        $user = User::factory()->create(['usertype' => 'user']);
        // Note: coop_member_id is NOT set, so isActiveCoopMember() returns false

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('user.loans.store'), [
                'loan_type' => 'personal',
                'amount' => 5000,
                'monthly_income' => 20000,
                'existing_monthly_obligations' => 0,
                'purpose_of_loan' => 'Test',
                'source_of_income' => 'Salary',
                'document_consent' => '1',
                '_token'           => csrf_token(),
            ]);

        // Should redirect because user is not an active coop member
        $response->assertStatus(302);

        // The redirected session should have a flash error (not validation errors
        // on 'loan_type' — those only appear when the user IS an active member
        // and form validation fails)
        $response->assertSessionHas('error');

        /** Staff-encoded path (LoanSubmissionService::submit):
         *  A LoanApplication created with user_id pointing to a user who has
         *  no coop_member_id should result in "Credit limit not assessed" —
         *  the evaluate() method finds no member linked to that user_id,
         *  making approvedCreditLimit null, which forces ineligibility.
         */
        $unlinkedMember = CoopMember::factory()->create([
            'user_id' => null,
            'full_name' => 'Unlinked Member',
            'status' => CoopMember::STATUS_ACTIVE,
            'savings_balance' => 0,
        ]);

        $service = app(LoanEvaluationService::class);
        $loanApp = LoanApplication::factory()->create([
            'user_id' => null,  // staff-encoded, no user_id
            'amount' => 5000,
            'monthly_income' => 20000,
            'loan_type' => 'personal',
            'term_months' => 12,
            'existing_monthly_obligations' => 0,
            'status' => 'pending',
        ]);

        $result = $service->evaluate($loanApp);

        // Should be ineligible with "Credit limit not assessed"
        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('Credit limit not assessed', $result['rejection_reason'] ?? '');
        $this->assertEquals(0, $result['recommended_amount']);
        $this->assertEquals('High', $result['risk_level']);
    }
}
