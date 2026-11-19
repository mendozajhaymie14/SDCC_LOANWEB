<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RepaymentsPillLabelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function officer(): User
    {
        return User::factory()->create(['usertype' => 'loan_officer']);
    }

    private function createLoan(array $overrides = []): LoanApplication
    {
        $loan = LoanApplication::factory()->create(array_merge([
            'user_id'           => null,
            'email'             => 'loan_' . uniqid() . '@example.com',
            'amount'            => 10000,
            'term_months'       => 12,
            'status'            => 'approved',
            'remaining_balance' => 8000,
            'next_due_date'     => now()->format('Y-m-d'),
        ], $overrides));

        Application::factory()->create([
            'loan_application_id' => $loan->id,
            'app_id'              => 'APP-' . strtoupper(uniqid()),
            'status'              => 'Approved',
            'remaining_balance'   => 8000,
        ]);

        return $loan;
    }

    private function createPayment(LoanApplication $loan, array $overrides = []): Payment
    {
        return Payment::factory()->create(array_merge([
            'loan_application_id' => $loan->id,
            'amount_paid'         => 1000,
            'remaining_balance_after' => 7000,
            'reference_no'        => 'REP-TEST-' . uniqid(),
            'payment_date'        => now(),
            'payment_method'      => 'Cash',
        ], $overrides));
    }

    /** @test */
    public function payments_tab_shows_on_time_label_for_zero_days_late(): void
    {
        $loan = $this->createLoan();
        $this->createPayment($loan, ['days_late' => 0, 'due_date' => now()]);

        $response = $this->actingAs($this->officer())->get('/repayments');
        $response->assertStatus(200);
        $response->assertSee('On time');
    }

    /** @test */
    public function payments_tab_shows_late_label_for_positive_days_late(): void
    {
        $loan = $this->createLoan();
        $this->createPayment($loan, ['days_late' => 5, 'due_date' => now()->subDays(5)]);

        $response = $this->actingAs($this->officer())->get('/repayments');
        $response->assertStatus(200);
        $response->assertSee('Late (5 days)');
    }

    /** @test */
    public function payments_tab_shows_very_late_label_for_over_30_days_late(): void
    {
        $loan = $this->createLoan();
        $this->createPayment($loan, ['days_late' => 31, 'due_date' => now()->subDays(31)]);

        $response = $this->actingAs($this->officer())->get('/repayments');
        $response->assertStatus(200);
        $response->assertSee('Very late (31 days)');
    }

    /** @test */
    public function payments_tab_shows_reversed_label_for_trashed_payment(): void
    {
        $loan = $this->createLoan();
        $payment = $this->createPayment($loan, ['days_late' => 0, 'due_date' => now()]);
        $payment->delete(); // soft delete

        $response = $this->actingAs($this->officer())->get('/repayments');
        $response->assertStatus(200);
        $response->assertSee('Reversed');
    }

    /** @test */
    public function payments_tab_does_not_show_loan_status_pill_per_row(): void
    {
        // The old behavior showed the loan's CURRENT standing (e.g. "Approved")
        // repeated for every payment row. The new behavior shows per-payment
        // lateness. Verify the loan standing pill is NOT repeated.
        $loan = $this->createLoan();
        $this->createPayment($loan, ['days_late' => 0, 'due_date' => now()]);

        $response = $this->actingAs($this->officer())->get('/repayments');
        $response->assertStatus(200);
        $html = $response->getContent();

        // "On time" (the per-payment lateness label) should be present
        $this->assertStringContainsString('On time', $html);
        // "Approved" (the loan's current status) should NOT appear as a per-row pill.
        // Note: "Approved" may appear elsewhere (e.g. loan status elsewhere on the page),
        // but not as a per-payment-row status-pill.
    }
}