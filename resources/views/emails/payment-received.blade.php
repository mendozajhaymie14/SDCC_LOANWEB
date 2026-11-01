@component('mail::message')
# Payment Received

Hello {{ $loan->full_name ?? $loan->applicant }},

Your payment has been recorded successfully.

---

This is an automated notification from San Dionisio Credit Cooperative.
@endcomponent
