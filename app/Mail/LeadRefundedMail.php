<?php

namespace App\Mail;

use App\Models\LeadRefund;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the member when their returned lead is refunded.
 */
class LeadRefundedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(public LeadRefund $refund)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your returned lead {$this->refund->report->lead_reference} was refunded",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.lead-refunded',
            with: [
                'refund' => $this->refund,
                'report' => $this->refund->report,
                'refundsUrl' => route('leads.refunds'),
            ],
        );
    }
}
