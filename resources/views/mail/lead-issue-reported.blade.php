<x-mail::message>
# A lead was sent back

**{{ $operator->name }}** ({{ $operator->email }}) wants to return lead **{{ $report->lead_reference }}**.

<x-mail::table>
| | |
|:--|:--|
| Lead | {{ $report->lead_reference }}{{ $report->lead_name ? ' · '.$report->lead_name : '' }} |
| City | {{ $report->lead_city ?: '—' }} |
| Paid | ${{ $report->price }} |
| Reason type | {{ $report->category->label() }} |
| Sent | {{ $submittedAt }} |
</x-mail::table>

**Reason written by the operator**

{{ $report->reason }}

<x-mail::button :url="$adminUrl">
Review the request
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
