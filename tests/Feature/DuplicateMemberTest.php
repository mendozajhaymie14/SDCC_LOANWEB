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
}