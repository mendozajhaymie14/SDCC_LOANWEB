<?php

namespace Tests\Feature;

use App\Models\CoopMember;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The canonical cooperative member status.
 *
 * 'active' is the one value the application writes and filters on; the old
 * spellings ('Active', 'Approved', 'approved') are folded onto it by the data
 * migration. The dashboard's Active Members KPI counts exactly that.
 */
class MemberStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_is_the_canonical_status(): void
    {
        $this->assertSame('active', CoopMember::STATUS_ACTIVE);
        $this->assertContains(CoopMember::STATUS_ACTIVE, CoopMember::STATUSES);
    }

    /** An approved member is 'active' and counted in the Active Members KPI. */
    public function test_approved_member_is_active_and_counted(): void
    {
        $manager = User::factory()->asBranchManager()->create();

        // A pending membership application, approved by the branch manager.
        $application = \App\Models\MemberApplication::create([
            'surname'        => 'Dela Cruz',
            'first_name'     => 'Juan',
            'email'          => 'juan@gmail.com',
            'contact_number' => '09171234567',
            'status'         => 'pending',
        ]);

        $this->actingAs($manager)
            ->post(route('memberships.approve', $application->id))
            ->assertOk();

        $member = CoopMember::where('email', 'juan@gmail.com')->first();

        $this->assertNotNull($member);
        $this->assertSame(CoopMember::STATUS_ACTIVE, $member->status);
        $this->assertTrue($member->isActiveMember());

        // The KPI counts members whose status is the canonical one.
        $this->assertSame(1, CoopMember::where('status', CoopMember::STATUS_ACTIVE)->count());
    }

    /** A non-active member is not counted. */
    public function test_pending_member_is_not_counted_as_active(): void
    {
        CoopMember::factory()->create(['status' => CoopMember::STATUS_PENDING]);
        CoopMember::factory()->create(['status' => CoopMember::STATUS_ACTIVE]);

        $this->assertSame(1, CoopMember::where('status', CoopMember::STATUS_ACTIVE)->count());
    }

    /** The Active Members KPI on the staff dashboard reads the same value. */
    public function test_dashboard_kpi_shows_the_active_member_count(): void
    {
        CoopMember::factory()->create(['status' => CoopMember::STATUS_ACTIVE]);
        CoopMember::factory()->create(['status' => CoopMember::STATUS_PENDING]);

        $admin = User::factory()->asAdmin()->create();

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertSee((string) CoopMember::where('status', CoopMember::STATUS_ACTIVE)->count());
    }

    /** The legacy spellings are folded onto 'active' by the data migration. */
    public function test_legacy_status_spellings_are_canonicalized(): void
    {
        foreach (['Approved', 'approved', 'Active', 'ACTIVE'] as $legacy) {
            CoopMember::factory()->create(['status' => $legacy]);
        }

        (require database_path(
            'migrations/2026_10_21_131000_canonicalize_coop_member_status.php'
        ))->up();

        $this->assertSame(4, CoopMember::where('status', CoopMember::STATUS_ACTIVE)->count());
        $this->assertSame(0, CoopMember::whereIn('status', ['Approved', 'approved', 'Active', 'ACTIVE'])->count());
    }

    /** A member whose status is not active cannot borrow. */
    public function test_inactive_member_cannot_borrow(): void
    {
        $member = User::factory()->asMember()->create();

        CoopMember::whereKey($member->coop_member_id)
            ->update(['status' => CoopMember::STATUS_INACTIVE]);

        $this->actingAs($member->fresh());

        $response = $this->get(route('user.loans.create'));

        $response->assertRedirect(route('user.membership'));
        $response->assertSessionHas('error');
    }

    /** The user's role and the coop link always agree. */
    public function test_user_role_and_coop_link_agree(): void
    {
        $member = User::factory()->asMember()->create();
        $this->assertSame(Permissions::ROLE_MEMBER, $member->usertype);
        $this->assertNotNull($member->coop_member_id);

        $plain = User::factory()->asUser()->create();
        $this->assertSame(Permissions::ROLE_USER, $plain->usertype);
        $this->assertNull($plain->coop_member_id);
    }
}
