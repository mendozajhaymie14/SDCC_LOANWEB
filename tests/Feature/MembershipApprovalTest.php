<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\MemberApplication;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'birthdate'     => now()->subYears(30)->format('Y-m-d'),
            'status'        => 'pending',
        ]);

        $this->assertDatabaseHas('member_applications', ['id' => $application->id, 'status' => 'pending']);
        $this->assertEquals(Permissions::ROLE_USER, $this->applicant->usertype);

        // Branch Manager approves.
        $manager = User::factory()->asBranchManager()->create();
        $this->actingAs($manager);

        $response = $this->post(route('memberships.approve', $application->id));

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

        $response = $this->post(route('memberships.reject', $application->id));

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
            'last_name'     => 'Doe',
            'first_name'    => 'Jane',
            'email'         => 'jane@gmail.com',
            'contact_number'=> '09171234567',
            'sex'           => 'female',
            'civil_status'  => 'single',
            'birthdate'     => now()->subYears(30)->format('Y-m-d'),
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
            'last_name'     => 'Doe',
            'first_name'    => 'Jane',
            'email'         => 'jane@gmail.com',
            'contact_number'=> '09171234567',
            'sex'           => 'female',
            'civil_status'  => 'single',
            'birthdate'     => now()->subYears(30)->format('Y-m-d'),
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
            'last_name'     => 'Doe',
            'first_name'    => 'Jane',
            'email'         => 'jane@gmail.com',
            'contact_number'=> '09171234567',
            'sex'           => 'female',
            'civil_status'  => 'single',
            'birthdate'     => now()->subYears(30)->format('Y-m-d'),
        ]);

        $this->assertNotNull(session('errors')->get('email')[0]);
        $this->assertSame('Your membership application has already been approved.',
            session('errors')->get('email')[0]);
    }
}