<?php

namespace App\Mail;

use App\Mail\Concerns\DescribesLeadPurchase;
use App\Models\LeadPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the lead contact after an operator buys their lead, with the operator's details.
 */
class LeadPurchasedContactMail extends Mailable implements ShouldQueue
{
    use DescribesLeadPurchase, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(public LeadPurchase $purchase)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $buyer = $this->buyerDetails();

        return new Envelope(
            subject: "{$buyer['company']} will be contacting you about your request",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.lead-purchased-contact',
            with: [
                'lead' => $this->leadDetails(),
                'buyer' => $this->buyerDetails(),
            ],
        );
    }
}
