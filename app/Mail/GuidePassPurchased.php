<?php

namespace App\Mail;

use App\Models\GuidePass;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GuidePassPurchased extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public GuidePass $guidePass) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "【{$this->guidePass->museum->name()}】音声ガイドのご購入ありがとうございます",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.guide-pass-purchased',
            with: [
                'museum' => $this->guidePass->museum,
                'passUrl' => route('visitor.passes.show', $this->guidePass->token),
                'museumUrl' => route('visitor.museum', $this->guidePass->museum),
            ],
        );
    }
}
