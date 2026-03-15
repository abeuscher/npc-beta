<x-mail::message>
# Event cancelled

Hi {{ $registration->name }},

We're sorry to let you know that **{{ $registration->event->title }}** has been cancelled.

We apologise for any inconvenience this may cause. If you have any questions please
reply to this email and we will be happy to help.

Thanks for your understanding,<br>
{{ config('app.name') }}
</x-mail::message>
