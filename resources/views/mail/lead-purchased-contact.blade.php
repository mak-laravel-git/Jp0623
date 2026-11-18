<x-mail::message>
# A vending operator will be in touch

Hello {{ $lead['contact_name'] }},

Thank you for your request for {{ $lead['business'] }}. We have matched your location with a vending operator, and they will contact you shortly. Here are their details:

<x-mail::table>
| | |
|:--|:--|
| **Company** | {{ $buyer['company'] }} |
| **Email** | {{ $buyer['email'] }} |
| **Phone** | {{ $buyer['phone'] ?: 'Not provided' }} |
</x-mail::table>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
