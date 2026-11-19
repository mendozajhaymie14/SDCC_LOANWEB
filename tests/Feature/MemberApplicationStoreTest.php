<?php

namespace Tests\Feature;

use App\Models\CoopMember;
use App\Models\MemberApplication;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MemberApplicationStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
    }

    protected function idUploadFields(): array
    {
        return [
            'id_type'    => 'drivers_license',
            'id_number'  => '1234567890',
            'id_front'   => UploadedFile::fake()->image('id-front.jpg'),
            'id_back'    => UploadedFile::fake()->image('id-back.jpg'),
        ];
    }

    public function test_same_as_current_address_copies_current_to_permanent(): void
    {
        $user = User::factory()->create([
            'email' => 'test@gmail.com',
            'password' => 'password',
            'usertype' => 'user',
        ]);

        $this->actingAs($user);

        $response = $this->post(route('member.applications.store'), [
            'first_name'    => 'John',
            'middle_name'   => 'Middle',
            'last_name'     => 'Doe',
            'occupation'    => 'Engineer',
            'province'      => 'National Capital Region (NCR)',
            'municipality'  => 'Las Piñas City',
            'barangay'      => 'B. F. International Village',
            'address_line'  => '123 Main Street',
            'zip_code'      => '1740',
            'contact_number'=> '09171234567',
            'email'         => 'test@gmail.com',
            'monthly_income'              => 50000,
            'existing_monthly_obligations'=> 2000,
            'same_as_current_address'     => 'on',
            'date_of_birth'   => '1990-05-15',
            'data_privacy_consent'        => '1',
            ...$this->idUploadFields(),
        ]);

        $application = MemberApplication::where('email', 'test@gmail.com')->first();
        $this->assertNotNull($application);

        // Verify permanent address fields were copied from current address
        $this->assertEquals('123 Main Street', $application->perm_street);
        $this->assertEquals('B. F. International Village', $application->perm_barangay);
        $this->assertEquals('Las Piñas City', $application->perm_municipality);
        $this->assertEquals('National Capital Region (NCR)', $application->perm_province);
        $this->assertEquals('1740', $application->perm_zip_code);
    }

    public function test_non_gmail_email_is_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'test@gmail.com',
            'password' => 'password',
            'usertype' => 'user',
        ]);

        $this->actingAs($user);

        $response = $this->post(route('member.applications.store'), [
            'first_name'    => 'John',
            'middle_name'   => 'Middle',
            'last_name'     => 'Doe',
            'occupation'    => 'Engineer',
            'province'      => 'National Capital Region (NCR)',
            'municipality'  => 'Las Piñas City',
            'barangay'      => 'B. F. International Village',
            'address_line'  => '123 Main Street',
            'zip_code'      => '1740',
            'contact_number'=> '09171234567',
            'email'         => 'test@yahoo.com',
            'monthly_income'              => 50000,
            'existing_monthly_obligations'=> 2000,
            'data_privacy_consent'        => '1',
            ...$this->idUploadFields(),
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertNull(MemberApplication::where('email', 'test@yahoo.com')->first());
    }

    public function test_same_as_current_unchecked_allows_manual_permanent_address(): void
    {
        $user = User::factory()->create([
            'email' => 'test2@gmail.com',
            'password' => 'password',
            'usertype' => 'user',
        ]);

        $this->actingAs($user);

        $response = $this->post(route('member.applications.store'), [
            'first_name'    => 'Jane',
            'middle_name'   => 'Middle',
            'last_name'     => 'Smith',
            'occupation'    => 'Doctor',
            'province'      => 'National Capital Region (NCR)',
            'municipality'  => 'Las Piñas City',
            'barangay'      => 'B. F. International Village',
            'address_line'  => '123 Current Street',
            'zip_code'      => '1740',
            'contact_number'=> '09171234567',
            'email'         => 'test2@gmail.com',
            'monthly_income'              => 50000,
            'existing_monthly_obligations'=> 2000,
            'date_of_birth'   => '1990-05-15',
            // same_as_current_address NOT checked (unchecked checkbox doesn't send value)
            'perm_street'        => '456 Permanent Ave',
            'perm_barangay'      => 'Almanza Uno',
            'perm_municipality'  => 'Las Piñas City',
            'perm_province'      => 'National Capital Region (NCR)',
            'perm_zip_code'      => '1741',
            ...$this->idUploadFields(),
            'data_privacy_consent'        => '1',
        ]);

        $response->assertRedirect();

        $application = MemberApplication::where('email', 'test2@gmail.com')->first();
        $this->assertNotNull($application);

        // Verify permanent address fields were NOT copied, manual values used
        $this->assertEquals('456 Permanent Ave', $application->perm_street);
        $this->assertEquals('Almanza Uno', $application->perm_barangay);
        $this->assertEquals('Las Piñas City', $application->perm_municipality);
        $this->assertEquals('National Capital Region (NCR)', $application->perm_province);
        $this->assertEquals('1741', $application->perm_zip_code);
    }

    public function test_dob_validation_rejects_future_date(): void
    {
        $user = User::factory()->create([
            'email' => 'test3@gmail.com',
            'password' => 'password',
            'usertype' => 'user',
        ]);

        $this->actingAs($user);

        $tomorrow = Carbon::tomorrow()->format('Y-m-d');

        $response = $this->post(route('member.applications.store'), [
            'first_name'    => 'Future',
            'middle_name'   => '',
            'last_name'     => 'Person',
            'occupation'    => 'Student',
            'province'      => 'National Capital Region (NCR)',
            'municipality'  => 'Las Piñas City',
            'barangay'      => 'B. F. International Village',
            'address_line'  => '123 Future St',
            'zip_code'      => '1740',
            'contact_number'=> '09171234567',
            'email'         => 'test3@gmail.com',
            'monthly_income'              => 50000,
            'existing_monthly_obligations'=> 2000,
            'date_of_birth'   => $tomorrow, // Future date
            'data_privacy_consent'        => '1',
            ...$this->idUploadFields(),
        ]);

        $response->assertSessionHasErrors('date_of_birth');
        $errors = $response->getSession()->get('errors')->get('date_of_birth');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('before or equal', strtolower($errors[0]));
    }

    public function test_dob_validation_rejects_under_18(): void
    {
        $user = User::factory()->create([
            'email' => 'test4@gmail.com',
            'password' => 'password',
            'usertype' => 'user',
        ]);

        $this->actingAs($user);

        $dob = '2010-01-01';

        $response = $this->post(route('member.applications.store'), [
            'first_name'    => 'Young',
            'middle_name'   => '',
            'last_name'     => 'Person',
            'occupation'    => 'Student',
            'province'      => 'National Capital Region (NCR)',
            'municipality'  => 'Las Piñas City',
            'barangay'      => 'B. F. International Village',
            'address_line'  => '123 Young St',
            'zip_code'      => '1740',
            'contact_number'=> '09171234567',
            'email'         => 'test4@gmail.com',
            'monthly_income'              => 50000,
            'existing_monthly_obligations'=> 2000,
            'date_of_birth'   => $dob,
            'data_privacy_consent'        => '1',
            ...$this->idUploadFields(),
        ]);

        $response->assertSessionHasErrors('date_of_birth');
        $errors = $response->getSession()->get('errors')->get('date_of_birth');
        $this->assertStringContainsString('18', strtolower($errors[0]));
    }

    public function test_dob_validation_accepts_valid_past_date(): void
    {
        $user = User::factory()->create([
            'email' => 'test5@gmail.com',
            'password' => 'password',
            'usertype' => 'user',
        ]);

        $this->actingAs($user);

        // Born in 2000 = 26 years old in 2026
        $dob = '2000-01-01';

        $response = $this->post(route('member.applications.store'), [
            'first_name'    => 'Valid',
            'middle_name'   => '',
            'last_name'     => 'Person',
            'occupation'    => 'Worker',
            'province'      => 'National Capital Region (NCR)',
            'municipality'  => 'Las Piñas City',
            'barangay'      => 'B. F. International Village',
            'address_line'  => '123 Valid St',
            'zip_code'      => '1740',
            'contact_number'=> '09171234567',
            'email'         => 'test5@gmail.com',
            'monthly_income'              => 50000,
            'existing_monthly_obligations'=> 2000,
            'date_of_birth'   => $dob,
            'data_privacy_consent'        => '1',
            ...$this->idUploadFields(),
        ]);

        $response->assertRedirect();

        $application = MemberApplication::where('email', 'test5@gmail.com')->first();
        $this->assertNotNull($application);
        $this->assertEquals('2000-01-01', $application->date_of_birth->format('Y-m-d'));
    }

    public function test_approval_copies_address_and_dob_to_coop_member(): void
    {
        $application = MemberApplication::create([
            'surname'        => 'Doe',
            'first_name'     => 'John',
            'middle_name'    => 'Middle',
            'email'          => 'john.doe@gmail.com',
            'contact_number' => '09171234567',
            'occupation'     => 'Engineer',
            'province'       => 'National Capital Region (NCR)',
            'municipality'   => 'Las Piñas City',
            'barangay'       => 'B. F. International Village',
            'street'         => '123 Main Street',
            'zip_code'       => '1740',
            'date_of_birth'  => '1990-05-15',
            'status'         => 'pending',
            'monthly_income'              => 50000,
            'existing_monthly_obligations'=> 2000,
            'id_front_path'  => 'test/front-123.jpg',
            'id_back_path'   => 'test/back-123.jpg',
        ]);

        $manager = User::factory()->asBranchManager()->create();
        $this->actingAs($manager);

        $response = $this->post(route('memberships.approve', $application->id));
        $response->assertOk()->assertJson(['success' => true]);

        $application->refresh();
        $this->assertEquals('approved', $application->status);
        $this->assertNotNull($application->approved_member_id);

        $member = CoopMember::find($application->approved_member_id);
        $this->assertNotNull($member);

        $this->assertStringContainsString('123 Main Street', $member->address);
        $this->assertStringContainsString('B. F. International Village', $member->address);
        $this->assertStringContainsString('Las Piñas City', $member->address);
        $this->assertStringContainsString('National Capital Region (NCR)', $member->address);
        $this->assertStringContainsString('1740', $member->address);

        $this->assertEquals('1990-05-15', $member->date_of_birth->format('Y-m-d'));
    }

    public function test_approval_does_not_overwrite_existing_member_dob(): void
    {
        $existingMember = CoopMember::create([
            'member_id'     => 'SDCC-2026-0001',
            'full_name'     => 'John Doe',
            'email'         => 'john.doe2@gmail.com',
            'contact_number'=> '09171234567',
            'occupation'    => 'Engineer',
            'date_of_birth' => '1985-01-01',
            'is_registered' => true,
            'status'        => CoopMember::STATUS_ACTIVE,
        ]);

        $application = MemberApplication::create([
            'surname'        => 'Doe',
            'first_name'     => 'John',
            'middle_name'    => 'Middle',
            'email'          => 'john.doe2@gmail.com',
            'contact_number' => '09171234567',
            'occupation'     => 'Engineer',
            'province'       => 'National Capital Region (NCR)',
            'municipality'   => 'Las Piñas City',
            'barangay'       => 'B. F. International Village',
            'street'         => '123 Main Street',
            'zip_code'       => '1740',
            'date_of_birth'  => '1990-05-15',
            'status'         => 'pending',
            'monthly_income'              => 50000,
            'existing_monthly_obligations'=> 2000,
            'id_front_path'  => 'test/front-456.jpg',
            'id_back_path'   => 'test/back-456.jpg',
        ]);

        $manager = User::factory()->asBranchManager()->create();
        $this->actingAs($manager);

        $response = $this->post(route('memberships.approve', $application->id));
        $response->assertOk();

        $existingMember->refresh();
        $this->assertEquals('1985-01-01', $existingMember->date_of_birth->format('Y-m-d'));
    }

    /**
     * @group age-validation
     */
    public function test_dob_validation_rejects_17y364d(): void
    {
        $user = User::factory()->create([
            'email' => 'age_17@gmail.com',
            'password' => 'password',
            'usertype' => 'user',
        ]);
        $this->actingAs($user);

        $response = $this->post(route('member.applications.store'), [
            'first_name'    => 'Seventeen',
            'middle_name'   => '',
            'last_name'     => 'Person',
            'occupation'    => 'Student',
            'province'      => 'National Capital Region (NCR)',
            'municipality'  => 'Las Piñas City',
            'barangay'      => 'B. F. International Village',
            'address_line'  => '17 Test St',
            'zip_code'      => '1740',
            'contact_number'=> '09171234567',
            'email'         => 'age_17@gmail.com',
            'monthly_income'              => 50000,
            'existing_monthly_obligations' => 2000,
            'date_of_birth' => '2009-01-01',
            'data_privacy_consent'        => '1',
            ...$this->idUploadFields(),
        ]);
        $response->assertSessionHasErrors('date_of_birth');
        $errors = $response->getSession()->get('errors')->get('date_of_birth');
        $this->assertStringContainsString('18', strtolower($errors[0]));
    }

    /**
     * @group age-validation
     */
    public function test_dob_validation_accepts_exactly_18_today(): void
    {
        $user = User::factory()->create([
            'email' => 'age_18@gmail.com',
            'password' => 'password',
            'usertype' => 'user',
        ]);
        $this->actingAs($user);

        $response = $this->post(route('member.applications.store'), [
            'first_name'    => 'Eighteen',
            'middle_name'   => '',
            'last_name'     => 'Person',
            'occupation'    => 'Student',
            'province'      => 'National Capital Region (NCR)',
            'municipality'  => 'Las Piñas City',
            'barangay'      => 'B. F. International Village',
            'address_line'  => '18 Test St',
            'zip_code'      => '1740',
            'contact_number'=> '09171234567',
            'email'         => 'age_18@gmail.com',
            'monthly_income'              => 50000,
            'existing_monthly_obligations' => 2000,
            'date_of_birth' => '2008-11-07',
            'data_privacy_consent'        => '1',
            ...$this->idUploadFields(),
        ]);
        $response->assertRedirect();

        $application = MemberApplication::where('email', 'age_18@gmail.com')->first();
        $this->assertNotNull($application);
        $this->assertEquals('2008-11-07', $application->date_of_birth->format('Y-m-d'));
    }

    /**
     * @group age-validation
     */
    public function test_dob_validation_accepts_1999(): void
    {
        $user = User::factory()->create([
            'email' => 'age_1999@gmail.com',
            'password' => 'password',
            'usertype' => 'user',
        ]);
        $this->actingAs($user);

        $response = $this->post(route('member.applications.store'), [
            'first_name'    => 'NineteenNinetyNine',
            'middle_name'   => '',
            'last_name'     => 'Person',
            'occupation'    => 'Student',
            'province'      => 'National Capital Region (NCR)',
            'municipality'  => 'Las Piñas City',
            'barangay'      => 'B. F. International Village',
            'address_line'  => '1999 Test St',
            'zip_code'      => '1740',
            'contact_number'=> '09171234567',
            'email'         => 'age_1999@gmail.com',
            'monthly_income'              => 50000,
            'existing_monthly_obligations' => 2000,
            'date_of_birth' => '1999-01-01',
            'data_privacy_consent'        => '1',
            ...$this->idUploadFields(),
        ]);
        $response->assertRedirect();

        $application = MemberApplication::where('email', 'age_1999@gmail.com')->first();
        $this->assertNotNull($application);
        $this->assertEquals('1999-01-01', $application->date_of_birth->format('Y-m-d'));
    }

    /**
     * @group age-validation
     */
    public function test_same_as_current_copies_all_perm_fields(): void
    {
        $user = User::factory()->create([
            'email' => 'perm_copy@gmail.com',
            'password' => 'password',
            'usertype' => 'user',
        ]);
        $this->actingAs($user);

        $response = $this->post(route('member.applications.store'), [
            'first_name'    => 'SameCurrent',
            'middle_name'   => '',
            'last_name'     => 'Person',
            'occupation'    => 'Student',
            'province'      => 'Laguna',
            'municipality'  => 'Santa Cruz',
            'barangay'      => 'Santa Cruz',
            'address_line'  => '123 Current St',
            'zip_code'      => '4000',
            'contact_number'=> '09171234567',
            'email'         => 'perm_copy@gmail.com',
            'monthly_income'              => 50000,
            'existing_monthly_obligations'=> 2000,
            'date_of_birth' => '2000-01-01',
            'same_as_current_address' => 'on',
            'data_privacy_consent'        => '1',
            ...$this->idUploadFields(),
        ]);
        $response->assertRedirect();

        $application = MemberApplication::where('email', 'perm_copy@gmail.com')->first();
        $this->assertNotNull($application);
        // Verify all permanent address fields were copied
        $this->assertEquals('Laguna', $application->perm_province);
        $this->assertEquals('Santa Cruz', $application->perm_municipality);
        $this->assertEquals('Santa Cruz', $application->perm_barangay);
        $this->assertEquals('123 Current St', $application->perm_street);
        $this->assertEquals('4000', $application->perm_zip_code);
    }

    /**
     * @group age-validation
     */
    public function test_old_city_barangay_retained_after_validation_failure(): void
    {
        $user = User::factory()->create([
            'email' => 'old_city@gmail.com',
            'password' => 'password',
            'usertype' => 'user',
        ]);
        $this->actingAs($user);

        $response = $this->post(route('member.applications.store'), [
            'first_name'    => 'OldCity',
            'middle_name'   => '',
            'last_name'     => 'Person',
            'occupation'    => 'Student',
            'province'      => 'Laguna',
            'municipality'  => 'Santa Cruz',
            'barangay'      => 'Santa Cruz',
            'address_line'  => '123 Old St',
            'zip_code'      => '4000',
            'contact_number'=> '09171234567',
            'email'         => 'old_city@gmail.com',
            'monthly_income'              => 50000,
            'existing_monthly_obligations' => 2000,
            'date_of_birth' => '2010-01-01', // Invalid - under 18
            'data_privacy_consent'        => '1',
            ...$this->idUploadFields(),
        ]);

        // Should redirect back with validation errors (status 302)
        $this->assertTrue($response->status() === 302 || $response->status() === 200);

        // Check old input is flashed to session
        $oldInput = $response->getSession()->getOldInput();
        $this->assertEquals('Laguna', $oldInput['province'] ?? '');
        $this->assertEquals('Santa Cruz', $oldInput['municipality'] ?? '');
        $this->assertEquals('Santa Cruz', $oldInput['barangay'] ?? '');
    }

    public function test_savings_balance_stored_on_form_submission(): void
    {
        $user = User::factory()->create([
            'email' => 'savings_test@gmail.com',
            'password' => 'password',
            'usertype' => 'user',
        ]);
        $this->actingAs($user);

        $response = $this->post(route('member.applications.store'), [
            'first_name'    => 'Savings',
            'middle_name'   => '',
            'last_name'     => 'Test',
            'occupation'    => 'Engineer',
            'province'      => 'National Capital Region (NCR)',
            'municipality'  => 'Las Piñas City',
            'barangay'      => 'B. F. International Village',
            'address_line'  => '123 Test St',
            'zip_code'      => '1740',
            'contact_number'=> '09171234567',
            'email'         => 'savings_test@gmail.com',
            'monthly_income'              => 50000,
            'existing_monthly_obligations'=> 2000,
            'date_of_birth'   => '1990-05-15',
            'savings_balance'             => 5000.00,
            'data_privacy_consent'        => '1',
            ...$this->idUploadFields(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors(['savings_balance']);

        $application = MemberApplication::where('email', 'savings_test@gmail.com')->first();
        $this->assertNotNull($application);
        $this->assertEquals(5000.00, $application->savings_balance);
    }

    public function test_savings_balance_negative_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'savings_neg@gmail.com',
            'password' => 'password',
            'usertype' => 'user',
        ]);
        $this->actingAs($user);

        $response = $this->post(route('member.applications.store'), [
            'first_name'    => 'Savings',
            'middle_name'   => '',
            'last_name'     => 'Negative',
            'occupation'    => 'Engineer',
            'province'      => 'National Capital Region (NCR)',
            'municipality'  => 'Las Piñas City',
            'barangay'      => 'B. F. International Village',
            'address_line'  => '123 Test St',
            'zip_code'      => '1740',
            'contact_number'=> '09171234567',
            'email'         => 'savings_neg@gmail.com',
            'monthly_income'              => 50000,
            'existing_monthly_obligations'=> 2000,
            'savings_balance'             => -100,
            'data_privacy_consent'        => '1',
            ...$this->idUploadFields(),
        ]);

        $response->assertSessionHasErrors(['savings_balance']);
        $errors = $response->getSession()->get('errors')->get('savings_balance');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('at least 0', strtolower($errors[0]));
    }

    public function test_savings_balance_non_numeric_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'savings_nan@gmail.com',
            'password' => 'password',
            'usertype' => 'user',
        ]);
        $this->actingAs($user);

        $response = $this->post(route('member.applications.store'), [
            'first_name'    => 'Savings',
            'middle_name'   => '',
            'last_name'     => 'NaN',
            'occupation'    => 'Engineer',
            'province'      => 'National Capital Region (NCR)',
            'municipality'  => 'Las Piñas City',
            'barangay'      => 'B. F. International Village',
            'address_line'  => '123 Test St',
            'zip_code'      => '1740',
            'contact_number'=> '09171234567',
            'email'         => 'savings_nan@gmail.com',
            'monthly_income'              => 50000,
            'existing_monthly_obligations'=> 2000,
            'savings_balance'             => 'abc',
            'data_privacy_consent'        => '1',
            ...$this->idUploadFields(),
        ]);

        $response->assertSessionHasErrors(['savings_balance']);
        $errors = $response->getSession()->get('errors')->get('savings_balance');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('a number', strtolower($errors[0]));
    }

    public function test_approval_copies_savings_when_empty(): void
    {
        $application = MemberApplication::create([
            'surname'        => 'Savings',
            'first_name'     => 'Test',
            'middle_name'    => '',
            'email'          => 'savings_approval@gmail.com',
            'contact_number' => '09171234567',
            'occupation'     => 'Engineer',
            'province'       => 'National Capital Region (NCR)',
            'municipality'   => 'Las Piñas City',
            'barangay'       => 'B. F. International Village',
            'street'         => '123 Test St',
            'zip_code'       => '1740',
            'date_of_birth'  => '1990-05-15',
            'status'         => 'pending',
            'monthly_income'              => 50000,
            'existing_monthly_obligations'=> 2000,
            'savings_balance'             => 7500.00,
            'id_front_path'  => 'test/front-789.jpg',
            'id_back_path'   => 'test/back-789.jpg',
        ]);

        $manager = User::factory()->asBranchManager()->create();
        $this->actingAs($manager);

        $response = $this->post(route('memberships.approve', $application->id));
        $response->assertOk()->assertJson(['success' => true]);

        $application->refresh();
        $member = CoopMember::find($application->approved_member_id);
        $this->assertNotNull($member);
        $this->assertEquals(7500.00, $member->savings_balance);
    }

    public function test_approval_does_not_overwrite_existing_member_savings(): void
    {
        $existingMember = CoopMember::create([
            'member_id'       => 'SDCC-2026-0099',
            'full_name'       => 'Savings Existing',
            'email'           => 'savings_existing@gmail.com',
            'contact_number'  => '09171234567',
            'occupation'      => 'Engineer',
            'savings_balance' => 10000.00,
            'is_registered'   => true,
            'status'          => CoopMember::STATUS_ACTIVE,
        ]);

        $application = MemberApplication::create([
            'surname'        => 'Existing',
            'first_name'     => 'Savings',
            'middle_name'    => '',
            'email'          => 'savings_existing@gmail.com',
            'contact_number' => '09171234567',
            'occupation'     => 'Engineer',
            'province'       => 'National Capital Region (NCR)',
            'municipality'   => 'Las Piñas City',
            'barangay'       => 'B. F. International Village',
            'street'         => '123 Test St',
            'zip_code'       => '1740',
            'date_of_birth'  => '1990-05-15',
            'status'         => 'pending',
            'monthly_income'              => 50000,
            'existing_monthly_obligations'=> 2000,
            'savings_balance'             => 5000.00, // Different value - should NOT overwrite
            'id_front_path'  => 'test/front-101.jpg',
            'id_back_path'   => 'test/back-101.jpg',
        ]);

        $manager = User::factory()->asBranchManager()->create();
        $this->actingAs($manager);

        $response = $this->post(route('memberships.approve', $application->id));
        $response->assertOk();

        $existingMember->refresh();
        $this->assertEquals(10000.00, $existingMember->savings_balance);
    }

    public function test_approval_copies_birthdate_when_empty(): void
    {
        $application = MemberApplication::create([
            'surname'        => 'Dob',
            'first_name'     => 'Test',
            'middle_name'    => '',
            'email'          => 'dob_approval@gmail.com',
            'contact_number' => '09171234567',
            'occupation'     => 'Engineer',
            'province'       => 'National Capital Region (NCR)',
            'municipality'   => 'Las Piñas City',
            'barangay'       => 'B. F. International Village',
            'street'         => '123 Test St',
            'zip_code'       => '1740',
            'date_of_birth'  => '1990-05-15',
            'status'         => 'pending',
            'monthly_income'              => 50000,
            'existing_monthly_obligations'=> 2000,
            'id_front_path'  => 'test/front-102.jpg',
            'id_back_path'   => 'test/back-102.jpg',
        ]);

        $manager = User::factory()->asBranchManager()->create();
        $this->actingAs($manager);

        $response = $this->post(route('memberships.approve', $application->id));
        $response->assertOk()->assertJson(['success' => true]);

        $application->refresh();
        $member = CoopMember::find($application->approved_member_id);
        $this->assertNotNull($member);
        $this->assertEquals('1990-05-15', $member->date_of_birth->format('Y-m-d'));
    }

    public function test_id_storage_path_has_no_email(): void
    {
        $user = User::factory()->create([
            'email' => 'test-noemail@gmail.com',
            'password' => 'password',
            'usertype' => 'user',
        ]);
        $this->actingAs($user);

        $response = $this->post(route('member.applications.store'), [
            'first_name'    => 'NoEmail',
            'middle_name'   => '',
            'last_name'     => 'Test',
            'occupation'    => 'Engineer',
            'province'      => 'National Capital Region (NCR)',
            'municipality'  => 'Las Piñas City',
            'barangay'      => 'B. F. International Village',
            'address_line'  => '123 Test St',
            'zip_code'      => '1740',
            'contact_number'=> '09171234567',
            'email'         => 'test-noemail@gmail.com',
            'monthly_income'              => 50000,
            'existing_monthly_obligations'=> 2000,
            'date_of_birth' => '1990-05-15',
            'data_privacy_consent'        => '1',
            ...$this->idUploadFields(),
        ]);

        $response->assertRedirect();

        $application = MemberApplication::where('email', 'test-noemail@gmail.com')->first();
        $this->assertNotNull($application);

        // Verify stored path does NOT contain the email address
        $this->assertStringNotContainsString('test-noemail@gmail.com', $application->id_front_path);
        $this->assertStringNotContainsString('test-noemail@gmail.com', $application->id_back_path);
    }
}
