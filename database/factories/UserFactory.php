<?php

namespace Database\Factories;

use App\Models\CoopMember;
use App\Models\Team;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Jetstream\Features;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * The default account is a registered non-member: it has an online
     * account but no cooperative membership.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'remember_token' => Str::random(10),
            'profile_photo_path' => null,
            'current_team_id' => null,
            'usertype' => Permissions::ROLE_USER,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user should be a System Administrator.
     */
    public function asAdmin(): static
    {
        return $this->state(['usertype' => Permissions::ROLE_ADMIN]);
    }

    /**
     * Indicate that the user should be a Branch Manager.
     */
    public function asBranchManager(): static
    {
        return $this->state(['usertype' => Permissions::ROLE_BRANCH_MANAGER]);
    }

    /**
     * Indicate that the user should be a Loan Officer.
     */
    public function asLoanOfficer(): static
    {
        return $this->state(['usertype' => Permissions::ROLE_LOAN_OFFICER]);
    }

    /**
     * Indicate that the user should be a Credit Investigator.
     */
    public function asCreditInvestigator(): static
    {
        return $this->state(['usertype' => Permissions::ROLE_CREDIT_INVESTIGATOR]);
    }

    /**
     * Indicate that the user should be a registered non-member.
     */
    public function asUser(): static
    {
        return $this->state(['usertype' => Permissions::ROLE_USER]);
    }

    /**
     * Indicate that the user is a Member: a cooperative membership record is
     * created and linked, so usertype and the coop link agree.
     */
    public function asMember(): static
    {
        return $this->state(['usertype' => Permissions::ROLE_MEMBER])
            ->afterCreating(function (User $user) {
                // The factory fills the NOT NULL columns (date_of_birth, …);
                // only the identity of the row is overridden here.
                $member = CoopMember::factory()->create([
                    'member_id'      => 'SDCC-' . now()->format('Y') . '-' . str_pad((string) (CoopMember::max('id') + 1), 4, '0', STR_PAD_LEFT),
                    'email'          => $user->email,
                    'full_name'      => $user->name,
                    'user_id'        => $user->id,
                    'is_registered'  => true,
                    'status'         => CoopMember::STATUS_ACTIVE,
                ]);

                $user->forceFill(['coop_member_id' => $member->id])->save();
            });
    }

    /**
     * Indicate that the user should have a personal team.
     */
    public function withPersonalTeam(?callable $callback = null): static
    {
        if (! Features::hasTeamFeatures()) {
            return $this->state([]);
        }

        return $this->has(
            Team::factory()
                ->state(fn (array $attributes, User $user) => [
                    'name' => $user->name.'\'s Team',
                    'user_id' => $user->id,
                    'personal_team' => true,
                ])
                ->when(is_callable($callback), $callback),
            'ownedTeams'
        );
    }
}
