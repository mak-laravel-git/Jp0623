<x-mail::message>
@if ($registered)
# Your membership price

Hello {{ $name ?: 'there' }},

@if ($appliesFrom)
We have set a custom membership price for your account: **${{ $price }} per month**. Your current paid period is not affected. The new price applies from your next renewal on **{{ $appliesFrom }}**.
@else
We have set a custom membership price for your account: **${{ $price }} per month**. This replaces the standard membership price.
@endif

<x-mail::button :url="$url">
View your membership
</x-mail::button>
@else
# A custom membership price is reserved for you

We have reserved a custom membership price for this email address: **${{ $price }} per month**. It applies automatically when you create your account with this email.

<x-mail::button :url="$url">
Create your account
</x-mail::button>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
