<x-mail::message>
# Your return was approved

Hi {{ $refund->user->name }},

We approved your return of lead **{{ $report->lead_reference }}**{{ $report->lead_city ? ' ('.$report->lead_city.')' : '' }} and refunded **${{ $refund->total_amount }}**.

<x-mail::table>
| | |
|:--|:--|
@if ($refund->credit_amount > 0)
| Credits added | {{ $refund->credit_amount }} Credits |
@endif
@if ($refund->card_amount > 0)
| Back to your card | ${{ $refund->card_amount }} (can take 5-10 business days) |
@endif
</x-mail::table>

@if ($refund->credit_amount > 0)
You can use your Credits when you buy your next lead.
@endif

@if ($refund->admin_note)
**Note from our team**

{{ $refund->admin_note }}
@endif

<x-mail::button :url="$refundsUrl">
View refund history
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
