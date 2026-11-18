<x-mail::message>
# Your lead details

Thank you for your purchase. Here is the contact information for lead **{{ $lead['id'] }}**. Please reach out as soon as possible.

<x-mail::table>
| | |
|:--|:--|
| **Business** | {{ $lead['business'] }} |
| **Contact name** | {{ $lead['contact_name'] }} |
| **Email** | {{ $lead['email'] }} |
| **Phone** | {{ $lead['phone'] }} |
| **Address** | {{ $lead['address'] }} |
@if ($lead['location'] !== '')
| **City / State** | {{ $lead['location'] }} |
@endif
| **Request type** | {{ $lead['type'] }} |
</x-mail::table>

We have also emailed {{ $lead['contact_name'] }} your company name, email and phone number so they know to expect your call.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
