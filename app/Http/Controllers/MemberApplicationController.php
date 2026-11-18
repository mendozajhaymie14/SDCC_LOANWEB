<?php

namespace App\Http\Controllers;

use App\Models\MemberApplication;
use App\Services\DuplicateMemberService;
use App\Support\Permissions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MemberApplicationController extends Controller
{
    /**
     * Show the cooperative membership application form (guests only).
     */
    public function create()
    {
        $user = Auth::user();              // Single query; null when not logged in
        $isAuth = $user !== null;

        // Only registered non-members (and unregistered guests) may apply.
        if ($isAuth && ! Permissions::has($user, Permissions::APPLY_MEMBERSHIP)) {
            abort(403, 'Unauthorized action.');
        }

        $authEmail = $user?->email ?? '';

        return view('auth.member-application', compact('isAuth', 'authEmail'));
    }

    /**
     * Validate and persist a new membership application.
     */
    public function store(Request $request)
    {
        $data = $request->all();

        if (Auth::check() && ! Permissions::has(Auth::user(), Permissions::APPLY_MEMBERSHIP)) {
            abort(403, 'Unauthorized action.');
        }

        // One application at a time, and an approved one is final.
        //
        // This is checked before validation on purpose: an applicant who is
        // already approved (or already pending) must be told THAT, not the
        // generic "this e-mail belongs to a cooperative member" message that
        // the coop_members rule below would produce.
        // A logged-in user's email is read-only on the form, so the server
        // trusts the profile copy rather than whatever was posted.
        $email = Auth::check()
            ? strtolower(trim((string) Auth::user()->email))
            : strtolower(trim((string) $request->input('email')));

        if ($email !== '') {
            $existing = MemberApplication::withTrashed()->where('email', $email)->first();

            if ($existing?->status === 'approved') {
                return back()->withInput()
                    ->withErrors(['email' => 'Your membership application has already been approved.']);
            }

            if ($existing?->status === 'pending') {
                return back()->withInput()
                    ->withErrors(['email' => 'You already have a membership application under review. Please wait for the result.']);
            }
        }

        Validator::make($data, [
            // Identity
            'last_name'      => ['required', 'string', 'max:255'],
            'first_name'     => ['required', 'string', 'max:255'],
            'middle_name'    => ['nullable', 'string', 'max:255'],
            'occupation'     => ['required', 'string', 'max:100'],

            // Current Address
            'province'     => ['nullable', 'string', 'max:255'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'barangay'     => ['nullable', 'string', 'max:255'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'zip_code'     => ['nullable', 'string', 'max:12'],
            'perm_province' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'same_as_current_address' => ['nullable'],

            // Permanent address (existing fields kept for compatibility)
            'perm_street'        => ['nullable', 'string', 'max:255'],
            'perm_barangay'      => ['nullable', 'string', 'max:255'],
            'perm_municipality'  => ['nullable', 'string', 'max:255'],
            'perm_zip_code'      => ['nullable', 'string', 'max:12'],
            'perm_stay_years'    => ['nullable', 'integer', 'min:0', 'max:99'],
            'perm_stay_months'   => ['nullable', 'integer', 'min:0', 'max:11'],

            // Financial profile
            'monthly_income'              => ['nullable', 'numeric', 'min:0'],
            'existing_monthly_obligations' => ['nullable', 'numeric', 'min:0'],
            'savings_balance' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],

            // Demographics
            'residency_type' => ['nullable', 'string', 'max:100'],
            'contact_number' => ['required', new \App\Rules\PhilippineMobileNumber],
            'email'          => ['required', 'string', 'email', 'max:255', function ($attribute, $value, $fail) {
                $email = strtolower(trim($value));

                // Cooperative policy: membership applications only via gmail.
                if (! str_ends_with($email, '@gmail.com')) {
                    $fail('Only @gmail.com email addresses are allowed.');
                    return;
                }

                // Uniqueness of member_applications.email is handled at the
                // store level by the firstWhere() lookup + update/create path,
                // which prevents the MySQL 1062 duplicate-key crash. This check
                // remains to stop a registered CoopMember from re-applying.
                if (\App\Models\CoopMember::where('email', $email)->exists()) {
                    $fail('This email address is already registered as a cooperative member.');
                }
            }],
            'data_privacy_consent' => ['required', 'accepted'],
            'nationality'    => ['nullable', 'string', 'max:100'],
            'place_of_birth' => ['nullable', 'string', 'max:255'],
        ])->validate();

        // Normalize strings so the DB never stores stray whitespace
        $data['last_name']      = $this->normalize($data['last_name']);
        $data['first_name']     = $this->normalize($data['first_name']);
        $data['middle_name']    = isset($data['middle_name']) ? $this->normalize($data['middle_name']) : null;
        $data['email']          = strtolower(trim($data['email']));

        // Normalize address fields only when present
        foreach (['address_line', 'barangay', 'municipality', 'zip_code'] as $f) {
            if (!empty($data[$f])) {
                $data[$f] = $this->normalize($data[$f]);
            }
        }

        // Map the combined address_line field from the form to the street column
        if (!empty($data['address_line']) && empty($data['street'])) {
            $data['street'] = $this->normalize($data['address_line']);
        }
        foreach (['perm_street', 'perm_barangay', 'perm_municipality', 'perm_zip_code'] as $f) {
            if (!empty($data[$f])) {
                $data[$f] = $this->normalize($data[$f]);
            }
        }

        // Validate Date of Birth: real past date, at least 18 years old
        if (!empty($data['date_of_birth'])) {
            $dob = Carbon::createFromFormat('Y-m-d', $data['date_of_birth']);
            if ($dob === false || $dob->isFuture()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'date_of_birth' => ['Birthdate must be a past date.'],
                ]);
            }
            if ($dob->age < 18) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'date_of_birth' => ['Applicant must be at least 18 years old.'],
                ]);
            }
        } else {
            $data['date_of_birth'] = null;
        }

        // If "same as current address" is checked, copy current address fields to perm_*
        if (!empty($data['same_as_current_address'])) {
            $data['perm_street']        = $data['address_line']  ?? $request->input('address_line');
            $data['perm_barangay']      = $data['barangay']     ?? $request->input('barangay');
            $data['perm_municipality']  = $data['municipality'] ?? $request->input('municipality');
            $data['perm_province']      = $data['province']     ?? $request->input('province');
            $data['perm_zip_code']      = $data['zip_code']     ?? $request->input('zip_code');
        } else {
            // Copy province to perm_province when not same-as-current but empty
            if (empty($data['perm_province']) && !empty($data['province'])) {
                $data['perm_province'] = $data['province'];
            }
        }

        // Duplicate detection against existing coop_members (and pending applications).
        $data['surname'] = $request->input('last_name');
        $data['residency_type'] = $request->input('residency_type') ?? $request->input('residency') ?? 'owned';
        $data['nationality'] = $request->input('nationality', 'Filipino');
        $data['place_of_birth'] = $request->input('place_of_birth') ?? 'City of Las Piñas';
        $data['occupation'] = $request->input('occupation') ?? $request->input('hanap_buhay');
        $data['monthly_income']              = $request->input('monthly_income');
        $data['existing_monthly_obligations'] = $request->input('existing_monthly_obligations');

