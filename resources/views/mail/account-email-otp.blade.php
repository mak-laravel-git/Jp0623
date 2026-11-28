<x-mail::message>
# Confirm your new email

Use this 6-digit code to change the email address on your account. It expires in 10 minutes.

<x-mail::panel>
**{{ $code }}**
</x-mail::panel>

Can't find our emails in your inbox? Check your spam or junk folder.

If you did not ask to change your email, you can ignore this message.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
