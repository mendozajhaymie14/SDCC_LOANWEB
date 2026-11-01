<?php

namespace App\Services;

use App\Models\CoopMember;
use App\Models\MemberApplication;
use Illuminate\Support\Str;

class DuplicateMemberService
{
    /**
     * Check for duplicate member profiles.
     *
     * @param string $fullName
     * @param string $email
     * @param string|null $contactNumber
     * @param int|null $excludeMemberId Exclude this member ID (edit-self case)
     * @return array{
     *     isDuplicate: bool,
     *     isWarning: bool,
     *     message: string,
     *     matchedFields: string[],
     *     existingMember: object|null
     * }
     */
    public function check(
        string $fullName,
        string $email,
        ?string $contactNumber,
        ?int $excludeMemberId = null
    ): array {
        $normalizedName   = $this->normalizeName($fullName);
        $normalizedEmail  = strtolower(trim($email));
        $normalizedContact = $this->normalizeContactNumber($contactNumber);

        $excludeClause = $excludeMemberId ? "AND id != {$excludeMemberId}" : '';

        $matchedFields = [];
        $existingMember = null;

        // --- Email match (case-insensitive) - BLOCK ---
        if ($normalizedEmail) {
            $candidate = CoopMember::whereRaw("LOWER(email) = ?", [$normalizedEmail])
                ->when($excludeMemberId, fn ($q) => $q->where('id', '!=', $excludeMemberId))
                ->first();
            if (!$candidate) {
                $candidate = MemberApplication::whereRaw("LOWER(email) = ?", [$normalizedEmail])
                    ->when($excludeMemberId, fn ($q) => $q->where('id', '!=', $excludeMemberId))
                    ->first();
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
                $candidate = MemberApplication::whereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(contact_number, ' ', ''), '-', ''), '+63', '0'), '+', ''), '63', '0') = ?",
                    [$normalizedContact]
                )
                ->when($excludeMemberId, fn ($q) => $q->where('id', '!=', $excludeMemberId))
                ->first();
            }
            // Fallback: PHP-side normalization on fetched rows for edge cases
            if (!$candidate && $normalizedContact) {
                $candidate = $this->findByContactPhpSide(CoopMember::query(), $excludeMemberId, $normalizedContact);
                if (!$candidate) {
                    $candidate = $this->findByContactPhpSide(MemberApplication::query(), $excludeMemberId, $normalizedContact);
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

        return [
            'isDuplicate' => $isDuplicate,
            'isWarning' => $isWarning,
            'message' => $message,
            'matchedFields' => $matchedFields,
            'existingMember' => $existingMember,
        ];
    }

    /**
     * PHP-side name lookup fallback for MemberApplication (no CONCAT in SQLite).
     */
    private function findByNamePhpSide($query, ?int $excludeId, string $normalizedName)
    {
        $rows = $query->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))->get();
        foreach ($rows as $row) {
            $fullName = trim($row->first_name . ' ' . ($row->middle_name ?? '') . ' ' . $row->surname);
            if ($this->normalizeName($fullName) === $normalizedName) {
                return $row;
            }
        }
        return null;
    }

    /**
     * PHP-side contact number lookup fallback for edge cases SQL can't catch.
     */
    private function findByContactPhpSide($query, ?int $excludeId, string $normalizedContact)
    {
        $rows = $query->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))->get();
        foreach ($rows as $row) {
            $digits = preg_replace('/[^0-9]/', '', (string) ($row->contact_number ?? ''));
            if ($digits === $normalizedContact) {
                return $row;
            }
        }
        return null;
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