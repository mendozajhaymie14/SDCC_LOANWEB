<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\CoopMember;
use App\Models\MemberApplication;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Membership approval workflow via Branch Manager.
 *
 * When approved: coop member linked, usertype flipped to 'member',
 * STATUS_ACTIVE, audit logged, welcome notification sent.
 * When rejected: applicant stays 'user', may reapply.
 */
class MembershipApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $applicant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->applicant = User::factory()->create([
            'name'     => 'Jane Doe',
            'email'    => 'jane@gmail.com',
            'password' => 'secret',
            'usertype' => Permissions::ROLE_USER,
        ]);
    }

    public function test_approval_flips_usertype_links_coop_member_sets_active_status(): void
    {
        $this->actingAs($this->applicant);

        $application = MemberApplication::create([
            'surname'       => 'Doe',
            'first_name'    => 'Jane',
            'email'         => 'jane@gmail.com',
            'contact_number'=> '09171234567',
            'status'        => 'pending',
        ]);

        $this->assertDatabaseHas('member_applications', ['id' => $application->id, 'status' => 'pending']);
        $this->assertEquals(Permissions::ROLE_USER, $this->applicant->usertype);

        // Branch Manager approves.
        $manager = User::factory()->asBranchManager()->create();
        $this->actingAs($manager);

        $response = $this->post(route('memberships.approve', $application->id), [
            '_token' => csrf_token(),
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $application->refresh();
        $this->assertEquals('approved', $application->status);
        $this->assertNotNull($application->reviewed_at);
        $this->assertEquals($manager->id, $application->reviewed_by);

        $user = User::find($this->applicant->id);
        $this->assertEquals(Permissions::ROLE_MEMBER, $user->usertype);
        $this->assertNotNull($user->coop_member_id);

        $member = $user->coopMember()->first();
        $this->assertNotNull($member);
        $this->assertEquals('active', $member->status);
        $this->assertTrue($member->is_registered);

        $this->assertGreaterThan(0, AuditLog::where('user_id', $user->id)->count());

        $this->assertGreaterThan(0, AppNotification::where('user_id', $user->id)
            ->where('type', AppNotification::TYPE_MEMBERSHIP_APPROVED)->count());
    }

    public function test_loan_officer_can_approve_membership_application(): void
    {
        $application = MemberApplication::create([
            'surname'       => 'Doe',
            'first_name'    => 'Jane',
            'email'         => 'jane@gmail.com',
            'contact_number'=> '09171234567',
            'status'        => 'pending',
        ]);

        // Loan Officer holds APPROVE_MEMBERSHIPS alongside Branch Manager.
        $officer = User::factory()->create(['usertype' => Permissions::ROLE_LOAN_OFFICER]);
        $this->actingAs($officer);

        $response = $this->post(route('memberships.approve', $application->id), [
            '_token' => csrf_token(),
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $application->refresh();
        $this->assertEquals('approved', $application->status);
        $this->assertNotNull($application->reviewed_at);
        $this->assertEquals($officer->id, $application->reviewed_by);
    }

    public function test_credit_investigator_cannot_approve_membership_application(): void
    {
        $application = MemberApplication::create([
            'surname'       => 'Doe',
            'first_name'    => 'Jane',
            'email'         => 'jane@gmail.com',
            'contact_number'=> '09171234567',
            'status'        => 'pending',
        ]);

        // Credit Investigator may encode applications but not decide memberships.
        $investigator = User::factory()->create(['usertype' => Permissions::ROLE_CREDIT_INVESTIGATOR]);
        $this->actingAs($investigator);

        $response = $this->post(route('memberships.approve', $application->id), [
            '_token' => csrf_token(),
        ]);

        $response->assertForbidden();

        // State untouched.
        $application->refresh();
        $this->assertEquals('pending', $application->status);
    }

    public function test_rejection_keeps_usertype_as_user(): void
    {
        $application = MemberApplication::create([
            'surname'       => 'Doe',
            'first_name'    => 'Jane',
            'email'         => 'jane@gmail.com',
            'contact_number'=> '09171234567',
            'status'        => 'pending',
        ]);

        $manager = User::factory()->asBranchManager()->create();
        $this->actingAs($manager);

        $response = $this->post(route('memberships.reject', $application->id), [
            '_token' => csrf_token(),
        ]);

        $response->assertOk()->assertJson(['success' => true, 'status' => 'rejected']);

        $application->refresh();
        $this->assertEquals('rejected', $application->status);

        $user = User::find($this->applicant->id);
        $this->assertEquals(Permissions::ROLE_USER, $user->usertype);
        $this->assertNull($user->coop_member_id);

        $this->assertGreaterThan(0, AuditLog::where('user_id', $user->id)->count());

        $this->assertGreaterThan(0, AppNotification::where('user_id', $user->id)
            ->where('type', AppNotification::TYPE_MEMBERSHIP_REJECTED)->count());
    }

    /**
     * The Active Members page counts by is_registered, not just status.
     * An approved application whose member row was created without a linked
     * account (is_registered=false) must be flipped on approval.
     */
    public function test_approval_makes_member_active_and_counted_on_active_members_page(): void
    {
        $application = MemberApplication::create([
            'surname'        => 'Doe',
            'first_name'     => 'Jane',
            'email'         => 'jane@gmail.com',
            'contact_number'=> '09171234567',
            'status'        => 'pending',
        ]);

        $manager = User::factory()->asBranchManager()->create();
        $this->actingAs($manager);

        $this->post(route('memberships.approve', $application->id), [
            '_token' => csrf_token(),
        ])->assertOk()->assertJson(['success' => true]);

        $member = CoopMember::where('email', 'jane@gmail.com')->first();
        $this->assertNotNull($member);
        $this->assertEquals(CoopMember::STATUS_ACTIVE, $member->status);
        $this->assertTrue($member->is_registered);

        // The Active Members page filters status=active and counts is_registered.
        $response = $this->get(route('active-members.index'));
        $response->assertOk();
        $response->assertSee('Approved Members: 1');
        $response->assertSee($member->member_id);
        $response->assertSee('Approved');
    }

    public function test_rejection_deactivates_linked_member_row(): void
    {
        $application = MemberApplication::create([
            'surname'        => 'Doe',
            'first_name'     => 'Jane',
            'email'         => 'jane@gmail.com',
            'contact_number'=> '09171234567',
            'status'        => 'pending',
        ]);

        $manager = User::factory()->asBranchManager()->create();
        $this->actingAs($manager);

        // Approve first so a member row exists, then reject it.
        $this->post(route('memberships.approve', $application->id), [
            '_token' => csrf_token(),
        ]);

        $member = CoopMember::where('email', 'jane@gmail.com')->first();
        $this->assertNotNull($member);
        $this->assertEquals(CoopMember::STATUS_ACTIVE, $member->status);

        $this->post(route('memberships.reject', $application->id), [
            '_token' => csrf_token(),
        ])->assertOk()->assertJson(['success' => true, 'status' => 'rejected']);

        $member->refresh();
        $this->assertEquals(CoopMember::STATUS_PENDING, $member->status);
        $this->assertFalse($member->is_registered);

        // The member is no longer counted on the Active Members page.
        $this->get(route('active-members.index'))
            ->assertSee('Approved Members: 0');
    }

    public function test_approving_twice_is_idempotent(): void
    {
        $application = MemberApplication::create([
            'surname'        => 'Doe',
            'first_name'     => 'Jane',
            'email'         => 'jane@gmail.com',
            'contact_number'=> '09171234567',
            'status'        => 'pending',
        ]);

        $manager = User::factory()->asBranchManager()->create();
        $this->actingAs($manager);

        $this->post(route('memberships.approve', $application->id), ['_token' => csrf_token()])->assertOk();
        $first = CoopMember::where('email', 'jane@gmail.com')->first();
        $firstId = $first->id;

        // Second approval must not create a duplicate member row.
        $this->post(route('memberships.approve', $application->id), ['_token' => csrf_token()])->assertOk();
        $this->assertDatabaseCount('coop_members', 1);
        $this->assertNotNull(CoopMember::find($firstId));
    }

    public function test_sync_approved_dry_run_changes_nothing_and_real_run_fixes_stale_row(): void
    {
        // Approved application with a stale (pending) member row.
        $application = MemberApplication::create([
            'surname'        => 'Doe',
            'first_name'     => 'Jane',
            'email'         => 'jane@gmail.com',
            'contact_number'=> '09171234567',
            'status'        => 'approved',
        ]);

        $staleMember = CoopMember::create([
            'member_id'      => 'SDCC-2026-0099',
            'full_name'      => 'Jane Doe',
            'email'         => 'jane@gmail.com',
            'contact_number' => '09171234567',
            'is_registered'  => false,
            'status'         => CoopMember::STATUS_PENDING,
        ]);
        $application->approved_member_id = $staleMember->id;
        $application->save();

        // Dry run: nothing changes.
        Artisan::call('members:sync-approved', ['--dry-run' => true]);
        $staleMember->refresh();
        $this->assertEquals(CoopMember::STATUS_PENDING, $staleMember->status);
        $this->assertFalse($staleMember->is_registered);

        // Real run: fixes the stale row.
        Artisan::call('members:sync-approved');
        $staleMember->refresh();
        $this->assertEquals(CoopMember::STATUS_ACTIVE, $staleMember->status);
        $this->assertTrue($staleMember->is_registered);
    }

    public function test_only_one_pending_application_per_user(): void
    {
        $this->actingAs($this->applicant);

        MemberApplication::create([
            'surname'       => 'Doe',
            'first_name'    => 'Jane',
            'email'         => 'jane@gmail.com',
            'contact_number'=> '09171234567',
            'status'        => 'pending',
        ]);

        $response = $this->post(route('member.applications.store'), [
            '_token'             => csrf_token(),
            'last_name'          => 'Doe',
            'first_name'         => 'Jane',
            'email'              => 'jane@gmail.com',
            'contact_number'     => '09171234567',
            'data_privacy_consent' => true,
        ]);

        $this->assertNotNull(session('errors')->get('email')[0]);
        $this->assertSame('You already have a membership application under review. Please wait for the result.',
            session('errors')->get('email')[0]);
    }

    public function test_approved_user_cannot_reapply(): void
    {
        // First approve.
        $application = MemberApplication::create([
            'surname'       => 'Doe',
            'first_name'    => 'Jane',
            'email'         => 'jane@gmail.com',
            'contact_number'=> '09171234567',
            'status'        => 'pending',
        ]);

        $manager = User::factory()->asBranchManager()->create();
        $this->actingAs($manager);
        $this->post(route('memberships.approve', $application->id));

        // The applicant is a cooperative member now, so the membership form
        // is closed to them: only a 'user' may apply for membership.
        $this->actingAs(User::find($this->applicant->id));

        $this->post(route('member.applications.store'), [
            '_token'             => csrf_token(),
            'last_name'          => 'Doe',
            'first_name'         => 'Jane',
            'email'              => 'jane@gmail.com',
            'contact_number'     => '09171234567',
            'data_privacy_consent' => true,
        ])->assertForbidden();
    }

    public function test_an_already_approved_application_blocks_a_later_application(): void
    {
        // Approved while nobody was registered yet, so no account was linked.
        MemberApplication::create([
            'surname'       => 'Doe',
            'first_name'    => 'Jane',
            'email'         => 'jane@gmail.com',
            'contact_number'=> '09171234567',
            'status'        => 'approved',
        ]);

        $this->actingAs($this->applicant);

        $this->post(route('member.applications.store'), [
            '_token'             => csrf_token(),
            'last_name'          => 'Doe',
            'first_name'         => 'Jane',
            'email'              => 'jane@gmail.com',
            'contact_number'     => '09171234567',
            'data_privacy_consent' => true,
        ]);

        $this->assertNotNull(session('errors')->get('email')[0]);
        $this->assertSame('Your membership application has already been approved.',
            session('errors')->get('email')[0]);
    }

    /** @test */
    public function membership_form_renders_only_allowed_fields(): void
    {
        $response = $this->get(route('member.applications.create'));
        $response->assertOk();
        $response->assertSee('occupation');          // new required field
        $response->assertSee('last_name');
        $response->assertSee('first_name');
        $response->assertSee('middle_name');
        $response->assertSee('contact_number');
        $response->assertSee('email');
        $response->assertSee('province');
        $response->assertSee('municipality');
        $response->assertSee('barangay');
        $response->assertSee('address_line');
        $response->assertSee('zip_code');
    }

    /** @test */
    public function membership_form_does_not_render_removed_fields(): void
    {
        $response = $this->get(route('member.applications.create'));
        $response->assertOk();
        // Use name= attributes to avoid substring false-positives (e.g. "tin" in "text-muted")
        $response->assertDontSee('name="birthdate"');
        $response->assertDontSee('name="tin"');
        $response->assertDontSee('name="suffix"');
        $response->assertDontSee('name="sex"');
        $response->assertDontSee('name="civil_status"');
        $response->assertDontSee('name="country_of_birth"');
        $response->assertDontSee('name="city_of_birth"');
        $response->assertDontSee('name="id_picture"');
        $response->assertDontSee('name="proof_of_billing"');
        $response->assertDontSee('name="character_references"');
        $response->assertDontSee('name="gender"');
    }

    /** @test */
    public function approved_application_birthdate_is_saved_and_shown_on_active_members_edit(): void
    {
        $application = MemberApplication::create([
            'surname'        => 'Doe',
            'first_name'     => 'Jane',
            'email'          => 'jane@gmail.com',
            'contact_number' => '09171234567',
            'status'         => 'pending',
            'date_of_birth'  => '1999-11-20',
        ]);

        $manager = User::factory()->asBranchManager()->create();
        $this->actingAs($manager);

        $this->post(route('memberships.approve', $application->id), [
            '_token' => csrf_token(),
        ])->assertOk()->assertJson(['success' => true]);

        // date_of_birth is persisted on the CoopMember row.
        $member = CoopMember::where('email', 'jane@gmail.com')->first();
        $this->assertNotNull($member);
        $this->assertEquals('1999-11-20', $member->date_of_birth->format('Y-m-d'));

        // The Active Members page renders it as Y-m-d in the data attribute
        // so <input type="date"> can consume it.
        $response = $this->get(route('active-members.index'));
        $response->assertOk();
        $response->assertSee('data-date-of-birth="1999-11-20"', false);
    }

    /** @test */
    public function occupation_is_required_on_membership_application(): void
    {
        $response = $this->post(route('member.applications.store'), [
            '_token'             => csrf_token(),
            'last_name'          => 'Doe',
            'first_name'         => 'Jane',
            'email'             => 'jane@gmail.com',
            'contact_number'    => '09171234567',
            'data_privacy_consent' => true,
            // occupation omitted
        ]);

        $response->assertSessionHasErrors(['occupation']);
    }

    /** @test */
    public function valid_membership_application_stores_occupation_and_copies_to_member_on_approval(): void
    {
        $this->actingAs($this->applicant);

        $response = $this->post(route('member.applications.store'), [
            '_token'             => csrf_token(),
            'last_name'          => 'Doe',
            'first_name'         => 'Jane',
            'email'              => 'jane@gmail.com',
            'contact_number'     => '09171234567',
            'data_privacy_consent' => true,
            'occupation'         => 'Teacher',
            'address_line'       => '123 Main St',
            'barangay'           => 'Poblacion',
            'municipality'       => 'San Dionisio',
            'province'           => 'Parañaque',
            'zip_code'           => '1700',
        ]);

        $response->assertRedirect();
        $application = MemberApplication::where('email', 'jane@gmail.com')->first();
        $this->assertNotNull($application);
        $this->assertSame('Teacher', $application->occupation);

        // Approve and verify occupation copies to CoopMember.
        $manager = User::factory()->asBranchManager()->create();
        $this->actingAs($manager);
        $this->post(route('memberships.approve', $application->id), [
            '_token' => csrf_token(),
        ]);

        $user = User::find($this->applicant->id);
        $member = $user->coopMember()->first();
        $this->assertNotNull($member);
        $this->assertSame('Teacher', $member->occupation);
    }
}