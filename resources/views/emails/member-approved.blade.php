@component('mail::message')
# Your membership is approved

Hello {{ $name }},

Congratulations! Your membership application with San Dionisio Credit Cooperative has been approved.

Your SDCC Member ID is:

## {{ $memberId }}

Please keep this ID safe. You will need it whenever you sign up or verify your membership.

@if ($hasAccount)
Your online account is already linked to this membership. You can log in and apply for a loan right away.

@component('mail::button', ['url' => $loginUrl])
Log in
@endcomponent
@else
**Next step:** create your online account.

1. Open the registration page and choose **"Yes, I am a member"**.
2. Enter your Member ID above and press **Send code**.
3. Type the 6-digit code we email you, then set your password.

@component('mail::button', ['url' => $registerUrl])
Create my account
@endcomponent
@endif

If you did not apply for membership, please ignore this e-mail.

<p>---</p>
<p>San Dionisio Credit Cooperative</p>
@endcomponent
