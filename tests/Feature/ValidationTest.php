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
}