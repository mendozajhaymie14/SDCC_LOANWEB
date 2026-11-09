<?php

namespace App\Services;

use App\Models\CoopMember;
use App\Models\MemberApplication;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DuplicateMemberService
{
    /**
     * Check for duplicate member profiles.
     *
     * @param string $fullName
     * @param string $email
     * @param string|null $contactNumber
     * @param int|null $excludeMemberId Exclude this member ID (edit-self case, coop_members.id)
     * @param int|null $excludeApplicationId Exclude this application ID (member_applications.id)
     * @return array{
     *     isDuplicate: bool,
     *     isWarning: bool,
     *     message: string,
     *     publicMessage: string,
     *     matchedFields: string[],
     *     existingMember: object|null
     * }
     */
    public function check(
        string $fullName,
        string $email,
        ?string $contactNumber,
        ?int $excludeMemberId = null,
        ?int $excludeApplicationId = null
    ): array {
        $normalizedName   = $this->normalizeName($fullName);
        $normalizedEmail  = strtolower(trim($email));
        $normalizedContact = $this->normalizeContactNumber($contactNumber);

        $matchedFields = [];
        $existingMember = null;

        // When editing a member, load its current email/contact for self-link detection.
        // Rule 3: an APPROVED application with NULL approved_member_id whose email/contact
        // match the member's currently stored values counts as the member's own.
        $memberCurrentEmail = null;
        $memberCurrentContact = null;
        if ($excludeMemberId !== null) {
            $self = CoopMember::find($excludeMemberId);
            if ($self) {
                $memberCurrentEmail = $self->email;
                $memberCurrentContact = $self->contact_number;
            }
        }

        // --- Email match (case-insensitive) - BLOCK ---
        if ($normalizedEmail) {
            // CoopMember path: only coop_members columns — no approved_member_id here.
            $candidate = CoopMember::whereRaw("LOWER(email) = ?", [$normalizedEmail])
                ->when($excludeMemberId, fn ($q) => $q->where('id', '!=', $excludeMemberId))
                ->first();
            if (!$candidate) {
                // MemberApplication path with grouped NULL condition (rule 2).
                if ($excludeMemberId !== null && $memberCurrentEmail !== null) {
                    $candidate = MemberApplication::whereRaw(
                        "LOWER(email) = ? AND (approved_member_id IS NULL OR approved_member_id != ?)",
                        [$normalizedEmail, $excludeMemberId]
                    )
                    ->when($excludeApplicationId, fn ($q) => $q->where('id', '!=', $excludeApplicationId))
                    ->first();
                    // Self-link exclusion: approved app with NULL link whose email
                    // OR contact matches the member's current stored value counts
                    // as the member's own (rule 3).
                    if ($candidate && $candidate instanceof MemberApplication
                        && $candidate->status === 'approved'
                        && $candidate->approved_member_id === null
                        && ($this->sameEmail($candidate->email, $memberCurrentEmail)
                            || $this->sameContact($candidate->contact_number, $memberCurrentContact))) {
                        $candidate = null;
                    }
                } else {
                    $candidate = MemberApplication::whereRaw("LOWER(email) = ?", [$normalizedEmail])
                        ->when($excludeApplicationId, fn ($q) => $q->where('id', '!=', $excludeApplicationId))
                        ->when($excludeMemberId, fn ($q) => $q->whereRaw(
                            "(approved_member_id IS NULL OR approved_member_id != ?)",
                            [$excludeMemberId]
                        ))
                        ->first();
                }
            }
            if ($candidate) {
                $matchedFields[] = 'email';
                $existingMember = $candidate;
            }
        }

        // --- Contact number match (digits-only normalization) - BLOCK ---
        if ($normalizedContact && !$existingMember) {
            $candidate = CoopMember::whereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(contact_number, ' ', ''), '-', ''), '+63', '0'), '+', ''), '63', '0') = ?",
                [$normalizedContact]
            )
            ->when($excludeMemberId, fn ($q) => $q->where('id', '!=', $excludeMemberId))
            ->first();
            if (!$candidate) {
                if ($excludeMemberId !== null && $memberCurrentContact !== null) {
                    $candidate = MemberApplication::whereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(contact_number, ' ', ''), '-', ''), '+63', '0'), '+', ''), '63', '0') = ? AND (approved_member_id IS NULL OR approved_member_id != ?)",
                        [$normalizedContact, $excludeMemberId]
                    )
                    ->when($excludeApplicationId, fn ($q) => $q->where('id', '!=', $excludeApplicationId))
                    ->first();
                    // Self-link exclusion: approved app with NULL link whose email
                    // OR contact matches the member's current stored value counts
                    // as the member's own (rule 3).
                    if ($candidate && $candidate instanceof MemberApplication
                        && $candidate->status === 'approved'
                        && $candidate->approved_member_id === null
                        && ($this->sameEmail($candidate->email, $memberCurrentEmail)
                            || $this->sameContact($candidate->contact_number, $memberCurrentContact))) {
                        $candidate = null;
                    }
                } else {
                    $candidate = MemberApplication::whereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(contact_number, ' ', ''), '-', ''), '+63', '0'), '+', ''), '63', '0') = ?",
                        [$normalizedContact]
                    )
                    ->when($excludeApplicationId, fn ($q) => $q->where('id', '!=', $excludeApplicationId))
                    ->when($excludeMemberId, fn ($q) => $q->whereRaw(
                        "(approved_member_id IS NULL OR approved_member_id != ?)",
                        [$excludeMemberId]
                    ))
                    ->first();
                }
            }
            // Fallback: PHP-side normalization on fetched rows for edge cases
            if (!$candidate && $normalizedContact) {
                $candidate = $this->findByContactPhpSide(CoopMember::query(), $excludeMemberId, $excludeApplicationId, $normalizedContact);
                if (!$candidate) {
                    $candidate = $this->findByContactPhpSide(MemberApplication::query(), $excludeMemberId, $excludeApplicationId, $normalizedContact);
                }
                // Self-link exclusion: same logic as SQL path — approved app with NULL link
                // matching the member's currently stored email/contact counts as the member's own (rule 3).
                if ($candidate && $candidate instanceof MemberApplication
                    && $candidate->status === 'approved'
                    && $candidate->approved_member_id === null
                    && ($this->sameEmail($candidate->email, $memberCurrentEmail)
                    || $this->sameContact($candidate->contact_number, $memberCurrentContact))) {
                    $candidate = null;
                }
            }
            if ($candidate) {
                $matchedFields[] = 'contact_number';
                $existingMember = $candidate;
            }
        }

        // --- Name match (normalized) - WARNING only unless combined with email/contact ---
        if ($normalizedName) {
            // CoopMember: compare full names in PHP (SQL REPLACE doesn't sort words)
            $candidate = null;
            $coopMembers = CoopMember::when($excludeMemberId, fn ($q) => $q->where('id', '!=', $excludeMemberId))->get();
            foreach ($coopMembers as $row) {
                if ($this->normalizeName($row->full_name) === $normalizedName) {
                    $candidate = $row;
                    break;
                }
            }

            if (!$candidate) {
                // MemberApplication: compare full name constructed from fields in PHP
                $candidate = $this->findByNamePhpSide(
                    MemberApplication::query(),
                    $excludeMemberId,
                    $excludeApplicationId,
                    $normalizedName
                );
            }

            if ($candidate && !in_array('email', $matchedFields) && !in_array('contact_number', $matchedFields)) {
                $matchedFields[] = 'name';
                $existingMember = $candidate;
            }
        }

        $isDuplicate = in_array('email', $matchedFields) || in_array('contact_number', $matchedFields);
        $isWarning = !$isDuplicate && in_array('name', $matchedFields);

        $message = $this->buildMessage($isDuplicate, $isWarning, $matchedFields, $existingMember);

        // Safe text for the public: never names another member or says which field matched.
        $publicMessage = ($isDuplicate || $isWarning)
            ? 'We could not process this application because some of the details you entered are already on file. If you are already a member, please register or sign in using your Member ID. Otherwise, please contact the cooperative office.'
            : '';

        // Rule 6: log the matched record's table name and id in the duplicate path.
        if ($isDuplicate && $existingMember) {
            Log::info("Duplicate member detection", [
                'matched_fields' => $matchedFields,
                'table' => $existingMember instanceof CoopMember ? 'coop_members' : 'member_applications',
                'id' => $existingMember->member_id ?? $existingMember->id,
                'status' => $existingMember->status ?? null,
                'approved_member_id' => $existingMember->approved_member_id ?? null,
                'email' => $existingMember->email ?? null,
                'contact_number' => $existingMember->contact_number ?? null,
                'exclude_member_id' => $excludeMemberId,
                'exclude_application_id' => $excludeApplicationId,
            ]);
        }

        return [
            'isDuplicate'   => $isDuplicate,
            'isWarning'     => $isWarning,
            'message'       => $message,          // staff-only, includes details
            'publicMessage' => $publicMessage,    // safe for applicants
            'matchedFields' => $matchedFields,
            'existingMember' => $existingMember,
        ];
    }

    /**
     * PHP-side name lookup fallback for MemberApplication (no CONCAT in SQLite).
     * Rule 1: no member_applications-only column (approved_member_id) filter here.
     * excludeApplicationId maps to id column on both models.
     */
    private function findByNamePhpSide($query, ?int $excludeMemberId, ?int $excludeApplicationId, string $normalizedName)
    {
        $rows = $query
            ->when($excludeMemberId, fn ($q) => $q->where('id', '!=', $excludeMemberId))
            ->when($excludeApplicationId, fn ($q) => $q->where('id', '!=', $excludeApplicationId))
            ->get();
        foreach ($rows as $row) {
            if ($this->isOwnApplication($row, $excludeMemberId)) {
                continue;
            }
            $fullName = trim($row->first_name . ' ' . ($row->middle_name ?? '') . ' ' . $row->surname);
            if ($this->normalizeName($fullName) === $normalizedName) {
                return $row;
            }
        }
        return null;
    }

    /**
     * PHP-side contact number lookup fallback for edge cases SQL can't catch.
     * Rule 1: no member_applications-only column (approved_member_id) filter here.
     * excludeApplicationId maps to id column on both models.
     */
    private function findByContactPhpSide($query, ?int $excludeMemberId, ?int $excludeApplicationId, string $normalizedContact)
    {
        $rows = $query
            ->when($excludeMemberId, fn ($q) => $q->where('id', '!=', $excludeMemberId))
            ->when($excludeApplicationId, fn ($q) => $q->where('id', '!=', $excludeApplicationId))
            ->get();
        foreach ($rows as $row) {
            if ($this->isOwnApplication($row, $excludeMemberId)) {
                continue;
            }
            $digits = preg_replace('/[^0-9]/', '', (string) ($row->contact_number ?? ''));
            if ($digits === $normalizedContact) {
                return $row;
            }
        }
        return null;
    }

    /**
     * True when $row is a MemberApplication that was approved into the member
     * currently being edited (its own application), so it must never count as a duplicate.
     */
    private function isOwnApplication($row, ?int $excludeMemberId): bool
    {
        return $excludeMemberId !== null
            && $row instanceof MemberApplication
            && $row->approved_member_id !== null
            && (int) $row->approved_member_id === $excludeMemberId;
    }

    private function sameEmail(?string $a, ?string $b): bool
    {
        return $a !== null && $b !== null && strtolower(trim($a)) === strtolower(trim($b));
    }

    private function sameContact(?string $a, ?string $b): bool
    {
        $na = $this->normalizeContactNumber($a);
        return $na !== null && $na === $this->normalizeContactNumber($b);
    }

    protected function normalizeName(string $name): string
    {
        $clean = strtolower(trim($name));
        $clean = preg_replace('/[,\.]/', '', $clean);
        $clean = preg_replace('/\s+/', ' ', $clean);

        $words = explode(' ', $clean);
        sort($words);

        return implode(' ', $words);
    }

    protected function normalizeContactNumber(?string $contact): ?string
    {
        if (!$contact) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $contact);

        if (Str::startsWith($digits, '639') && strlen($digits) === 13) {
            return '0' . substr($digits, 2); // 639XXXXXXXXX -> 09XXXXXXXXX
        }
        if (Str::startsWith($digits, '63') && strlen($digits) === 12) {
            return '0' . substr($digits, 2); // 639XXXXXXXXX -> 09XXXXXXXXX
        }
        if (Str::startsWith($digits, '9') && strlen($digits) === 10) {
            return '0' . $digits; // 9XXXXXXXXX -> 09XXXXXXXXX
        }
        if (Str::startsWith($digits, '09') && strlen($digits) === 11) {
            return $digits; // Already normalized
        }

        return $digits;
    }

    protected function buildMessage(
        bool $isDuplicate,
        bool $isWarning,
        array $matchedFields,
        $existingMember
    ): string {
        if ($isDuplicate) {
            $fields = implode(' and ', $matchedFields);
            $memberId = $existingMember->member_id ?? ($existingMember->id ?? 'N/A');
            $memberName = $existingMember->full_name ?? ($existingMember->fullName ?? 'Unknown');

            return "A member with the same {$fields} already exists: {$memberName} (Member ID: {$memberId})";
        }

        if ($isWarning) {
            $memberId = $existingMember->member_id ?? ($existingMember->id ?? 'N/A');
            $memberName = $existingMember->full_name ?? ($existingMember->fullName ?? 'Unknown');

            return "A member with a similar name exists: {$memberName} (Member ID: {$memberId}). Please verify before continuing.";
        }

        return '';
    }
}