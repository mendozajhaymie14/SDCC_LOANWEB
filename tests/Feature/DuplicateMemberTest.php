<?php

namespace Tests\Feature;

use App\Models\CoopMember;
use App\Models\User;
use App\Services\DuplicateMemberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuplicateMemberTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create([
            'usertype' => 'branch_manager',
            'email'    => 'admin@example.com',
        ]);
    }

    private function makeMember(array $attrs = []): CoopMember
    {
        return CoopMember::factory()->create(array_merge([
            'member_id'      => 'SDCC-2025-0001',
            'full_name'      => 'Juan Dela Cruz',
            'email'          => 'juan@example.com',
            'contact_number' => '09171234567',
            'status'         => CoopMember::STATUS_ACTIVE,
        ], $attrs));
    }

    /** @test */
    public function same_email_blocks_creation()
    {
        $this->makeMember(['email' => 'juan@example.com']);

        $result = app(DuplicateMemberService::class)->check(
            'Juan Dela Cruz',
            'juan@example.com',
            '09171234567'
        );

        $this->assertTrue($result['isDuplicate']);
        $this->assertFalse($result['isWarning']);
        $this->assertContains('email', $result['matchedFields']);
        $this->assertStringContainsString('email', $result['message']);
        $this->assertStringContainsString('Juan Dela Cruz', $result['message']);
    }

    /** @test */
    public function same_contact_number_blocks_creation()
    {
        $this->makeMember(['contact_number' => '09171234567']);

        $result = app(DuplicateMemberService::class)->check(
            'Juan Dela Cruz',
            'different@example.com',
            '09171234567'
        );

        $this->assertTrue($result['isDuplicate']);
        $this->assertFalse($result['isWarning']);
        $this->assertContains('contact_number', $result['matchedFields']);
        $this->assertStringContainsString('contact', $result['message']);
    }

    /** @test */
    public function same_contact_number_different_formats_blocks_creation()
    {
        $this->makeMember(['contact_number' => '09171234567']);

        // Test +63 format
        $result1 = app(DuplicateMemberService::class)->check(
            'Juan Dela Cruz',
            'different@example.com',
            '+639171234567'
        );
        $this->assertTrue($result1['isDuplicate']);
        $this->assertContains('contact_number', $result1['matchedFields']);

        // Test 639 format
        $result2 = app(DuplicateMemberService::class)->check(
            'Juan Dela Cruz',
            'different@example.com',
            '639171234567'
        );
        $this->assertTrue($result2['isDuplicate']);
        $this->assertContains('contact_number', $result2['matchedFields']);

        // Test 9 format (no leading 0)
        $result3 = app(DuplicateMemberService::class)->check(
            'Juan Dela Cruz',
            'different@example.com',
            '9171234567'
        );
        $this->assertTrue($result3['isDuplicate']);
        $this->assertContains('contact_number', $result3['matchedFields']);
    }

    /** @test */
    public function same_normalized_name_warns_but_allows()
    {
        $this->makeMember(['full_name' => 'Juan Dela Cruz']);

        // Name with different order and case
        $result = app(DuplicateMemberService::class)->check(
            'CRUZ JUAN DELA',
            'different@example.com',
            '09181234567'
        );

        $this->assertFalse($result['isDuplicate']);
        $this->assertTrue($result['isWarning']);
        $this->assertContains('name', $result['matchedFields']);
        $this->assertStringContainsString('similar name', $result['message']);
    }

    /** @test */
    public function same_name_with_different_email_warns_only()
    {
        $this->makeMember([
            'full_name' => 'Juan Dela Cruz',
            'email'     => 'juan@example.com',
        ]);

        // Same name, different email and contact
        $result = app(DuplicateMemberService::class)->check(
            'Juan Dela Cruz',
            'different@example.com',
            '09181234567'
        );

        $this->assertFalse($result['isDuplicate']);
        $this->assertTrue($result['isWarning']);
        $this->assertContains('name', $result['matchedFields']);
    }

    /** @test */
    public function edit_self_does_not_flag_duplicate()
    {
        $existing = $this->makeMember([
            'id'             => 5,
            'full_name'      => 'Juan Dela Cruz',
            'email'          => 'juan@example.com',
            'contact_number' => '09171234567',
        ]);

        // Same data but excluding this member's ID
        $result = app(DuplicateMemberService::class)->check(
            'Juan Dela Cruz',
            'juan@example.com',
            '09171234567',
            5 // exclude_id
        );

        $this->assertFalse($result['isDuplicate']);
        $this->assertFalse($result['isWarning']);
        $this->assertEmpty($result['matchedFields']);
    }

    /** @test */
    public function email_case_insensitive_match()
    {
        $this->makeMember(['email' => 'Juan@Example.COM']);

        $result = app(DuplicateMemberService::class)->check(
            'Juan Dela Cruz',
            'juan@example.com', // lowercase
            '09171234567'
        );

        $this->assertTrue($result['isDuplicate']);
        $this->assertContains('email', $result['matchedFields']);
    }

    /** @test */
    public function pending_member_application_email_match_blocks()
    {
        \App\Models\MemberApplication::create([
            'first_name'      => 'Juan',
            'surname'         => 'Dela Cruz',
            'email'           => 'pending@example.com',
            'contact_number'  => '09171234567',
            'status'          => 'pending',
        ]);

        $result = app(DuplicateMemberService::class)->check(
            'Juan Dela Cruz',
            'pending@example.com',
            '09181234567'
        );

        $this->assertTrue($result['isDuplicate']);
        $this->assertContains('email', $result['matchedFields']);
    }

    /** @test */
    public function pending_member_application_contact_match_blocks()
    {
        \App\Models\MemberApplication::create([
            'first_name'      => 'Juan',
            'surname'         => 'Dela Cruz',
            'email'           => 'pending@example.com',
            'contact_number'  => '09171234567',
            'status'          => 'pending',
        ]);

        $result = app(DuplicateMemberService::class)->check(
            'Juan Dela Cruz',
            'different@example.com',
            '+639171234567'
        );

        $this->assertTrue($result['isDuplicate']);
        $this->assertContains('contact_number', $result['matchedFields']);
    }

    /** @test */
    public function edit_self_with_own_approved_application_email_succeeds()
    {
        $member = CoopMember::factory()->create([
            'full_name'      => 'Maria Santos',
            'email'          => 'maria@example.com',
            'contact_number' => '09171112222',
            'status'         => CoopMember::STATUS_ACTIVE,
        ]);

        $application = \App\Models\MemberApplication::factory()->create([
            'email'            => 'maria@example.com',
            'contact_number'   => '09171112222',
            'status'           => 'approved',
        ]);

        // The member's own approved application has the same email.
        // Excluding this member's coop_members id and this application's id
        // should not flag a duplicate.
        $result = app(DuplicateMemberService::class)->check(
            'Maria Santos',
            'maria@example.com',
            '09171112222',
            $member->id,
            $application->id
        );

        $this->assertFalse($result['isDuplicate']);
        $this->assertFalse($result['isWarning']);
        $this->assertEmpty($result['matchedFields']);
    }

    /** @test */
    public function edit_self_to_another_members_email_still_blocked()
    {
        $this->makeMember(['email' => 'other@example.com']);

        $result = app(DuplicateMemberService::class)->check(
            'Juan Dela Cruz',
            'other@example.com',
            '09181234567',
            5   // excludeMemberId (different member id)
        );

        $this->assertTrue($result['isDuplicate']);
        $this->assertContains('email', $result['matchedFields']);
    }

    /** @test */
    public function approve_member_not_blocked_by_unrelated_coop_member_with_same_numeric_id()
    {
        // An unrelated coop_member with a particular id
        $unrelatedMember = CoopMember::factory()->create([
            'full_name'      => 'Unrelated Member',
            'email'          => 'unrelated@example.com',
            'contact_number' => '09179998888',
            'status'         => CoopMember::STATUS_ACTIVE,
        ]);

        $application = \App\Models\MemberApplication::factory()->create([
            'email'        => 'applicant@example.com',
            'contact_number'=> '09170001111',
            'status'       => 'pending',
        ]);

        // Exclude the application itself by id.
        // The unrelated coop_member should not block the check.
        $result = app(DuplicateMemberService::class)->check(
            'Applicant Name',
            'applicant@example.com',
            '09170001111',
            null, // excludeMemberId (no linked coop_member for this application)
            $application->id  // excludeApplicationId (member_applications.id)
        );

        $this->assertFalse($result['isDuplicate']);
        $this->assertFalse($result['isWarning']);
        $this->assertEmpty($result['matchedFields']);
    }

    /** @test */
    public function bug_A_PHP_side_helper_crashes_on_coop_member_approved_member_id_filter()
    {
        // A coop member (different from the application)
        $unrelatedMember = CoopMember::factory()->create([
            'full_name'      => 'Unrelated Member',
            'email'          => 'unrelated@example.com',
            'contact_number' => '09179998888',
            'status'         => CoopMember::STATUS_ACTIVE,
        ]);

        // A pending application with unique email and contact
        $application = \App\Models\MemberApplication::factory()->create([
            'first_name'      => 'Applicant',
            'surname'         => 'Name',
            'email'           => 'applicant@example.com',
            'contact_number'  => '09170001111',
            'status'          => 'pending',
        ]);

        // Check should not flag duplicate — the unrelated coop_member has
        // a different email/contact, and the pending app has unique email/contact.
        // The PHP-side fallback path on CoopMember must not crash with the
        // member_applications-only column (approved_member_id) that doesn't
        // exist on coop_members.
        $result = app(DuplicateMemberService::class)->check(
            'Applicant Name',
            'unique@example.com',
            '09170009999',
            null, // excludeMemberId (no linked coop_member)
            null  // excludeApplicationId
        );

        $this->assertFalse($result['isDuplicate']);
        $this->assertFalse($result['isWarning']);
        $this->assertEmpty($result['matchedFields']);
    }

    /** @test */
    public function bug_B_editing_member_with_own_approved_app_NULL_approved_member_id()
    {
        // A coop member (the one being edited)
        $member = CoopMember::factory()->create([
            'full_name'      => 'Fat Jhaymie Mendoza',
            'member_id'      => 'SDCC-2026-0006',
            'email'          => 'mendoza@example.com',
            'contact_number' => '09171234567',
            'status'         => CoopMember::STATUS_ACTIVE,
        ]);

        // An approved application with NULL approved_member_id (old record)
        // but with the same email and contact number as the member.
        // Use a DIFFERENT id than the member so the id!=excludeMemberId
        // fallback does not accidentally exclude it.
        $app = \App\Models\MemberApplication::factory()->create([
            'email'          => 'mendoza@example.com',
            'contact_number' => '09171234567',
            'status'         => 'approved',
        ]);
        $app->id = 999;
        $app->save();

        // Editing this member should succeed — the application counts as
        // the member's own because approved_member_id IS NULL and the
        // email/contact match the member's currently stored values.
        $result = app(DuplicateMemberService::class)->check(
            'Fat Jhaymie Mendoza',
            'mendoza@example.com',
            '09171234567',
            $member->id,   // exclude coop_members.id
            null         // excludeApplicationId
        );

        $this->assertFalse($result['isDuplicate']);
        $this->assertFalse($result['isWarning']);
        $this->assertEmpty($result['matchedFields']);
    }

    /** @test */
    public function bug_C_editing_member_with_own_approved_app_NULL_link_contact_only_match()
    {
        // A coop member (the one being edited)
        $member = CoopMember::factory()->create([
            'full_name'      => 'Fat Jhaymie Mendoza',
            'member_id'      => 'SDCC-2026-0006',
            'email'          => 'mendoza@example.com',
            'contact_number' => '09171234567',
            'status'         => CoopMember::STATUS_ACTIVE,
        ]);

        // An approved application with NULL approved_member_id (old record)
        // whose CONTACT matches the member but whose EMAIL differs.
        // Rule 3: email OR contact match counts as the member's own.
        $app = \App\Models\MemberApplication::factory()->create([
            'email'           => 'old-mendoza@example.com',
            'contact_number'  => '09171234567',
            'status'          => 'approved',
        ]);
        $app->id = 999;
        $app->save();

        // Editing this member should succeed — the application counts as
        // the member's own because approved_member_id IS NULL and the
        // contact matches the member's currently stored value.
        $result = app(DuplicateMemberService::class)->check(
            'Fat Jhaymie Mendoza',
            'mendoza@example.com',
            '09171234567',
            $member->id,   // exclude coop_members.id
            null         // excludeApplicationId
        );

        $this->assertFalse($result['isDuplicate']);
        $this->assertFalse($result['isWarning']);
        $this->assertEmpty($result['matchedFields']);
    }

    /** @test */
    public function editing_to_same_email_as_different_pending_app_blocks()
    {
        // A coop member being edited
        $member = CoopMember::factory()->create([
            'full_name'      => 'Juan Dela Cruz',
            'email'          => 'juan@example.com',
            'contact_number' => '09171234567',
            'status'         => CoopMember::STATUS_ACTIVE,
        ]);

        // A different pending application with the same email/contact as the member
        // (but this app's approved_member_id does NOT equal the member's id,
        // so it's not the member's own application).
        \App\Models\MemberApplication::factory()->create([
            'first_name'      => 'Other',
            'surname'         => 'Applicant',
            'email'           => 'juan@example.com',
            'contact_number'  => '09171234567',
            'status'          => 'pending',
        ]);

        // Editing this member to the same email as a different pending app
        // should be blocked as a duplicate.
        $result = app(DuplicateMemberService::class)->check(
            'Juan Dela Cruz',
            'juan@example.com',
            '09171234567',
            $member->id,   // exclude this member
            null          // excludeApplicationId (different app, not excluded)
        );

        $this->assertTrue($result['isDuplicate']);
        $this->assertContains('email', $result['matchedFields']);
    }
}