@component('mail::message')
# Loan Application Update

Hello {{ $loan->full_name ?? $loan->applicant }},

Thank you for your application ({{ $loan->reference ?? $loan->app_id }}). After careful review, we regret to inform you that your loan application has been **not approved** at this time.

@if ($reason)
<p><strong>Reason:</strong> {{ $reason }}</p>
@endif

<p>You may reapply in the future once your circumstances have improved. Please log in to your member account to view more details.</p>

<p>---</p>
<p>This is an automated notification from San Dionisio Credit Cooperative.</p>
@endcomponent