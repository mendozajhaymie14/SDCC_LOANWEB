<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        $member = $user->coopMember;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:2048'], // Allows .jpg, .jpeg, and .png up to 2MB
        ];

        // Members must also keep coop_members.email unique
        if ($member) {
            $rules['email'][] = Rule::unique('coop_members')->ignore($member->id);
        }

        Validator::make($input, $rules)->validateWithBag('updateProfileInformation');

        if (isset($input['photo'])) {
            $user->updateProfilePhoto($input['photo']);
        }

        if ($input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $input, $member);
        } else {
            DB::transaction(function () use ($user, $input, $member) {
                $user->forceFill([
                    'name' => $input['name'],
                    'email' => $input['email'],
                ])->save();

                // Sync coop_members.email so the two tables never drift
                if ($member) {
                    $member->forceFill([
                        'email' => $input['email'],
                    ])->save();
                }
            });
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input, $member): void
    {
        DB::transaction(function () use ($user, $input, $member) {
            $user->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
                'email_verified_at' => null,
            ])->save();

            // Sync coop_members.email so the two tables never drift
            if ($member) {
                $member->forceFill([
                    'email' => $input['email'],
                ])->save();
            }

            $user->sendEmailVerificationNotification();
        });
    }
}
