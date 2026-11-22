<?php

namespace App\Mail;

use App\Models\LeadIssueReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the admin when an operator sends a purchased lead back.
 */
class LeadIssueReportedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(public LeadIssueReport $report)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Lead returned: {$this->report->lead_reference} ({$this->report->category->label()})",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.lead-issue-reported',
            with: [
                'report' => $this->report,
                'operator' => $this->report->user,
                'submittedAt' => $this->report->created_at->format('M j, Y g:i A'),
                'adminUrl' => route('admin.lead-issues.show', $this->report),
            ],
        );
    }
}
