<?php

namespace Tests\Feature;

use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JetstreamProfileEmailSyncTest extends TestCase
{
    use RefreshDatabase;
    /** @test */
    public function member_email_change_via_jetstream_syncs_users_and_coop_members()
    {
        $user = User::factory()->asMember()->create(['email' => 'before@example.com']);

        (new UpdateUserProfileInformation)->update($user, [
            'name' => $user->name,
            'email' => 'after@example.com',
        ]);

        $this->assertSame('after@example.com', $user->fresh()->email);
        $this->assertSame('after@example.com', $user->coopMember->fresh()->email);
    }

    /** @test */
    public function non_member_email_change_works_without_coop_member()
    {
        $user = User::factory()->create(['usertype' => 'member']);

        (new UpdateUserProfileInformation)->update($user, [
            'name' => $user->name,
            'email' => 'changed@example.com',
        ]);

        $this->assertSame('changed@example.com', $user->fresh()->email);
        $this->assertNull($user->fresh()->coopMember);
    }
}