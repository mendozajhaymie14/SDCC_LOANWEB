@component('mail::message')
# Membership Application Update

Dear **{{ $name }}**,

Thank you for applying for membership with San Dionisio Credit Cooperative (SDCC).

After careful review, we regret to inform you that your application was **not approved** at this time.

**Reason:** {{ $reason }}

You are welcome to submit a new application once you have addressed the concern above. You can do so by clicking the button below:

@component('mail::button', ['url' => $applyUrl])
Submit a New Application
@endcomponent

If you have any questions, please contact the cooperative office directly.

Thank you for your interest in SDCC.

San Dionisio Credit Cooperative
@endcomponent