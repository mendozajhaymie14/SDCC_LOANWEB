<?php

namespace App\Actions\Fortify;

use App\Models\CoopMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $isExisting = !empty($input['is_existing_member']) && $input['is_existing_member'] == '1';

        // 1. Conditional Form Input Validation
        Validator::make($input, [
            'is_existing_member' => ['nullable', 'in:0,1'],
            'member_id'          => [Rule::requiredIf($isExisting), 'nullable', 'string', 'max:255'],
            'first_name'         => ['nullable', 'string', 'max:255'],
            'middle_name'        => ['nullable', 'string', 'max:255'],
            'last_name'          => ['nullable', 'string', 'max:255'],
            'email'              => ['required', 'string', 'email', 'max:255', 'unique:users', 'ends_with:@gmail.com'],
            'password'           => $this->passwordRules(),
            'terms'              => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        // 2. Existing Member Creation Flow
        if ($isExisting) {
            $member = CoopMember::where('member_id', trim($input['member_id']))
                ->first();

            if (!$member) {
                throw ValidationException::withMessages([
                    'member_id' => ['The provided Member ID does not match our cooperative records.'],
                ]);
            }

            if (strtolower(trim($member->email)) !== strtolower(trim($input['email']))) {
                throw ValidationException::withMessages([
                    'email' => ['The email address does not match our registered records on file for this member.'],
                ]);
            }

            if ($member->is_registered) {
                throw ValidationException::withMessages([
                    'member_id' => ['An online account has already been registered for this cooperative member.'],
                ]);
            }

            return DB::transaction(function () use ($input, $member) {
                $user = User::create([
                    'name'           => $member->full_name, // Uses official name from coop_members record
                    'email'          => strtolower(trim($input['email'])),
                    'password'       => Hash::make($input['password']),
                    'coop_member_id' => $member->id,
                ]);

                $member->update([
                    'is_registered' => true,
                    'user_id'       => $user->id,
                ]);

                return $user;
            });
        }

        // 3. Standard New Member Creation Flow
        $fullName = $this->normalizeString(
            implode(' ', array_filter([$input['first_name'] ?? null, $input['middle_name'] ?? null, $input['last_name'] ?? null]))
        );

        return User::create([
            'name'     => $fullName,
            'email'    => strtolower(trim($input['email'])),
            'password' => Hash::make($input['password']),
        ]);
    }

    /**
     * Helper to trim extra spaces and capitalize text.
     */
    private function normalizeString(string $value): string
    {
        return ucwords(strtolower(trim(preg_replace('/\s+/', ' ', $value))));
    }
}