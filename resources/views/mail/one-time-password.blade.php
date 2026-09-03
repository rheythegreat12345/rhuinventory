<x-mail::message>
# Verify your email address

Use the verification code below to finish creating your account. It expires in 10 minutes.

<x-mail::panel>
{{ $oneTimePassword }}
</x-mail::panel>

If you did not request this code, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
