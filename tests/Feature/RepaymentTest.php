<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepaymentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function payment_reduces_balance_and_creates_payment_record()
    {
        $loanApplication = LoanApplication::factory()->create([
            'amount' => 10000,
            'term_months' => 12,
            'status' => 'approved',
            'remaining_balance' => 10000, // no prior payments
        ]);

        $application = Application::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'app_id' => 'APP-0001',
            'amount' => 10000,
            'term_months' => 12,
            'status' => 'Approved',
            'remaining_balance' => 10000,
        ]);

        $this->actingAs(User::factory()->create(['usertype' => 'loan_officer']));

        // Act: Make a payment
        $paymentAmount = 2000;
        $response = $this->actingAs(User::factory()->create(['usertype' => 'loan_officer']))
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/admin/repayments', [
                'application_id' => $application->id,
                'amount' => $paymentAmount,
                'reference_no' => 'REF-123',
                'payment_date' => now()->toDateString(),
                'payment_method' => 'bank_transfer',
                'notes' => 'Test payment',
            ]);

        // Assert: Payment successful
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Assert: Payment record created
        $this->assertDatabaseHas('payments', [
            'loan_application_id' => $loanApplication->id,
            'amount_paid' => $paymentAmount,
            'reference_no' => 'REF-123',
            'payment_method' => 'bank_transfer',
        ]);

        // Assert: Balance reduced on both tables
        // total_payable for 10000, 12 months @ 1% = ~10661.88
        // remaining_balance = total_payable - 2000 = 8661.88
        $loanApplication->refresh();
        $application->refresh();
        $this->assertEquals(8661.88, $loanApplication->remaining_balance, '',
            'loanApplication remaining_balance should be 8661.88 (total_payable 10661.88 - 2000 payment)');
        $this->assertEquals(8661.88, $application->remaining_balance, '',
            'application remaining_balance should be 8661.88 (total_payable 10661.88 - 2000 payment)');
    }

    /** @test */
    public function overpayment_is_rejected()
    {
        $loanApplication = LoanApplication::factory()->create([
            'amount' => 5000,
            'term_months' => 6,
            'status' => 'approved',
            'remaining_balance' => 5176.44, // total_payable for 5000/6m @ 1%
        ]);

        $application = Application::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'app_id' => 'APP-0002',
            'amount' => 5000,
            'term_months' => 6,
            'status' => 'Approved',
            'remaining_balance' => 5176.44,
        ]);

        $this->actingAs(User::factory()->create(['usertype' => 'loan_officer']));

        // Act: Try to pay more than total payable
        $response = $this->actingAs(User::factory()->create(['usertype' => 'loan_officer']))
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/admin/repayments', [
                'application_id' => $application->id,
                'amount' => 6000, // overpayment - exceeds total_payable of 5176.44
                'reference_no' => 'REF-456',
                'payment_date' => now()->toDateString(),
            ]);

        // Assert: Rejected
        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Payment amount exceeds remaining balance.',
        ]);

        // Assert: Balance unchanged
        $loanApplication->refresh();
        $application->refresh();
        $this->assertEquals(5176.44, $loanApplication->remaining_balance);
        $this->assertEquals(5176.44, $application->remaining_balance);

        // Assert: No payment created
        $this->assertDatabaseMissing('payments', [
            'loan_application_id' => $loanApplication->id,
            'reference_no' => 'REF-456',
        ]);
    }

    /** @test */
    public function payment_marks_loan_paid_when_balance_zero()
    {
        $loanApplication = LoanApplication::factory()->create([
            'amount' => 10000,
            'term_months' => 12,
            'status' => 'approved',
            'remaining_balance' => 10661.88, // total_payable for 10000/12m @ 1%
        ]);

        $application = Application::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'app_id' => 'APP-0003',
            'amount' => 10000,
            'term_months' => 12,
            'status' => 'Approved',
            'remaining_balance' => 10661.88,
        ]);

        $this->actingAs(User::factory()->create(['usertype' => 'loan_officer']));

        // Act: Pay full total_payable amount (principal + interest)
        $response = $this->actingAs(User::factory()->create(['usertype' => 'loan_officer']))
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/admin/repayments', [
                'application_id' => $application->id,
                'amount' => 10661.88,
                'reference_no' => 'REF-789',
                'payment_date' => now()->toDateString(),
            ]);

        // Assert: Success
        $response->assertStatus(200);

        // Assert: Loan marked paid, next_due cleared
        $loanApplication->refresh();
        $application->refresh();
        $this->assertEquals('paid', $loanApplication->status);
        $this->assertEquals('Paid', $application->status);
        $this->assertNull($loanApplication->next_due_date);
        $this->assertNull($application->next_due_date);
        $this->assertEquals(0, $loanApplication->remaining_balance);
        $this->assertEquals(0, $application->remaining_balance);

        // Assert: Payment created
        $this->assertDatabaseHas('payments', [
            'loan_application_id' => $loanApplication->id,
            'amount_paid' => 10661.88,
            'reference_no' => 'REF-789',
        ]);
    }

    /** @test */
    public function payment_advances_next_due_date_when_not_zero()
    {
        $loanApplication = LoanApplication::factory()->create([
            'amount' => 12000,
            'term_months' => 24,
            'status' => 'approved',
            'remaining_balance' => 13557.12, // total_payable for 12000/24m @ 1%
            'next_due_date' => now()->endOfMonth(),
        ]);

        $application = Application::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'app_id' => 'APP-0004',
            'amount' => 12000,
            'term_months' => 24,
            'status' => 'Approved',
            'remaining_balance' => 13557.12,
            'next_due_date' => now()->endOfMonth(),
        ]);

        $this->actingAs(User::factory()->create(['usertype' => 'loan_officer']));

        // Act: Make partial payment
        $originalDue = $application->next_due_date;
        $response = $this->actingAs(User::factory()->create(['usertype' => 'loan_officer']))
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/admin/repayments', [
                'application_id' => $application->id,
                'amount' => 3000,
                'reference_no' => 'REF-999',
                'payment_date' => now()->toDateString(),
            ]);

        // Assert: Success
        $response->assertStatus(200);

        // Assert: Balance reduced
        $loanApplication->refresh();
        $application->refresh();
        // remaining = total_payable (13557.12) - payment (3000) = 10557.12
        $this->assertEquals(10557.12, $loanApplication->remaining_balance, '', 1);
        $this->assertEquals(10557.12, $application->remaining_balance, '', 1);

        // Assert: Next due date advanced by installments covered
        // PaymentService uses emi(totalPayable, term, interest_rate??0) — rate=0 gives 13557.12/24=564.88
        // So installmentsCovered = floor(3000/564.88) = 5 months
        $totalPayable = $loanApplication->totalPayable();
        $monthlyPayment = app(\App\Services\AmortizationService::class)->emi($totalPayable, 24, 0);
        $installmentsCovered = (int) floor(3000 / $monthlyPayment);
        $this->assertEquals($originalDue->copy()->addMonths($installmentsCovered)->format('Y-m-d'), $loanApplication->next_due_date->format('Y-m-d'));
        $this->assertEquals($originalDue->copy()->addMonths($installmentsCovered)->format('Y-m-d'), $application->next_due_date->format('Y-m-d'));
    }

    /** @test */
    public function duplicate_reference_number_rejected()
    {
        $loanApplication = LoanApplication::factory()->create([
            'amount' => 8000,
            'term_months' => 10,
            'status' => 'approved',
            'remaining_balance' => 8364.70, // total_payable for 8000/10m @ 1%
        ]);

        $application = Application::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'app_id' => 'APP-0010',
            'amount' => 8000,
            'term_months' => 10,
            'status' => 'Approved',
            'remaining_balance' => 8364.70,
        ]);

        // Existing payment
        Payment::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'reference_no' => 'DUP-REF',
        ]);

        $this->actingAs(User::factory()->create(['usertype' => 'loan_officer']));

        // Act: Try to use same reference
        $response = $this->actingAs(User::factory()->create(['usertype' => 'loan_officer']))
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/admin/repayments', [
                'application_id' => $application->id,
                'amount' => 2000,
                'reference_no' => 'DUP-REF', // duplicate
                'payment_date' => now()->toDateString(),
            ]);

        // Assert: Rejected
        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'A payment with this reference number already exists for this loan.',
        ]);

        // Assert: No new payment created with duplicate ref
        $paymentCount = Payment::where('loan_application_id', $loanApplication->id)
            ->where('reference_no', 'DUP-REF')
            ->count();
        $this->assertEquals(1, $paymentCount);
    }

    /** @test */
    public function edit_repayment_recomputes_balance_and_due_date()
    {
        $loanApplication = LoanApplication::factory()->create([
            'amount' => 10000,
            'term_months' => 12,
            'status' => 'approved',
            'remaining_balance' => 10661.88, // total_payable for 10000/12m @ 1%
        ]);

        $application = Application::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'app_id' => 'APP-ED1',
            'amount' => 10000,
            'term_months' => 12,
            'status' => 'Approved',
            'remaining_balance' => 10661.88,
        ]);

        // Create an existing payment that we will edit
        $existingPayment = Payment::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'amount_paid' => 2000,
            'reference_no' => 'EDIT-REF',
            'payment_date' => now()->subMonths(1)->toDateString(),
        ]);

        $this->actingAs(User::factory()->create(['usertype' => 'loan_officer']));

        // Act: Edit the existing payment amount
        $response = $this->actingAs(User::factory()->create(['usertype' => 'loan_officer']))
            ->withHeaders(['Accept' => 'application/json'])
            ->put("/repayments/{$existingPayment->id}", [
                'amount' => 5000,
                'payment_method' => 'bank_transfer',
                'reference_no' => 'EDIT-REF',
                'payment_date' => now()->toDateString(),
                'notes' => 'Updated payment',
            ]);

        // Assert: Updated
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Assert: Balance recomputed — payment updated from 2000 to 5000
        // remaining = total_payable (10661.88) - 5000 = 5661.88
        $loanApplication->refresh();
        $application->refresh();
        $this->assertEquals(5661.88, $loanApplication->remaining_balance, '', 1);
        $this->assertEquals(5661.88, $application->remaining_balance, '', 1);

        // Assert: Payment was updated in place
        $this->assertDatabaseHas('payments', [
            'id' => $existingPayment->id,
            'reference_no' => 'EDIT-REF',
            'amount_paid' => 5000,
        ]);
    }

    /** @test */
    public function edit_repayment_rejects_over_balance()
    {
        $loanApplication = LoanApplication::factory()->create([
            'amount' => 5000,
            'term_months' => 6,
            'status' => 'approved',
            'remaining_balance' => 5176.44, // total_payable for 5000/6m @ 1%
        ]);

        $application = Application::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'app_id' => 'APP-ED2',
            'amount' => 5000,
            'term_months' => 6,
            'status' => 'Approved',
            'remaining_balance' => 5176.44,
        ]);

        // Create an existing payment of 4000
        Payment::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'amount_paid' => 4000,
            'reference_no' => 'OVER-REF',
        ]);

        $this->actingAs(User::factory()->create(['usertype' => 'loan_officer']));

        // Act: Try to increase to 6000 — total would be 6000 > 5176.44 total payable
        $existingPayment = Payment::where('loan_application_id', $loanApplication->id)->first();
        $this->assertNotNull($existingPayment);

        $paymentId = $existingPayment->id;

        $response = $this->actingAs(User::factory()->create(['usertype' => 'loan_officer']))
            ->withHeaders(['Accept' => 'application/json'])
            ->put("/repayments/{$paymentId}", [
                'amount' => 6000,
                'payment_method' => 'cash',
                'reference_no' => 'OVER-REF-UPDATED',
                'payment_date' => now()->toDateString(),
            ]);

        // Assert: Rejected because new total would exceed total payable
        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Total paid would exceed total payable.',
        ]);
    }

    /** @test */
    public function edit_repayment_rejects_duplicate_reference()
    {
        $loanApplication = LoanApplication::factory()->create([
            'amount' => 5000,
            'term_months' => 6,
            'status' => 'approved',
            'remaining_balance' => 5176.44, // total_payable for 5000/6m @ 1%
        ]);

        $application = Application::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'app_id' => 'APP-ED3',
            'amount' => 5000,
            'term_months' => 6,
            'status' => 'Approved',
            'remaining_balance' => 5176.44,
        ]);

        // First payment with reference DUP-REF
        Payment::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'amount_paid' => 1000,
            'reference_no' => 'DUP-REF',
        ]);

        // Second payment with a different reference
        $otherPayment = Payment::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'amount_paid' => 2000,
            'reference_no' => 'OTHER-REF',
        ]);

        $this->actingAs(User::factory()->create(['usertype' => 'loan_officer']));

        // Act: Edit the first payment to use the same reference as the second
        $response = $this->actingAs(User::factory()->create(['usertype' => 'loan_officer']))
            ->withHeaders(['Accept' => 'application/json'])
            ->put("/repayments/{$otherPayment->id}", [
                'amount' => 1500,
                'reference_no' => 'DUP-REF',
            ]);

        // Assert: Rejected
        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'A payment with this reference number already exists for this loan.',
        ]);

        // Assert: Original unchanged
        $this->assertEquals(2000, Payment::find($otherPayment->id)->amount_paid);
    }

    /** @test */
    public function edit_repayment_recomputes_remaining_balance_after_and_running_balance()
    {
        $loanApplication = LoanApplication::factory()->create([
            'amount' => 10000,
            'term_months' => 12,
            'status' => 'approved',
            'remaining_balance' => 10661.88, // total_payable for 10000/12m @ 1%
            'next_due_date' => now()->endOfMonth(),
        ]);

        $application = Application::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'app_id' => 'APP-ED4',
            'amount' => 10000,
            'term_months' => 12,
            'status' => 'Approved',
            'remaining_balance' => 10661.88,
            'next_due_date' => now()->endOfMonth(),
        ]);

        // First payment of 100
        $payment1 = Payment::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'amount_paid' => 100,
            'reference_no' => 'P1',
            'payment_date' => now()->subDays(5)->toDateString(),
            'remaining_balance_after' => 9900,
        ]);

        // Second payment of 200 (running balance should be 9700)
        $payment2 = Payment::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'amount_paid' => 200,
            'reference_no' => 'P2',
            'payment_date' => now()->subDays(3)->toDateString(),
            'remaining_balance_after' => 9700,
        ]);

        $this->actingAs(User::factory()->create(['usertype' => 'loan_officer']));

        // Edit payment1 from 100 to 200 — total paid becomes 400, balance = 10661.88 - 400 = 10261.88
        $response = $this->actingAs(User::factory()->create(['usertype' => 'loan_officer']))
            ->withHeaders(['Accept' => 'application/json'])
            ->put("/repayments/{$payment1->id}", [
                'amount' => 200,
                'payment_method' => 'cash',
                'reference_no' => 'P1',
                'payment_date' => now()->subDays(5)->toDateString(),
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // (a) Both loan tables reflect total payable minus sum of payments
        // remaining = 10661.88 - (200 + 200) = 10261.88
        $loanApplication->refresh();
        $application->refresh();
        $this->assertEquals(10261.88, $loanApplication->remaining_balance, '', 1);
        $this->assertEquals(10261.88, $application->remaining_balance, '', 1);

        // (b) The edited payment's remaining_balance_after is recomputed
        // payment1 remaining_balance_after = 10661.88 - 200 = 10461.88
        $payment1->refresh();
        $this->assertEquals(10461.88, $payment1->remaining_balance_after, '', 1);

        // (c) The later payment's running remaining_balance_after is also updated
        // payment2 remaining_balance_after = 10661.88 - 400 = 10261.88
        $payment2->refresh();
        $this->assertEquals(10261.88, $payment2->remaining_balance_after, '', 1);

        // (d) next_due_date follows installments covered
        $totalPayable = $loanApplication->totalPayable();
        $monthlyPayment = app(\App\Services\AmortizationService::class)->emi($totalPayable, 12, 0);
        $installmentsCovered = (int) floor(400 / $monthlyPayment);
        $this->assertEquals(
            $loanApplication->next_due_date->copy()->addMonths($installmentsCovered)->format('Y-m-d'),
            $application->next_due_date->format('Y-m-d')
        );

        // (e) Over-pay is rejected with 422 and nothing changes
        // total paid after edit = 200 (payment1) + 200 (payment2) - 200 + 20000 = 20200 > 10661.88
        $response = $this->actingAs(User::factory()->create(['usertype' => 'loan_officer']))
            ->withHeaders(['Accept' => 'application/json'])
            ->put("/repayments/{$payment1->id}", [
                'amount' => 20000,
                'payment_method' => 'cash',
                'reference_no' => 'P1',
                'payment_date' => now()->subDays(5)->toDateString(),
            ]);
        $response->assertStatus(422);
        $response->assertJson(['success' => false]);

        $payment1->refresh();
        $this->assertEquals(200, $payment1->amount_paid);
        $loanApplication->refresh();
        $this->assertEquals(10261.88, $loanApplication->remaining_balance, '', 1);
    }

    /** @test */
    public function partial_payment_does_not_advance_due_date()
    {
        $loanApplication = LoanApplication::factory()->create([
            'amount' => 12000,
            'term_months' => 24,
            'status' => 'approved',
            'remaining_balance' => 13557.12, // total_payable for 12000/24m @ 1%
            'next_due_date' => now()->endOfMonth(),
        ]);

        $application = Application::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'app_id' => 'APP-PART1',
            'amount' => 12000,
            'term_months' => 24,
            'status' => 'Approved',
            'remaining_balance' => 13557.12,
            'next_due_date' => now()->endOfMonth(),
        ]);

        $this->actingAs(User::factory()->create(['usertype' => 'loan_officer']));

        // Act: Make partial payment less than monthly installment
        $monthlyPayment = app(\App\Services\AmortizationService::class)->emi(12000, 24);
        $partialAmount = min(2000, $monthlyPayment - 100); // well below monthly installment

        $originalDue = $application->next_due_date;
        $response = $this->actingAs(User::factory()->create(['usertype' => 'loan_officer']))
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/admin/repayments', [
                'application_id' => $application->id,
                'amount' => $partialAmount,
                'reference_no' => 'PART-REF',
                'payment_date' => now()->toDateString(),
            ]);

        $response->assertStatus(200);

        // Assert: Balance reduced by partial amount
        // remaining = total_payable (13557.12) - partialAmount
        $loanApplication->refresh();
        $application->refresh();
        $this->assertEquals(round(13557.12 - $partialAmount, 2), $loanApplication->remaining_balance, '', 1);
        $this->assertEquals(round(13557.12 - $partialAmount, 2), $application->remaining_balance, '', 1);

        // Assert: Next due date preserved (not advanced, not cleared)
        $this->assertNotNull($loanApplication->next_due_date);
        $this->assertNotNull($application->next_due_date);
        $this->assertEquals($originalDue, $loanApplication->next_due_date);
        $this->assertEquals($originalDue, $application->next_due_date);
    }

    /** @test */
    public function final_payment_marks_loan_paid()
    {
        $loanApplication = LoanApplication::factory()->create([
            'amount' => 10000,
            'term_months' => 12,
            'status' => 'approved',
            'remaining_balance' => 10661.88, // total_payable for 10000/12m @ 1%
        ]);

        $application = Application::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'app_id' => 'APP-FINAL',
            'amount' => 10000,
            'term_months' => 12,
            'status' => 'Approved',
            'remaining_balance' => 10661.88,
        ]);

        $this->actingAs(User::factory()->create(['usertype' => 'loan_officer']));

        // Store final payment covering full total_payable
        $response = $this->actingAs(User::factory()->create(['usertype' => 'loan_officer']))
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/admin/repayments', [
                'application_id' => $application->id,
                'amount' => 10661.88,
                'reference_no' => 'FINAL-REF',
                'payment_date' => now()->toDateString(),
            ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        $loanApplication->refresh();
        $application->refresh();
        $this->assertEquals('paid', $loanApplication->status);
        $this->assertEquals('Paid', $application->status);
        $this->assertNull($loanApplication->next_due_date);
        $this->assertNull($application->next_due_date);
        $this->assertEquals(0, $loanApplication->remaining_balance);
        $this->assertEquals(0, $application->remaining_balance);
    }

    /** @test */
    public function repayments_page_renders_for_branch_manager()
    {
        $branchManager = User::factory()->asBranchManager()->create([
            'name' => 'Juan Dela Cruz',
        ]);

        $loanApplication = LoanApplication::factory()->create([
            'user_id' => $branchManager->id,
            'amount' => 10000,
            'term_months' => 12,
            'status' => 'approved',
            'remaining_balance' => 8000,
        ]);

        $application = Application::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'app_id' => 'APP-0001',
            'amount' => 10000,
            'term_months' => 12,
            'status' => 'Approved',
            'remaining_balance' => 8000,
        ]);

        // Payment with reference_no
        Payment::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'user_id' => $branchManager->id,
            'amount_paid' => 2000,
            'remaining_balance_after' => 8000,
            'reference_no' => 'REF-001',
            'payment_method' => 'Cash',
            'payment_date' => now()->subDays(5),
            'encoded_by' => $branchManager->id,
            'notes' => 'First payment',
        ]);

        // Payment without reference_no (should show #REP-<id>)
        $paymentNoRef = Payment::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'user_id' => $branchManager->id,
            'amount_paid' => 1000,
            'remaining_balance_after' => 7000,
            'reference_no' => null,
            'payment_method' => 'GCash',
            'payment_date' => now(),
            'encoded_by' => $branchManager->id,
            'notes' => '',
        ]);

        $response = $this->actingAs($branchManager)
            ->get('/repayments');

        $response->assertStatus(200);
        $response->assertSee('Repayments');
        $response->assertSee('Dela Cruz, Juan');
        $response->assertSee('#APP-0001');
        $response->assertSee('₱2,000.00');
        $response->assertSee('₱8,000.00'); // Balance after first payment
        $response->assertSee('REF-001');
        $response->assertSee('#REP-' . $paymentNoRef->id);
        $response->assertSee('View');
        $response->assertSee('Edit');
        $response->assertSee($branchManager->name);
    }

    /** @test */
    public function repayments_page_renders_for_admin()
    {
        $admin = User::factory()->create(['usertype' => 'admin', 'role' => 'admin', 'name' => 'Maria Santos']);

        $loanApplication = LoanApplication::factory()->create([
            'user_id' => $admin->id,
            'amount' => 15000,
            'term_months' => 24,
            'status' => 'approved',
            'remaining_balance' => 12000,
        ]);

        $application = Application::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'app_id' => 'APP-0002',
            'amount' => 15000,
            'term_months' => 24,
            'status' => 'Approved',
            'remaining_balance' => 12000,
        ]);

        Payment::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'user_id' => $admin->id,
            'amount_paid' => 3000,
            'remaining_balance_after' => 12000,
            'reference_no' => 'ADMIN-REF',
            'payment_method' => 'Bank Transfer',
            'payment_date' => now()->subDays(3),
            'encoded_by' => $admin->id,
            'notes' => 'Admin recorded',
        ]);

        $response = $this->actingAs($admin)
            ->get('/repayments');

        $response->assertStatus(200);
        $response->assertSee('Santos, Maria');
        $response->assertSee('#APP-0002');
        $response->assertSee('₱3,000.00');
        $response->assertSee('ADMIN-REF');
        $response->assertSee('View');
        $response->assertSee('Edit');
    }

    /** @test */
    public function repayments_page_renders_borrower_with_apostrophe()
    {
        $branchManager = User::factory()->asBranchManager()->create([
            'name' => "Sean O'Brien",
        ]);

        $loanApplication = LoanApplication::factory()->create([
            'user_id' => $branchManager->id,
            'amount' => 5000,
            'term_months' => 6,
            'status' => 'approved',
            'remaining_balance' => 4000,
        ]);

        $application = Application::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'app_id' => 'APP-OBRIEN',
            'amount' => 5000,
            'term_months' => 6,
            'status' => 'Approved',
            'remaining_balance' => 4000,
        ]);

        Payment::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'user_id' => $branchManager->id,
            'amount_paid' => 1000,
            'remaining_balance_after' => 4000,
            'reference_no' => 'OBRIEN-REF',
            'payment_method' => 'Cash',
            'payment_date' => now(),
            'encoded_by' => $branchManager->id,
        ]);

        $response = $this->actingAs($branchManager)
            ->get('/repayments');

        $response->assertStatus(200);
        $response->assertSee("O'Brien, Sean");
        $response->assertSee('#APP-OBRIEN');
    }
}