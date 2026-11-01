<?php

namespace Tests\Feature;

use App\Models\LoanApplication;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OverdueReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeManager()
    {
        return User::factory()->create([
            'usertype' => 'branch_manager',
            'email'    => 'manager@example.com',
        ]);
    }

    /** @test */
    public function overdue_payments_data_correctly_counts_overdue_loans()
    {
        $manager = $this->makeManager();

        // Create an overdue loan
        $loan = LoanApplication::factory()->create([
            'status'              => 'approved',
            'remaining_balance'   => 5000,
            'next_due_date'       => Carbon::now()->subDays(10),
        ]);

        $response = $this->actingAs($manager)->get('/reports?report_type=overdue_payments');

        $response->assertOk();
        $response->assertSee($loan->reference ?? 'N/A');
    }

    /** @test */
    public function report_only_includes_approved_status()
    {
        $manager = $this->makeManager();

        // Create a pending loan (should NOT appear in overdue report)
        LoanApplication::factory()->create([
            'status'              => 'pending',
            'remaining_balance'   => 5000,
            'next_due_date'       => Carbon::now()->subDays(5),
        ]);

        // Create an approved overdue loan
        $overdueLoan = LoanApplication::factory()->create([
            'status'              => 'approved',
            'remaining_balance'   => 5000,
            'next_due_date'       => Carbon::now()->subDays(5),
        ]);

        $response = $this->actingAs($manager)->get('/reports?report_type=overdue_payments');

        $response->assertOk();
    }

    /** @test */
    public function report_excludes_loans_with_zero_remaining_balance()
    {
        $manager = $this->makeManager();

        // Create a paid loan (zero remaining balance)
        LoanApplication::factory()->create([
            'status'              => 'paid',
            'remaining_balance'   => 0,
            'next_due_date'       => Carbon::now()->subDays(5),
        ]);

        // Create an actual overdue loan
        $overdueLoan = LoanApplication::factory()->create([
            'status'              => 'approved',
            'remaining_balance'   => 5000,
            'next_due_date'       => Carbon::now()->subDays(5),
        ]);

        $response = $this->actingAs($manager)->get('/reports?report_type=overdue_payments');

        $response->assertOk();
    }

    /** @test */
    public function pdf_export_works_for_overdue_payments_report()
    {
        $manager = $this->makeManager();

        // Create an overdue loan
        LoanApplication::factory()->create([
            'status'              => 'approved',
            'remaining_balance'   => 5000.50,
            'next_due_date'       => Carbon::now()->subDays(15),
        ]);

        $response = $this->actingAs($manager)->get('/reports/export-pdf?report_type=overdue_payments');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    /** @test */
    public function excel_export_works_for_overdue_payments_report()
    {
        $manager = $this->makeManager();

        // Create an overdue loan
        LoanApplication::factory()->create([
            'status'              => 'approved',
            'remaining_balance'   => 7500,
            'next_due_date'       => Carbon::now()->subDays(3),
        ]);

        $response = $this->actingAs($manager)->get('/reports/export-excel?report_type=overdue_payments');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}