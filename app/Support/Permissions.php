<?php

namespace App\Support;

use App\Models\User;

/**
 * Permission matrix for 5-role RBAC system.
 * Each role gets a level of access by permission key.
 * Any route requiring a role must use ->middleware(['role:perm_key']) where perm_key is defined here.
 * Routes without a 'role:' middleware are public.
 */
class Permissions
{
    /** Role names (usertype values) */
    public const ROLE_ADMIN = 'admin';
    public const ROLE_BRANCH_MANAGER = 'branch_manager';
    public const ROLE_LOAN_OFFICER = 'loan_officer';
    public const ROLE_CREDIT_INVESTIGATOR = 'credit_investigator';
    public const ROLE_MEMBER = 'member';

    /**
     * Registered but not a cooperative member.
     *
     * A 'user' has an online account and the member portal, but no
     * coop_members link — so no loan, no release, no member profile.
     */
    public const ROLE_USER = 'user';

    /** The four staff roles admin can create (including admin itself) */
    public const STAFF_ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_BRANCH_MANAGER,
        self::ROLE_LOAN_OFFICER,
        self::ROLE_CREDIT_INVESTIGATOR,
    ];

    /** Permission keys — constant for use in routes and middleware */
    public const MANAGE_USERS = 'manage_users';
    public const MANAGE_SETTINGS = 'manage_settings';
    public const MANAGE_BACKUPS = 'manage_backups';
    public const VIEW_AUDIT_LOGS = 'view_audit_logs';
    public const APPROVE_LOANS = 'approve_loans';
    public const REJECT_LOANS = 'reject_loans';
    public const ENCODE_APPLICATIONS = 'encode_applications';
    public const VIEW_APPLICATIONS = 'view_applications';
    public const VIEW_EVALUATION = 'view_evaluation';
    public const RECORD_PAYMENTS = 'record_payments';
    public const VIEW_REPORTS = 'view_reports';
    public const STAFF_ACTIVITY = 'staff_activity';
    public const VERIFY_DOCUMENTS = 'verify_documents';
    public const ADD_REVIEW_NOTES = 'add_review_notes';
    public const VALIDATE_RECOMMENDATION = 'validate_recommendation';
    public const VIEW_MEMBER_PROFILES = 'view_member_profiles';
    public const EDIT_MEMBER_PROFILES = 'edit_member_profiles';
    public const VIEW_DASHBOARD = 'view_dashboard';
    public const OWN_PORTAL = 'own_portal';
    public const VIEW_OWN_LOANS = 'view_own_loans';
    public const APPROVE_MEMBERSHIPS = 'approve_memberships';
    public const PROCESS_DISBURSEMENTS = 'process_disbursements';

    /** The 'Become a member' form and the member's own membership status */
    public const APPLY_MEMBERSHIP = 'apply_membership';

    /** Applying for a loan (form, submit, cancel) */
    public const APPLY_LOAN = 'apply_loan';

    /** Permission matrix: role => [ permission => allowed, ... ] */
    public const PERMISSIONS = [
        self::ROLE_ADMIN => [
            self::MANAGE_USERS => true,
            self::MANAGE_SETTINGS => true,
            self::MANAGE_BACKUPS => true,
            self::VIEW_AUDIT_LOGS => true,
            self::VIEW_DASHBOARD => true,
            self::VIEW_MEMBER_PROFILES => true,
        ],
        self::ROLE_BRANCH_MANAGER => [
            self::VIEW_DASHBOARD => true,
            self::VIEW_REPORTS => true,
            self::STAFF_ACTIVITY => true,
            self::VIEW_AUDIT_LOGS => true,
            self::APPROVE_LOANS => true,
            self::REJECT_LOANS => true,
            self::ENCODE_APPLICATIONS => true,
            self::VIEW_APPLICATIONS => true,
            self::VIEW_EVALUATION => true,
            self::RECORD_PAYMENTS => true,
            self::VERIFY_DOCUMENTS => true,
            self::ADD_REVIEW_NOTES => true,
            self::VALIDATE_RECOMMENDATION => true,
            self::VIEW_MEMBER_PROFILES => true,
            self::EDIT_MEMBER_PROFILES => true,
            self::APPROVE_MEMBERSHIPS => true,
            self::PROCESS_DISBURSEMENTS => true,
        ],
        self::ROLE_LOAN_OFFICER => [
            self::VIEW_DASHBOARD => true,
            self::VIEW_MEMBER_PROFILES => true,
            self::EDIT_MEMBER_PROFILES => true,
            self::ENCODE_APPLICATIONS => true,
            self::VIEW_APPLICATIONS => true,
            self::VIEW_EVALUATION => true,
            self::RECORD_PAYMENTS => true,
        ],
        self::ROLE_CREDIT_INVESTIGATOR => [
            self::VIEW_DASHBOARD => true,
            self::VIEW_APPLICATIONS => true,
            self::VIEW_EVALUATION => true,
            self::VERIFY_DOCUMENTS => true,
            self::ADD_REVIEW_NOTES => true,
            self::VALIDATE_RECOMMENDATION => true,
            self::VIEW_MEMBER_PROFILES => true,
        ],
        self::ROLE_MEMBER => [
            self::VIEW_DASHBOARD => true,
            self::OWN_PORTAL => true,
            self::VIEW_OWN_LOANS => true,
            self::APPLY_LOAN => true,
        ],
        self::ROLE_USER => [
            self::VIEW_DASHBOARD => true,
            self::OWN_PORTAL => true,
            self::APPLY_MEMBERSHIP => true,
        ],
    ];

    /** Human-readable labels for roles */
    public const ROLE_LABELS = [
        self::ROLE_ADMIN => 'Administrator',
        self::ROLE_BRANCH_MANAGER => 'Branch Manager',
        self::ROLE_LOAN_OFFICER => 'Loan Officer',
        self::ROLE_CREDIT_INVESTIGATOR => 'Credit Investigator',
        self::ROLE_MEMBER => 'Member',
        self::ROLE_USER => 'Registered User (non-member)',
    ];

    /**
     * The role a user belongs to.
     *
     * 'user' is a role in its own right: registered, but not a cooperative
     * member. A row with no usertype at all is a registered account with no
     * cooperative link, which is the same thing, so it reads as 'user'.
     *
     * Every role read goes through here so no row can be mistaken for
     * "no role at all" (and therefore accidentally granted staff rights).
     */
    public static function role(?User $user): string
    {
        if ($user !== null && ! empty(trim((string) $user->role))) {
            return strtolower(trim((string) $user->role));
        }

        $usertype = strtolower(trim((string) ($user?->usertype ?? '')));

        return match ($usertype) {
            ''      => self::ROLE_USER,
            default => $usertype,
        };
    }

    /**
     * Check if a user has a specific permission.
     */
    public static function has(?User $user, string $permission): bool
    {
        if ($user === null) {
            return false;
        }

        $role = self::role($user);
        if (!isset(self::PERMISSIONS[$role])) {
            return false;
        }

        return self::PERMISSIONS[$role][$permission] ?? false;
    }

    /**
     * Get all role values.
     */
    public static function allRoles(): array
    {
        return array_keys(self::PERMISSIONS);
    }

    /**
     * Get all permission keys.
     */
    public static function allPermissions(): array
    {
        return array_values(array_unique([...array_map(array_keys(...), self::PERMISSIONS)]));
    }

    /**
     * Get role label.
     */
    public static function label(string $role): string
    {
        return self::ROLE_LABELS[$role] ?? ucfirst(str_replace('_', ' ', $role));
    }
}