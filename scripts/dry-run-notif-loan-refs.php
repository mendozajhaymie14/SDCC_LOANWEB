<?php
/**
 * READ-ONLY dry-run report: outbox "Loan" column vs the loan each event actually belongs to.
 *
 * Lists every app_notification_logs row with:
 *   - log id, recipient, type, created_at
 *   - stored loan_application_id
 *   - the app_id that column resolves to TODAY (LoanApplication::appId(), newest mirror)
 *   - the loan whose approval/rejection/payment event time + recipient actually match
 * and flags mismatches (stored ref NULL-but-matchable, stored ref != matching loan's ref,
 * or stored loan's status/time contradicts the event type).
 *
 * Usage:
 *   php scripts/dry-run-notif-loan-refs.php scratch   # safe: aborts unless DB is sdcc_scratch
 *   php scripts/dry-run-notif-loan-refs.php sdcc      # READS THE REAL DB — the USER runs this, never the agent
 *
 * No writes — every query is a SELECT.
 */
use App\Models\AppNotificationLog;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Models\User;

require __DIR__ . '/../vendor/autoload.php';

$env = in_array($argv[1] ?? '', ['scratch', 'sdcc'], true) ? $argv[1] : 'scratch';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->detectEnvironment(fn () => $env);
// Positional args do NOT switch the env file (only artisan's --env does) — load it explicitly.
$app->loadEnvironmentFrom('.env.' . $env);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$db = config('database.connections.' . config('database.default') . '.database');
if ($env === 'scratch' && $db !== 'sdcc_scratch') {
    fwrite(STDERR, "ABORT: env=scratch but DB_DATABASE='$db' (expected sdcc_scratch). Not running.\n");
    exit(1);
}
if ($env === 'sdcc') {
    fwrite(STDERR, "WARNING: this reads the REAL sdcc database. It is READ-ONLY (SELECTs only), but you must run it yourself.\n");
}

echo "DB: $db\n\n";

$logs = AppNotificationLog::orderBy('id')->get([
    'id', 'recipient_email', 'notification_type', 'status', 'created_at',
    'loan_application_id', 'user_id', 'subject',
]);

if (!$logs->count()) {
    echo "app_notification_logs is EMPTY in '$env' — nothing to report.\n";
    exit(0);
}

/** Loans whose user/email matches the recipient (portal link first, then email fallback). */
function candidateLoans(AppNotificationLog $log): \Illuminate\Support\Collection
{
    $cands = collect();
    if ($log->loan_application_id) {
        $cands->push(LoanApplication::find($log->loan_application_id));
    }
    $userIds = User::where('email', $log->recipient_email)->pluck('id');
    foreach (LoanApplication::whereIn('user_id', $userIds)->get() as $l) {
        $cands->push($l);
    }
    foreach (LoanApplication::where('email', $log->recipient_email)->get() as $l) {
        $cands->push($l);
    }
    return $cands->unique('id')->values();
}

/** Does this loan's event (type + time + recipient) plausibly match the log row? */
function eventMatches(AppNotificationLog $log, ?LoanApplication $loan): bool
{
    if (!$loan) {
        return false;
    }
    $day = \Carbon\Carbon::parse($log->created_at)->startOfDay();
    switch ($log->notification_type) {
        case 'approved':
            return strtolower((string) $loan->status) === 'approved'
                && \Carbon\Carbon::parse($loan->updated_at)->startOfDay()->diffInDays($day) <= 1;
        case 'rejected':
            return strtolower((string) $loan->status) === 'rejected'
                && \Carbon\Carbon::parse($loan->updated_at)->startOfDay()->diffInDays($day) <= 1;
        case 'payment_received':
            return Payment::where('loan_application_id', $loan->id)
                ->whereDate('payment_date', $day)
                ->exists();
        case 'due_soon':
        case 'due_today':
        case 'overdue':
            return $loan->next_due_date
                && \Carbon\Carbon::parse($loan->next_due_date)->startOfDay()->diffInDays($day) <= 3;
        default:
            return false; // membership_* and other events have no loan
    }
}

$mismatches = [];

foreach ($logs as $log) {
    $storedLoan = LoanApplication::find($log->loan_application_id);
    $resolvedToday = $storedLoan?->appId(); // canonical helper: newest mirror's app_id

    // The loan whose event time + recipient actually match.
    $match = null;
    foreach (candidateLoans($log) as $cand) {
        if (eventMatches($log, $cand)) {
            $match = $cand;
            break;
        }
    }

    $reasons = [];
    if (!$log->loan_application_id && $match) {
        $reasons[] = "stored NULL but loan #{$match->id} ({$match->appId()}) matches — backfillable";
    } elseif ($storedLoan && $match && $storedLoan->id !== $match->id) {
        $reasons[] = "stored loan #{$storedLoan->id} != matching loan #{$match->id}";
    }
    if ($storedLoan && $resolvedToday && $match && $resolvedToday !== $match->appId()) {
        $reasons[] = "stored ref $resolvedToday != matching ref {$match->appId()}";
    }
    if ($storedLoan && !eventMatches($log, $storedLoan)) {
        $reasons[] = "stored loan's status/time does not fit event '{$log->notification_type}'";
    }

    printf(
        "log #%d | %s | %-18s | %s | stored loan_application_id=%s | resolves today=%s | matching loan=%s%s\n",
        $log->id,
        $log->recipient_email,
        $log->notification_type,
        $log->created_at,
        $log->loan_application_id ?? 'NULL',
        $resolvedToday ?? 'N/A',
        $match ? "#{$match->id} ({$match->appId()})" : 'none',
        $reasons ? '  <-- MISMATCH: ' . implode('; ', $reasons) : ''
    );

    if ($reasons) {
        $mismatches[] = [
            'log_id' => $log->id,
            'type' => $log->notification_type,
            'stored_loan_application_id' => $log->loan_application_id,
            'resolves_today' => $resolvedToday,
            'matching_loan_id' => $match?->id,
            'matching_app_id' => $match?->appId(),
            'reasons' => $reasons,
        ];
    }
}

echo "\n=== MISMATCHES (" . count($mismatches) . ") ===\n";
foreach ($mismatches as $m) {
    echo "  log #{$m['log_id']} ({$m['type']}): stored loan_application_id=" . var_export($m['stored_loan_application_id'], true)
        . " -> resolves " . ($m['resolves_today'] ?? 'N/A') . "; matching loan #{$m['matching_loan_id']} -> {$m['matching_app_id']}"
        . " | " . implode('; ', $m['reasons']) . "\n";
}
echo count($mismatches) === 0 ? "  none\n" : '';
echo "\nNOTE: this script only READS. It never writes.\n";
echo "If stored loan_application_id values themselves are wrong, extend\n";
echo "app/Console/Commands/BackfillNotifLogLoans.php and run --dry-run first (never --apply without backup).\n";
