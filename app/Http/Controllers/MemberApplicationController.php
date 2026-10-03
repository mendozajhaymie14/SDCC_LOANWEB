<?php

namespace App\Http\Controllers;

use App\Models\MemberApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MemberApplicationController extends Controller
{
    /**
     * Show the cooperative membership application form (guests only).
     */
    public function create()
    {
        return view('auth.member-application');
    }

    /**
     * Validate and persist a new membership application.
     */
    public function store(Request $request)
    {
        $data = $request->all();

        Validator::make($data, [
            // ── Identity ──
            'surname'       => ['required', 'string', 'max:255'],
            'first_name'    => ['required', 'string', 'max:255'],
            'middle_name'   => ['nullable', 'string', 'max:255'],

            // ── Present address ──
            'house_no'      => ['nullable', 'string', 'max:50'],
            'street'        => ['nullable', 'string', 'max:255'],
            'barangay'      => ['nullable', 'string', 'max:255'],
            'municipality'  => ['nullable', 'string', 'max:255'],
            'zip_code'      => ['nullable', 'string', 'max:12'],
            'stay_years'    => ['nullable', 'integer', 'min:0', 'max:99'],
            'stay_months'   => ['nullable', 'integer', 'min:0', 'max:11'],

            // ── Permanent address ──
            'perm_house_no'      => ['nullable', 'string', 'max:50'],
            'perm_street'        => ['nullable', 'string', 'max:255'],
            'perm barangay'      => ['nullable', 'string', 'max:255'],
            'perm_municipality'  => ['nullable', 'string', 'max:255'],
            'perm_zip_code'      => ['nullable', 'string', 'max:12'],
            'perm_stay_years'    => ['nullable', 'integer', 'min:0', 'max:99'],
            'perm_stay_months'   => ['nullable', 'integer', 'min:0', 'max:11'],

            // ── Demographics ──
            'residency_type' => ['required', 'in:owned,rented,mortgage,living_with_relatives'],
            'contact_number' => ['required', 'string', 'max:20'],
            'email'          => ['required', 'string', 'email', 'max:255', 'unique:member_applications', function ($attribute, $value, $fail) {
                $email = strtolower(trim($value));

                if (\App\Models\User::where('email', $email)->exists()) {
                    $fail('This email address is already registered as a user account.');
                }

                if (\App\Models\CoopMember::where('email', $email)->exists()) {
                    $fail('This email address is already registered as a cooperative member.');
                }
            }],
            'birthdate'      => ['required', 'date', 'before_or_equal:today'],
            'nationality'    => ['required', 'string', 'max:255'],
            'place_of_birth' => ['required', 'string', 'max:255'],
            'gender'         => ['required', 'in:male,female,lgbtqia+'],
            'occupation'     => ['required', 'string', 'max:255'],
            'civil_status'   => ['required', 'in:single,married,legally_separated,annulled,widowed,widower'],

            // ── Membership requirements ──
            'tin'             => ['nullable', 'string', 'max:30'],
            'id_picture'      => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'proof_of_billing' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ])->validate();

        // Normalize strings so the DB never stores stray whitespace
        $data['surname']        = $this->normalize($data['surname']);
        $data['first_name']     = $this->normalize($data['first_name']);
        $data['middle_name']    = isset($data['middle_name']) ? $this->normalize($data['middle_name']) : null;
        $data['nationality']    = $this->normalize($data['nationality']);
        $data['place_of_birth'] = $this->normalize($data['place_of_birth']);
        $data['occupation']     = $this->normalize($data['occupation']);
        $data['email']          = strtolower(trim($data['email']));

        // Normalize address fields only when present
        foreach (['house_no', 'street', 'barangay', 'municipality', 'zip_code'] as $f) {
            if (!empty($data[$f])) {
                $data[$f] = $this->normalize($data[$f]);
            }
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

        // Store the supporting documents and keep only their public paths
        foreach (['id_picture', 'proof_of_billing'] as $doc) {
            if ($request->hasFile($doc) && $request->file($doc)->isValid()) {
                $data[$doc] = $request->file($doc)->store('member-applications', 'public');
            } else {
                $data[$doc] = null;
            }
        }

        $application = MemberApplication::create($data);

        return redirect()->route('member.applications.show', $application->id)
            ->with('success', 'Your membership application has been submitted. We will review it and get back to you.');
    }

    /**
     * Show a single application (guests only, keyed by id so the URL is
     * not guessable without the confirmation page).
     */
    public function show(int $id)
    {
        $application = MemberApplication::query()->where('id', $id)->first();

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