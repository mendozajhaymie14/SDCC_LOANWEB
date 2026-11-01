@component('mail::message')
# Loan Approved 🎉

Hello {{ $loan->full_name ?? $loan->applicant }},

We're pleased to inform you that your loan application ({{ $loan->reference ?? $loan->app_id }}) has been **approved** for **₱{{ number_format($loan->amount, 2) }}**.

{{-- If monthly payment info exists --}}
@if (isset($loan->monthly_payment) && $loan->monthly_payment)
<p>Your monthly payment is <strong>₱{{ number_format($loan->monthly_payment, 2) }}</strong>.</p>
@endif

@if ($loan->next_due_date)
<p>Your first payment is due on <strong>{{ $loan->next_due_date->format('M d, Y') }}</strong>.</p>
@endif

<p>---</p>
<p>This is an automated notification from San Dionisio Credit Cooperative. Please log in to your member account to view more details.</p>
@endcomponent