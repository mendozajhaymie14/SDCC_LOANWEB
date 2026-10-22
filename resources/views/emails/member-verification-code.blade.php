@component('mail::message')
# Your verification code

Use this code to confirm your cooperative membership for **{{ $memberId }}**:

## {{ $code }}

The code expires in 10 minutes and can only be used once.

If you did not ask for this code you can safely ignore this e-mail.

<p>---</p>
<p>San Dionisio Credit Cooperative</p>
@endcomponent
