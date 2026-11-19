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
 * Sent to the operator after a lead purchase is paid, with the lead contact's details.
 */
class LeadPurchasedBuyerMail extends Mailable implements ShouldQueue
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
        $lead = $this->leadDetails();

        return new Envelope(
            subject: "Your lead details: {$lead['business']} ({$lead['id']})",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.lead-purchased-buyer',
            with: [
                'lead' => $this->leadDetails(),
                'buyer' => $this->buyerDetails(),
            ],
        );
    }
}
