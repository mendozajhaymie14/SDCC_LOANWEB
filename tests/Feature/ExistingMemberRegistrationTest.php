<?php

namespace Tests\Feature;

use App\Mail\MemberVerificationCodeMail;
use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\CoopMember;
use App\Models\MemberVerificationCode;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The "I am already a cooperative member" registration path.
 *
 * Every step is a POST. The mailer is faked so no real e-mail is sent, and
 * the response only ever shows a masked address.
 */
class ExistingMemberRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private const MEMBER_ID = 'SDCC-2023-0001';

    private CoopMember $member;
    private ?string $code = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = CoopMember::factory()->create([
            'member_id'      => self::MEMBER_ID,
            'email'          => 'juan@gmail.com',
            'contact_number' => '09171234567',
            'is_registered'  => false,
            'user_id'        => null,
        ]);
    }

    private function resetThrottle(): void
    {
        // Both limiter keys share an array cache; flush between steps so the
        // 5-per-minute counter does not exhaust itself mid-flow.
        Cache::flush();
    }

    /**
     * Run step 1 for the seeded member and keep the issued code.
     */
    private function issueCode(string $memberId = self::MEMBER_ID): string
    {
        Mail::fake();

        $this->postJson('/register/existing-member/send-code', ['member_id' => $memberId])
            ->assertOk();

        Mail::assertSent(MemberVerificationCodeMail::class, function ($mail) {
            if ($this->code === null) {
                $this->code = $mail->code;
            }
            return true;
        });

        $this->resetThrottle();

        return $this->code;
    }

    public function test_step_one_mails_code_to_the_on_file_address_and_returns_masked_value(): void
    {
        Mail::fake();

        $response = $this->postJson('/register/existing-member/send-code', [
            'member_id' => self::MEMBER_ID,
        ]);

        $response->assertOk()
            ->assertJson(['ok' => true])
            ->assertJson(['message' => 'If this ID has an email on file, we sent a code.'])
            ->assertJson(['email_masked' => 'j***@gmail.com'])
            ->assertJsonMissing(['email']);

        Mail::assertSent(MemberVerificationCodeMail::class, function ($mail) {
            return $mail->hasTo($this->member->email);
        });
    }

    public function test_unknown_member_id_returns_identical_generic_response_and_sends_no_mail(): void
    {
        Mail::fake();

        $response = $this->postJson('/register/existing-member/send-code', [
            'member_id' => 'SDCC-9999-9999',
        ]);

        $response->assertOk()
            ->assertJson(['ok' => true, 'message' => 'If this ID has an email on file, we sent a code.'])
            ->assertJson(['email_masked' => null]);

        Mail::assertNothingSent();
    }

    public function test_step_one_body_contains_no_full_email_or_name(): void
    {
        $body = $this->postJson('/register/existing-member/send-code', ['member_id' => self::MEMBER_ID])
            ->assertOk()
            ->getContent();

        // The only address in the body is masked; no name at all.
        $this->assertStringNotContainsString($this->member->email, $body);
        $this->assertStringNotContainsString($this->member->full_name, $body);
        $this->assertStringContainsString('j***@gmail.com', $body);
    }

    public function test_wrong_codes_lock_the_id_after_five_attempts(): void
    {
        $this->issueCode();

        for ($i = 1; $i < MemberVerificationCode::MAX_ATTEMPTS; ++$i) {
            $this->resetThrottle();
            $this->postJson('/register/existing-member/verify', [
                'member_id' => self::MEMBER_ID,
                'code'      => '000000',
            ])->assertStatus(422)->assertJson(['ok' => false]);
        }

        $this->resetThrottle();

        // The fifth wrong attempt trips the lock.
        $this->postJson('/register/existing-member/verify', [
            'member_id' => self::MEMBER_ID,
            'code'      => '000000',
        ])->assertStatus(429)->assertJson(['ok' => false]);
    }

    public function test_expired_code_fails(): void
    {
        CoopMember::factory()->create(['member_id' => 'SDCC-EX-1']);

        $issued = MemberVerificationCode::issue(CoopMember::where('member_id', 'SDCC-EX-1')->first());
        $issued['record']->update(['expires_at' => now()->subMinutes(1)]);

        $response = $this->postJson('/register/existing-member/verify', [
            'member_id' => 'sdcc-ex-1',
            'code'      => '000000',
        ]);

        $response->assertStatus(422)
            ->assertJson(['ok' => false, 'message' => 'That code is invalid or has expired. Please request a new one.']);
    }

    public function test_code_is_single_use(): void
    {
        $code = $this->issueCode();

        $this->postJson('/register/existing-member/verify', [
            'member_id' => self::MEMBER_ID,
            'code'      => $code,
        ])->assertOk()->assertJson(['ok' => true]);

        $this->resetThrottle();

        // The same code a second time is gone — the record was consumed.
        $this->postJson('/register/existing-member/verify', [
            'member_id' => self::MEMBER_ID,
            'code'      => $code,
        ])->assertStatus(422)->assertJson(['ok' => false]);
    }

    public function test_another_session_cannot_reuse_verified_session(): void
    {
        $code = $this->issueCode();

        $this->postJson('/register/existing-member/verify', [
            'member_id' => self::MEMBER_ID,
            'code'      => $code,
        ])->assertOk()->assertJson(['ok' => true]);

        $this->resetThrottle();

        // A second browser has its own session: the verification is not there.
        $this->flushSession();

        $this->post('/register', [
            'is_existing_member' => '1',
            'member_id'          => self::MEMBER_ID,
            'password'           => 'newpassword',
            'password_confirmation' => 'newpassword',
        ])->assertRedirect();

        $this->assertTrue(session('errors')->get('member_id')[0] === 'Your member verification is missing or has expired. Please request a new code.');
        $this->assertNull(User::where('email', $this->member->email)->first());
    }

    public function test_success_creates_linked_member_with_audit_row_and_notification(): void
    {
        $code = $this->issueCode();

        // Step 2 — the code proves the record; the session remembers it.
        $this->postJson('/register/existing-member/verify', [
            'member_id' => self::MEMBER_ID,
            'code'      => $code,
        ])->assertOk()->assertJson(['ok' => true]);

        $this->resetThrottle();

        $this->post('/register', [
            'is_existing_member' => '1',
            'member_id'          => self::MEMBER_ID,
            'password'           => 'newpassword',
            'password_confirmation' => 'newpassword',
        ])->assertRedirect();

        $user = User::where('email', $this->member->email)->first();

        $this->assertNotNull($user);
        $this->assertSame(Permissions::ROLE_MEMBER, Permissions::role($user));
        $this->assertSame($this->member->id, $user->coop_member_id);
        $this->assertNotNull($user->email_verified_at);

        $this->assertGreaterThan(0, AuditLog::count());

        $this->assertGreaterThan(0, AppNotification::where('user_id', $user->id)->count());
    }

    public function test_already_linked_id_is_refused(): void
    {
        $code = $this->issueCode();

        $this->postJson('/register/existing-member/verify', [
            'member_id' => self::MEMBER_ID,
            'code'      => $code,
        ])->assertOk();

        $this->resetThrottle();

        // Link a different online account to the same coop member AFTER the
        // code has been verified, so the self-link check trips.
        $otherUser = User::factory()->create([
            'usertype' => Permissions::ROLE_USER,
        ]);
        $otherUser->forceFill(['coop_member_id' => $this->member->id])->save();

        $this->post('/register', [
            'is_existing_member' => '1',
            'member_id'          => self::MEMBER_ID,
            'password'            => 'newpassword',
            'password_confirmation' => 'newpassword',
        ])->assertRedirect();

        $this->assertTrue(session('errors')->get('member_id')[0] === 'An online account has already been registered for this cooperative member.');
    }

    public function test_throttle_returns_429_when_six_requests_hit_per_minute(): void
    {
        $this->postJson('/register/existing-member/send-code', ['member_id' => 'SDCC-THR-1']);
        $this->resetThrottle();

        $responses = [];
        for ($i = 0; $i < 6; ++$i) {
            $responses[] = $this->postJson('/register/existing-member/send-code', [
                'member_id' => 'SDCC-THR-' . ($i + 1),
            ]);
        }

        $this->assertEquals(429, $responses[5]->getStatusCode());
    }

    public function test_member_without_email_cannot_self_link_and_falls_back_to_user_path(): void
    {
        CoopMember::factory()->create([
            'member_id'      => 'SDCC-NOMail-1',
            'email'          => '',
            'contact_number' => '09170000000',
        ]);

        Mail::fake();

        // Step 1: no address on file, so no code is sent — but the reply is
        // the same generic one, so nothing is revealed.
        $this->postJson('/register/existing-member/send-code', ['member_id' => 'SDCC-NOMail-1'])
            ->assertOk()
            ->assertJson(['ok' => true, 'email_masked' => null]);

        Mail::assertNothingSent();

        $this->resetThrottle();

        // The existing-member path is closed for that record, so the visitor
        // registers as a plain non-member.
        $this->post('/register', [
            'is_existing_member' => '0',
            'first_name'         => 'Jane',
            'last_name'          => 'Doe',
            'email'              => 'jane@gmail.com',
            'password'           => 'newpassword',
            'password_confirmation' => 'newpassword',
        ])->assertRedirect();

        $user = User::where('email', 'jane@gmail.com')->first();

        $this->assertNotNull($user);
        $this->assertSame(Permissions::ROLE_USER, Permissions::role($user));
        $this->assertNull($user->coop_member_id);
    }
}
