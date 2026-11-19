<?php

namespace App\Mail;

use App\Models\RouteListingInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the business when a member sends an inquiry about an established location / route for sale.
 */
class RouteInquiryReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(public RouteListingInquiry $inquiry)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New route inquiry: {$this->inquiry->listing_reference} ({$this->inquiry->listing_city})",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.route-inquiry-received',
            with: [
                'inquiry' => $this->inquiry,
                'askingPrice' => '$'.number_format($this->inquiry->listing_asking_price),
                'submittedAt' => $this->inquiry->created_at->format('M j, Y g:i A'),
                'adminUrl' => route('admin.route-inquiries.show', $this->inquiry),
            ],
        );
    }
}
