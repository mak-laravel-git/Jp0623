<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells a member (or a person without an account yet) about the custom monthly membership price set for them.
 */
class MembershipCustomPriceMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $price,
        public ?string $name,
        public bool $registered,
        public ?string $appliesFrom = null,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->registered
                ? 'Your membership price is now $'.$this->price.'/month'
                : 'A custom membership price is reserved for you',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.membership-custom-price',
            with: [
                'price' => $this->price,
                'name' => $this->name,
                'registered' => $this->registered,
                'appliesFrom' => $this->appliesFrom,
                'url' => $this->registered ? route('subscription.index') : route('register'),
            ],
        );
    }
}
