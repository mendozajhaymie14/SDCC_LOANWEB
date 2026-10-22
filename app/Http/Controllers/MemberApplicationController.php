<?php

namespace App\Http\Controllers;

use App\Models\MemberApplication;
use App\Support\Permissions;
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
        $email = strtolower(trim((string) $request->input('email')));

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
            'suffix'         => ['nullable', 'string', 'max:10'],
            'sex'            => ['required', 'in:male,female,lgbtqia+'],
            'civil_status'   => ['required', 'in:single,married,legally_separated,annulled,widowed'],

            // Current Address
            'country'      => ['nullable', 'string', 'max:255'],
            'province'     => ['nullable', 'string', 'max:255'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'barangay'     => ['nullable', 'string', 'max:255'],
            'street'       => ['nullable', 'string', 'max:255'],
            'zip_code'     => ['nullable', 'string', 'max:12'],

            // Permanent address (existing fields kept for compatibility)
            'perm_house_no'      => ['nullable', 'string', 'max:50'],
            'perm_street'        => ['nullable', 'string', 'max:255'],
            'perm_barangay'      => ['nullable', 'string', 'max:255'],
            'perm_municipality'  => ['nullable', 'string', 'max:255'],
            'perm_zip_code'      => ['nullable', 'string', 'max:12'],
            'perm_stay_years'    => ['nullable', 'integer', 'min:0', 'max:99'],
            'perm_stay_months'   => ['nullable', 'integer', 'min:0', 'max:11'],

            // Demographics
            'residency_type' => ['nullable', 'string', 'max:100'],
            'contact_number' => ['required', 'digits:11'],
            'email'          => ['required', 'string', 'email', 'max:255', function ($attribute, $value, $fail) {
                $email = strtolower(trim($value));

                // Uniqueness of member_applications.email is handled at the
                // store level by the firstWhere() lookup + update/create path,
                // which prevents the MySQL 1062 duplicate-key crash. This check
                // remains to stop a registered CoopMember from re-applying.
                if (\App\Models\CoopMember::where('email', $email)->exists()) {
                    $fail('This email address is already registered as a cooperative member.');
                }
            }],
            'birthdate'      => ['required', 'date', 'before_or_equal:today'],
            'nationality'    => ['nullable', 'string', 'max:100'],
            'place_of_birth' => ['nullable', 'string', 'max:255'],
            'gender'         => ['nullable', 'string', 'max:50'],
            'occupation'     => ['nullable', 'string', 'max:255'],

            // Membership requirements
            'tin'             => ['nullable', 'digits:12'],
            'id_picture'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'proof_of_billing' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],

            // Character references (up to three)
            'character_references'            => ['nullable', 'array', 'max:3'],
            'character_references.*.full_name' => ['nullable', 'string', 'max:255'],
            'character_references.*.address'   => ['nullable', 'string', 'max:255'],
            'character_references.*.contact_number' => ['nullable', 'string', 'digits:11'],
        ])->validate();

        // Normalize strings so the DB never stores stray whitespace
        $data['last_name']      = $this->normalize($data['last_name']);
        $data['first_name']     = $this->normalize($data['first_name']);
        $data['middle_name']    = isset($data['middle_name']) ? $this->normalize($data['middle_name']) : null;
        $data['suffix']         = !empty($data['suffix'] ?? null) ? trim($data['suffix']) : null;
        $data['sex']            = !empty($data['sex'] ?? null) ? $this->normalize($data['sex']) : null;
        $data['civil_status']   = !empty($data['civil_status'] ?? null) ? $this->normalize($data['civil_status']) : null;
        $data['country_of_birth'] = !empty($data['country_of_birth'] ?? null) ? $this->normalize($data['country_of_birth']) : null;
        $data['city_of_birth']  = !empty($data['city_of_birth'] ?? null) ? $this->normalize($data['city_of_birth']) : null;
        $data['email']          = strtolower(trim($data['email']));

        // Normalize address fields only when present
        foreach (['street', 'barangay', 'municipality', 'zip_code'] as $f) {
            if (!empty($data[$f])) {
                $data[$f] = $this->normalize($data[$f]);
            }
        }

        // Map the combined address_line field from the form to the street column
        if (!empty($data['address_line']) && empty($data['street'])) {
            $data['street'] = $this->normalize($data['address_line']);
        }
        foreach (['perm_house_no', 'perm_street', 'perm_barangay', 'perm_municipality', 'perm_zip_code'] as $f) {
            if (!empty($data[$f])) {
                $data[$f] = $this->normalize($data[$f]);
            }
        }

        // Normalize TIN (trim + upper-case) so lookups are case-insensitive
        if (!empty($data['tin'])) {
            $data['tin'] = strtoupper(trim($data['tin']));
        } else {
            $data['tin'] = null;
        }

        // Normalize character references; drop fully-empty rows and cap at three
        if (!empty($data['character_references']) && is_array($data['character_references'])) {
            $refs = [];
            foreach (array_slice($data['character_references'], 0, 3) as $ref) {
                if (!is_array($ref)) {
                    continue;
                }

                $full_name  = isset($ref['full_name']) && $ref['full_name'] !== '' ? $this->normalize($ref['full_name']) : null;
                $address    = isset($ref['address']) && $ref['address'] !== '' ? $this->normalize($ref['address']) : null;
                $contact_no = isset($ref['contact_number']) && $ref['contact_number'] !== '' ? trim($ref['contact_number']) : null;

                if ($full_name || $address || $contact_no) {
                    $refs[] = [
                        'full_name'  => $full_name,
                        'address'    => $address,
                        'contact_number' => $contact_no,
                    ];
                }
            }
            $data['character_references'] = $refs;
        } else {
            $data['character_references'] = null;
        }

        // Store the supporting documents and keep only their public paths
        foreach (['id_picture', 'proof_of_billing'] as $doc) {
            if ($request->hasFile($doc) && $request->file($doc)->isValid()) {
                $data[$doc] = $request->file($doc)->store('member-applications', 'public');
            } else {
                $data[$doc] = null;
            }
        }

        // Map form fields to model attributes with fallbacks
        $data['surname'] = $request->input('last_name');
        $data['residency_type'] = $request->input('residency_type') ?? $request->input('residency') ?? 'owned';
        $data['nationality'] = $request->input('nationality', 'Filipino');
        $data['place_of_birth'] = $request->input('place_of_birth') ?? $request->input('city_of_birth') ?? 'City of Las Piñas';
        $data['gender'] = $request->input('gender') ?? $request->input('sex');
        $data['occupation'] = $request->input('occupation') ?? $request->input('hanap_buhay');

// Get email reliably from validated data, request input, or logged-in user
        $email = $data['email'] ?? $request->input('email') ?? (Auth::check() ? Auth::user()->email : null);

        if (!$email) {
            return back()->withErrors(['email' => 'Email address is required.'])->withInput();
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