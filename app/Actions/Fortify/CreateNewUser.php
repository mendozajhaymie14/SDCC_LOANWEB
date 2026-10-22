<?php

namespace App\Actions\Fortify;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\CoopMember;
use App\Models\User;
use App\Support\Permissions;
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

    /** How long a verified member stays verified in the session. */
    private const VERIFICATION_TTL_MINUTES = 15;

    /**
     * Validate and create a newly registered user.
     *
     * Two ways in:
     *
     *  - 'No, I am new'  → a registered non-member: usertype 'user'. The
     *    account has the member portal but no cooperative link, so no loan.
     *  - 'Yes, I am a member' → the SDCC ID was verified with a code mailed
     *    to the address on file, so the account is created already linked to
     *    that cooperative membership: usertype 'member'.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $isExisting = !empty($input['is_existing_member']) && $input['is_existing_member'] == '1';

        // For the existing-member path the cooperative record is the only
        // source of truth: the visitor may not type a different name or
        // e-mail, because the code proved the record, not what they typed.
        $member = $isExisting ? $this->verifiedMember() : null;

        // The code proved one specific record, so the Member ID the visitor
        // typed cannot change between the verify step and the submit.
        if ($member && !empty($input['member_id'])) {
            $submittedId = strtoupper(str_replace(' ', '', trim((string) $input['member_id'])));
            $verifiedId  = strtoupper(str_replace(' ', '', (string) $member->member_id));

            if ($submittedId !== $verifiedId) {
                throw ValidationException::withMessages([
                    'member_id' => ['The Member ID does not match the record that was verified.'],
                ]);
            }
        }

        if ($member) {
            $input['email'] = $member->email;
            $input['name']  = $member->full_name;
        }

        Validator::make($input, [
            'is_existing_member' => ['nullable', 'in:0,1'],
            'member_id'          => [Rule::requiredIf($isExisting), 'nullable', 'string', 'max:255'],
            'first_name'         => ['nullable', 'string', 'max:255'],
            'middle_name'        => ['nullable', 'string', 'max:255'],
            'last_name'          => ['nullable', 'string', 'max:255'],
            'email'              => $this->emailRules(),
            'password'           => $this->passwordRules(),
            'terms'              => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        if ($member) {
            return $this->createLinkedMember($member, $input);
        }

        // Plain registration: an account without a cooperative membership.
        $fullName = $this->normalizeString(
            implode(' ', array_filter([$input['first_name'] ?? null, $input['middle_name'] ?? null, $input['last_name'] ?? null]))
        );

        $user = User::create([
            'name'     => $fullName,
            'email'    => strtolower(trim($input['email'])),
            'password' => Hash::make($input['password']),
            'usertype' => Permissions::ROLE_USER,
            'role'     => Permissions::ROLE_USER,
        ]);

        return $user;
    }

    /**
     * The e-mail rules for a new account.
     *
     * The @gmail.com restriction is still in force (the cooperative has not
     * settled the question yet). It is a single config switch, so it can be
     * lifted in one line: GMAIL_ONLY_EMAILS=false in the environment file.
     *
     * @return array<int, string>
     */
    private function emailRules(): array
    {
        $rules = ['required', 'string', 'email', 'max:255', 'unique:users'];

        if (config('sdcc.gmail_only_email', true)) {
            $rules[] = 'ends_with:@gmail.com';
        }

        return $rules;
    }

    /**
     * The cooperative member this session has verified.
     *
     * The verification lives in the session, so another browser — or another
     * person — cannot pick it up.
     *
     * @throws ValidationException
     */
    private function verifiedMember(): CoopMember
    {
        $session = request()->session();
        $id      = $session->get('verified_coop_member_id');
        $at      = $session->get('verified_coop_member_at');

        if (! $id || ! $at || (now()->timestamp - (int) $at) >= self::VERIFICATION_TTL_MINUTES * 60) {
            throw ValidationException::withMessages([
                'member_id' => ['Your member verification is missing or has expired. Please request a new code.'],
            ]);
        }

        $member = CoopMember::find($id);

        if (! $member) {
            throw ValidationException::withMessages([
                'member_id' => ['That cooperative member record no longer exists.'],
            ]);
        }

        return $member;
    }

    /**
     * Create the account and link it to the verified cooperative member.
     *
     * The row is locked while the link is made, so two people verifying the
     * same member ID at the same time cannot both win.
     */
    private function createLinkedMember(CoopMember $member, array $input): User
    {
        return DB::transaction(function () use ($member, $input) {
            $locked = CoopMember::where('id', $member->id)->lockForUpdate()->first();

            if (! $locked) {
                throw ValidationException::withMessages([
                    'member_id' => ['That cooperative member record no longer exists.'],
                ]);
            }

            if ($locked->is_registered || $locked->user_id || User::where('coop_member_id', $locked->id)->exists()) {
                throw ValidationException::withMessages([
                    'member_id' => ['An online account has already been registered for this cooperative member.'],
                ]);
            }

            if (User::where('email', strtolower(trim($locked->email)))->exists()) {
                throw ValidationException::withMessages([
                    'email' => ['An account already exists for this email address.'],
                ]);
            }

            $user = User::create([
                'name'           => $locked->full_name,
                'email'          => strtolower(trim($locked->email)),
                'password'       => Hash::make($input['password']),
                'coop_member_id' => $locked->id,
                'usertype'       => Permissions::ROLE_MEMBER,
                'role'           => Permissions::ROLE_MEMBER,
            ]);

            // The code already proved the address, so no second verification
            // mail is sent.
            $user->email_verified_at = now();
            $user->save();

            $locked->update([
                'is_registered' => true,
                'user_id'       => $user->id,
                'status'        => CoopMember::STATUS_ACTIVE,
            ]);

            AuditLog::log(
                'Member Registered',
                $locked->full_name . ' (' . $locked->member_id . ') registered an online account.',
                $user->id
            );

            AppNotification::create([
                'user_id' => $user->id,
                'title'   => 'Welcome to San Dionisio Credit Cooperative',
                'message' => 'Your account is linked to cooperative member ' . $locked->member_id . '. You can now apply for a loan.',
                'type'    => AppNotification::TYPE_WELCOME,
            ]);

            request()->session()->forget(['verified_coop_member_id', 'verified_coop_member_at']);

            return $user;
        });
    }

    /**
     * Helper to trim extra spaces and capitalize text.
     */
    private function normalizeString(string $value): string
    {
        return ucwords(strtolower(trim(preg_replace('/\s+/', ' ', $value))));
    }
}