// Get email reliably from validated data, request input, or logged-in user
        $email = $data['email'] ?? $request->input('email') ?? (Auth::check() ? Auth::user()->email : null);

        if (!$email) {
            return back()->withErrors(['email' => 'Email address is required.'])->withInput();
        }

        // Duplicate detection against existing coop_members (and pending applications).
        // Block on email or contact number match; warn on name-only match.
        $fullName = $data['first_name'] . ' ' . $data['middle_name'] . ' ' . $data['surname'];
        $contactNumber = $data['contact_number'] ?? null;

        $check = app(DuplicateMemberService::class)->check(
            $fullName,
            $email,
            $contactNumber
        );

        // Never show an applicant another member's name, Member ID or which
        // field matched. The detailed text ($check['message']) is for staff
        // screens only; the public form gets a generic message.
        $publicDuplicateMessage = $check['publicMessage']
            ?? 'We could not process this application because some of the details you entered are already on file. If you are already a member, please register or sign in using your Member ID. Otherwise, please contact the cooperative office.';

        if ($check['isDuplicate']) {
            \Log::info('Duplicate membership application blocked', [
                'matched' => $check['matchedFields'] ?? [],
            ]);

            return back()->withInput()
                ->withErrors(['email' => $publicDuplicateMessage]);
        }

        if ($check['isWarning']) {
            // Name-only match - the application may continue. Store only the
            // generic text, never the existing member's details.
            session()->flash('duplicate_warning', $publicDuplicateMessage);
        }

        // Look up an existing application by email before writing, so the
        // MySQL unique constraint on the email column is never hit by an
        // INSERT (which can raise a 1062 duplicate-key crash on concurrent
        // submissions or stale sessions).
        $application = MemberApplication::withTrashed()->where('email', $email)->first();

        if ($application) {
            // A rejected application may be submitted again; the approved and
            // pending cases were already handled before validation.

            // If it was soft-deleted, restore it first
            if ($application->trashed()) {
                $application->restore();
            }

            // Update the existing record and put it back in the queue.
            $application->update($data + [
                'status'      => 'pending',
                'reviewed_at' => null,
                'reviewed_by' => null,
            ]);
        } else {
            // Create new record only if no record exists at all
            $application = MemberApplication::create($data + ['status' => 'pending']);
        }

        // Link the application to the submitting user account so the
        // membership flow can later connect the approved CoopMember back
        // to this same user (see UserController::dashboard / createApplication).
        if (Auth::check()) {
            $application->user_id = Auth::id();
            $application->save();
        }

        // Determine appropriate message based on whether it was new or updated
        $message = $application->wasRecentlyCreated
            ? 'Your membership application has been submitted. We will review it and get back to you.'
            : 'Your membership application has been updated. Thank you for keeping your information current.';

        return redirect()->route('member.applications.show', $application->id)
            ->with('success', $message);
    }

    /**
     * Show a single application (guests only, keyed by id so the URL is
     * not guessable without the confirmation page).
     *
     * The rejection reason is never shown on this page — it is only sent
     * via email (MemberRejectedMail).
     */
    public function show(int $id)
    {
        $application = MemberApplication::find($id);

        if (!$application) {
            abort(404);
        }

        return view('auth.member-application-confirmed', compact('application'));
    }

    /**
     * Helper to trim extra spaces and title-case a string.
     */
    private function normalize(string $value): string
    {
        return ucwords(strtolower(trim(preg_replace('/\s+/', ' ', $value))));
    }
}