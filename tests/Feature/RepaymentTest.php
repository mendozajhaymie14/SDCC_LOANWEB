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
        // Arrange: Create an approved/disbursed loan with balance
        $loanApplication = LoanApplication::factory()->create([
            'amount' => 10000,
            'term_months' => 12,
            'status' => 'approved',
            'remaining_balance' => 8800, // after some payments
        ]);

        $application = Application::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'app_id' => 'APP-0001',
            'amount' => 10000,
            'term_months' => 12,
            'status' => 'Approved',
            'remaining_balance' => 8800,
        ]);

        $this->actingAs(User::factory()->create(['usertype' => 'admin']));

        // Act: Make a payment
        $paymentAmount = 2000;
        $response = $this->postJson('/admin/repayments', [
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
        $loanApplication->refresh();
        $application->refresh();
        $this->assertEquals(6800, $loanApplication->remaining_balance);
        $this->assertEquals(6800, $application->remaining_balance);
    }

    /** @test */
    public function overpayment_is_rejected()
    {
        // Arrange: Create an approved loan
        $loanApplication = LoanApplication::factory()->create([
            'amount' => 5000,
            'term_months' => 6,
            'status' => 'approved',
            'remaining_balance' => 3000,
        ]);

        $application = Application::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'app_id' => 'APP-0002',
            'amount' => 5000,
            'term_months' => 6,
            'status' => 'Approved',
            'remaining_balance' => 3000,
        ]);

        $this->actingAs(User::factory()->create(['usertype' => 'admin']));

        // Act: Try to pay more than balance
        $response = $this->postJson('/admin/repayments', [
            'application_id' => $application->id,
            'amount' => 4000, // overpayment
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
        $this->assertEquals(3000, $loanApplication->remaining_balance);
        $this->assertEquals(3000, $application->remaining_balance);

        // Assert: No payment created
        $this->assertDatabaseMissing('payments', [
            'loan_application_id' => $loanApplication->id,
            'reference_no' => 'REF-456',
        ]);
    }

    /** @test */
    public function payment_marks_loan_paid_when_balance_zero()
    {
        // Arrange: Create loan with exact balance
        $loanApplication = LoanApplication::factory()->create([
            'amount' => 10000,
            'term_months' => 12,
            'status' => 'approved',
            'remaining_balance' => 1500,
        ]);

        $application = Application::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'app_id' => 'APP-0003',
            'amount' => 10000,
            'term_months' => 12,
            'status' => 'Approved',
            'remaining_balance' => 1500,
        ]);

        $this->actingAs(User::factory()->create(['usertype' => 'admin']));

        // Act: Pay remaining balance
        $response = $this->postJson('/admin/repayments', [
            'application_id' => $application->id,
            'amount' => 1500,
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
            'amount_paid' => 1500,
            'reference_no' => 'REF-789',
        ]);
    }

    /** @test */
    public function payment_advances_next_due_date_when_not_zero()
    {
        // Arrange: Create loan
        $loanApplication = LoanApplication::factory()->create([
            'amount' => 12000,
            'term_months' => 24,
            'status' => 'approved',
            'remaining_balance' => 9000,
        ]);

        $application = Application::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'app_id' => 'APP-0004',
            'amount' => 12000,
            'term_months' => 24,
            'status' => 'Approved',
            'remaining_balance' => 9000,
            'next_due_date' => now()->endOfMonth(),
        ]);

        $this->actingAs(User::factory()->create(['usertype' => 'admin']));

        // Act: Make partial payment
        $originalDue = $application->next_due_date;
        $response = $this->postJson('/admin/repayments', [
            'application_id' => $application->id,
            'amount' => 3000,
            'reference_number' => 'REF-999',
            'payment_date' => now()->toDateString(),
        ]);

        // Assert: Success
        $response->assertStatus(200);

        // Assert: Balance reduced
        $loanApplication->refresh();
        $application->refresh();
        $this->assertEquals(6000, $loanApplication->remaining_balance);
        $this->assertEquals(6000, $application->remaining_balance);

        // Assert: Next due date advanced by one month (compare dates only, ignoring time)
        $this->assertEquals($originalDue->copy()->addMonth()->format('Y-m-d'), $loanApplication->next_due_date->format('Y-m-d'));
        $this->assertEquals($originalDue->copy()->addMonth()->format('Y-m-d'), $application->next_due_date->format('Y-m-d'));
    }

    /** @test */
    public function duplicate_reference_number_rejected()
    {
        // Arrange: Create loan and existing payment
        $loanApplication = LoanApplication::factory()->create([
            'amount' => 8000,
            'term_months' => 10,
            'status' => 'approved',
            'remaining_balance' => 5000,
        ]);

        $application = Application::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'app_id' => 'APP-0010',
            'amount' => 8000,
            'term_months' => 10,
            'status' => 'Approved',
            'remaining_balance' => 5000,
        ]);

        // Existing payment
        Payment::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'reference_no' => 'DUP-REF',
        ]);

        $this->actingAs(User::factory()->create(['usertype' => 'admin']));

        // Act: Try to use same reference
        $response = $this->postJson('/admin/repayments', [
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
        $this->assertEquals(1, $paymentCount); // only the original
    }
}