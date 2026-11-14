<?php

namespace Tests\Feature;

use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsentTest extends TestCase
{
    use RefreshDatabase;

    private function makeMember(): User
    {
        $user = User::factory()->create([
            'usertype' => 'member',
            'email'    => 'member@example.com',
        ]);

        $coopMember = CoopMember::factory()->create([
            'user_id'       => $user->id,
            'share_capital' => 30000,
            'is_migs'       => false,
            'status'        => CoopMember::STATUS_ACTIVE,
            'monthly_income' => 20000,
            // Monthly allowance ≥ 10,000 request so monthly gate passes
            'approved_monthly_credit_limit' => 20000,
            'monthly_credit_limit_source' => 'manual',
        ]);

        // Link the CoopMember back to the User so isActiveCoopMember() passes.
        $user->coop_member_id = $coopMember->id;
        $user->save();

        return $user;
    }

    /** @test */
    public function member_cannot_upload_documents_without_consent()
    {
        $member = $this->makeMember();

        // Create a dummy PDF file
        $file = \Illuminate\Http\UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $response = $this->actingAs($member)
            ->post(route('user.loans.store'), [
                '_token'             => csrf_token(),
                'full_name'          => 'Test Borrower',
                'email'             => $member->email,
                'contact_number'    => '09123456789',
                'source_of_income'  => 'Salary',
                'monthly_income'    => 20000,
                'existing_monthly_obligations' => 0,
                'purpose_of_loan'   => 'Personal',
                'loan_type'         => 'regular',
                'amount'            => 10000,
                'term_months'       => 36,
                'required_documents' => [$file],
                // consentGemini NOT provided
                'agree'             => true,
            ]);

        // Form submission with files but no consent redirects back with session errors
        $response->assertStatus(302);
        $response->assertRedirect();
        // Session errors should contain document_consent
        $response->assertSessionHasErrors(['document_consent']);
    }

    /** @test */
    public function member_can_upload_documents_with_consent()
    {
        $member = $this->makeMember();

        $file = \Illuminate\Http\UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $response = $this->actingAs($member)
            ->post(route('user.loans.store'), [
                '_token'             => csrf_token(),
                'full_name'          => 'Test Borrower',
                'email'             => $member->email,
                'contact_number'    => '09123456789',
                'source_of_income'  => 'Salary',
                'monthly_income'    => 20000,
                'existing_monthly_obligations' => 0,
                'purpose_of_loan'   => 'Personal',
                'loan_type'         => 'regular',
                'amount'            => 10000,
                'term_months'       => 36,
                'required_documents' => [$file],
                'document_consent'  => true,
                'agree'             => true,
            ]);

        // Should succeed (redirect to dashboard)
        $response->assertRedirect(route('user.dashboard'));
        $response->assertSessionHas('success');
    }

    /** @test */
    public function admin_cannot_submit_application_without_consent_when_files_present()
    {
        $admin = User::factory()->create([
            'usertype' => 'branch_manager',
            'email'    => 'admin@example.com',
        ]);

        $member = CoopMember::factory()->create([
            'user_id'       => null,
            'status'        => CoopMember::STATUS_ACTIVE,
            'full_name'     => 'Test Applicant',
            'email'         => 'test@example.com',
            'monthly_income'=> 30000,
            'existing_monthly_obligations' => 0,
            'share_capital' => 30000,
            'savings_balance' => 20000,
            'approved_credit_limit' => 50000,
            // Monthly allowance ≥ 10,000 request so monthly gate passes
            'approved_monthly_credit_limit' => 50000,
            'monthly_credit_limit_source' => 'manual',
            'address'      => '12 Rizal St, Brgy.centro',
            'occupation'   => 'Farmer',
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $response = $this->actingAs($admin)
            ->post(route('applications.encode.store'), [
                '_token'             => csrf_token(),
                'member_id'          => $member->id,
                'loan_type'          => 'regular',
                'amount'             => 10000,
                'term_months'        => 36,
                'purpose'            => 'Test loan',
                'monthly_income'     => 30000,
                'existing_monthly_obligations' => 0,
                'source_of_income'   => 'Salary',
                'required_documents' => [$file],
                // document_consent NOT provided
            ]);

        // Form submission with files but no consent redirects back with session errors
        $response->assertSessionHasErrors(['document_consent']);
        $this->assertSame(0, LoanApplication::where('encoded_by', $admin->id)->count());
    }

    /** @test */
    public function admin_can_submit_application_with_consent_when_files_present()
    {
        $admin = User::factory()->create([
            'usertype' => 'branch_manager',
            'email'    => 'admin@example.com',
        ]);

        $member = CoopMember::factory()->create([
            'user_id'       => null,
            'status'        => CoopMember::STATUS_ACTIVE,
            'full_name'     => 'Test Applicant',
            'email'         => 'test@example.com',
            'monthly_income'=> 30000,
            'existing_monthly_obligations' => 0,
            'share_capital' => 30000,
            'savings_balance' => 20000,
            'approved_credit_limit' => 50000,
            // Monthly allowance ≥ 10,000 request so monthly gate passes
            'approved_monthly_credit_limit' => 50000,
            'monthly_credit_limit_source' => 'manual',
            'address'      => '12 Rizal St, Brgy.centro',
            'occupation'   => 'Farmer',
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $response = $this->actingAs($admin)
            ->post(route('applications.encode.store'), [
                '_token'             => csrf_token(),
                'member_id'          => $member->id,
                'loan_type'          => 'regular',
                'amount'             => 10000,
                'term_months'        => 36,
                'purpose'            => 'Test loan',
                'monthly_income'     => 30000,
                'existing_monthly_obligations' => 0,
                'source_of_income'   => 'Salary',
                'required_documents'  => [$file],
                'document_consent'    => true,
            ]);

        // Should succeed — redirect to applications.index
        $response->assertRedirect(route('applications.index'));
        $response->assertSessionHas('success');

        $loanApp = LoanApplication::where('encoded_by', $admin->id)->latest()->first();
        $this->assertNotNull($loanApp, 'Application was not stored via encode path.');
        $this->assertEquals('Test Applicant', $loanApp->full_name);
        $this->assertEquals(10000, $loanApp->amount);
    }

    /** @test */
    public function application_without_documents_does_not_require_consent()
    {
        $member = $this->makeMember();

        // No files uploaded, consent not checked
        $response = $this->actingAs($member)
            ->post(route('user.loans.store'), [
                '_token'             => csrf_token(),
                'full_name'                  => 'Test Borrower',
                'email'                      => $member->email,
                'contact_number'             => '09123456789',
                'source_of_income'           => 'Salary',
                'monthly_income'             => 20000,
                'existing_monthly_obligations' => 0,
                'purpose_of_loan'            => 'Personal',
                'loan_type'                  => 'regular',
                'amount'                     => 10000,
                'term_months'                => 36,
                'agree'                      => true,
                // No consentGemini, no documents
            ]);

        // Should succeed
        $response->assertRedirect(route('user.dashboard'));
    }

    /** @test */
    public function loan_form_submits_without_employer_name(): void
    {
        $member = $this->makeMember();

        $file = \Illuminate\Http\UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $response = $this->actingAs($member)
            ->post(route('user.loans.store'), [
                '_token'             => csrf_token(),
                'full_name'                  => 'Test Borrower',
                'email'                      => $member->email,
                'contact_number'             => '09123456789',
                'source_of_income'           => 'Salary',
                'monthly_income'             => 20000,
                'existing_monthly_obligations' => 0,
                'purpose_of_loan'            => 'Personal',
                'loan_type'                  => 'regular',
                'amount'                     => 10000,
                'term_months'                => 36,
                'required_documents'         => [$file],
                'document_consent'           => true,
                'agree'                      => true,
                // employer_name intentionally omitted — field removed
            ]);

        $response->assertRedirect(route('user.dashboard'));
        $response->assertSessionHas('success');

        $this->assertEquals(1, LoanApplication::where('user_id', $member->id)->count());
    }

    /** @test */
    public function loan_form_does_not_contain_employer_field(): void
    {
        $member = $this->makeMember();

        $response = $this->actingAs($member)->get(route('user.loans.create'));

        $response->assertOk();
        $response->assertDontSee('employer_name');
        $response->assertDontSee('Employer');
    }
}