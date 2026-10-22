<?php

namespace App\Http\Controllers;

use App\Mail\MemberVerificationCodeMail;
use App\Models\CoopMember;
use App\Models\MemberVerificationCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * The "I am already a cooperative member" half of the registration flow.
 *
 * A visitor types their SDCC member ID, we mail a six-digit code to the
 * address on file, and they type the code back. Only then may they create
 * an account that is linked to that cooperative membership.
 *
 * Every step is a POST — nothing about a member is ever handed out by a GET.
 */
class MemberVerificationController extends Controller
{
    /**
     * The wording is the same whether or not a code was sent, so the
     * endpoint cannot be used to find out which member IDs exist.
     */
    private const GENERIC_MESSAGE = 'If this ID has an email on file, we sent a code.';

    /**
     * Step 1 — send the code.
     */
    public function sendCode(Request $request)
    {
        $data = $request->validate([
            'member_id' => ['required', 'string', 'max:255'],
        ]);

        $memberId  = strtoupper(trim($data['member_id']));
        $member    = CoopMember::where('member_id', $memberId)->first();
        $masked    = null;

        if ($member && $this->canSelfLink($member)) {
            $issued = MemberVerificationCode::issue($member, $request->ip());

            Mail::to($member->email)->send(new MemberVerificationCodeMail($issued['code'], $member->member_id));

            $masked = $this->mask($member->email);
        }

        return response()->json([
            'ok'           => true,
            'message'      => self::GENERIC_MESSAGE,
            'email_masked' => $masked,
        ]);
    }

    /**
     * Step 2 — check the code and remember the verified member.
     */
    public function verify(Request $request)
    {
        $data = $request->validate([
            'member_id' => ['required', 'string', 'max:255'],
            'code'      => ['required', 'string', 'size:6'],
        ]);

        $result = MemberVerificationCode::check($data['member_id'], $data['code']);

        if ($result['status'] === MemberVerificationCode::STATUS_LOCKED) {
            return response()->json([
                'ok'      => false,
                'message' => 'Too many incorrect attempts. This member ID is locked for ' . MemberVerificationCode::LOCK_MINUTES . ' minutes.',
            ], 429);
        }

        if ($result['status'] === MemberVerificationCode::STATUS_INCORRECT) {
            return response()->json([
                'ok'      => false,
                'message' => 'That code is incorrect.',
            ], 422);
        }

        if ($result['status'] === MemberVerificationCode::STATUS_INVALID) {
            return response()->json([
                'ok'      => false,
                'message' => 'That code is invalid or has expired. Please request a new one.',
            ], 422);
        }

        $member = CoopMember::find($result['record']->coop_member_id);

        if (! $member || ! $this->canSelfLink($member)) {
            return response()->json([
                'ok'      => false,
                'message' => 'That member ID cannot be registered online. Please use the membership application form instead.',
            ], 422);
        }

        // The verification belongs to this session only, and only for 15 minutes.
        $request->session()->put('verified_coop_member_id', $member->id);
        $request->session()->put('verified_coop_member_at', now()->timestamp);

        $parts = $member->nameParts();

        return response()->json([
            'ok'      => true,
            'message' => 'Verified. Choose a password to finish creating your account.',
            'member'  => [
                'first_name'      => $parts['first_name'],
                'middle_name'     => $parts['middle_name'],
                'last_name'       => $parts['last_name'],
                'email'           => $member->email,
                'contact_number'  => $member->contact_number,
            ],
        ]);
    }

    /**
     * May this member record be claimed by a new online account?
     *
     * The record must exist, carry an e-mail address, and not be linked to
     * an account already.
     */
    private function canSelfLink(CoopMember $member): bool
    {
        if (trim((string) $member->email) === '') {
            return false;
        }

        if ($member->is_registered || $member->user_id) {
            return false;
        }

        return ! User::where('coop_member_id', $member->id)->exists();
    }

    /**
     * Mask an e-mail address: first letter, the domain, nothing else.
     * e.g. juan.delacruz85@gmail.com -> j***@gmail.com
     */
    private function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', trim($email), 2), 2, '');

        return ($local !== '' ? mb_substr($local, 0, 1) : '') . '***@' . $domain;
    }
}
