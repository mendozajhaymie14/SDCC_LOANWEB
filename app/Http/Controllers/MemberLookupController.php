<?php

namespace App\Http\Controllers;

use App\Models\CoopMember;
use Illuminate\Http\Request;

class MemberLookupController extends Controller
{
    /**
     * Look up a cooperative member by their member_id.
     * Returns the member's name parts for auto-fill on the registration form.
     * Accessible to guests (no auth) since it powers the register page.
     */
    public function show(Request $request, string $memberId)
    {
        $member = CoopMember::where('member_id', trim($memberId))->first();

        if (!$member) {
            return response()->json([
                'ok' => false,
                'message' => 'Member ID not found. Please check and try again.',
            ], 404);
        }

        $nameParts = explode(' ', trim($member->full_name));
        $firstName = array_shift($nameParts);
        $lastName = array_pop($nameParts);
        $middleName = implode(' ', array_filter($nameParts));

        return response()->json([
            'ok' => true,
            'member' => [
                'first_name'  => $firstName,
                'middle_name' => $middleName,
                'last_name'   => $lastName,
                'full_name'   => $member->full_name,
            ],
        ]);
    }
}
