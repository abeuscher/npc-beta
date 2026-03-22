Hi {{ $account->contact->first_name ?? $account->email }},

Thanks for creating an account. Please verify your email address by visiting the link below:

{{ $verificationUrl }}

This link expires in 60 minutes. If you did not create an account, you can safely ignore this email.

{{ config('app.name') }}
