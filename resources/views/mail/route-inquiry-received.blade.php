<x-mail::message>
# New route inquiry

A member has sent an inquiry about {{ $inquiry->listing_reference }} ({{ $inquiry->listing_city }}).

<x-mail::table>
| | |
|:--|:--|
| **Member name** | {{ $inquiry->contact_name }} |
| **Member email** | {{ $inquiry->contact_email }} |
| **Member phone** | {{ $inquiry->contact_phone ?: 'Not provided' }} |
| **Listing ID** | {{ $inquiry->listing_reference }} |
| **City** | {{ $inquiry->listing_city }} |
| **Asking price** | {{ $askingPrice }} |
| **Submitted** | {{ $submittedAt }} |
</x-mail::table>

**Message**

{{ $inquiry->message }}

<x-mail::button :url="$adminUrl">
View inquiry
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
