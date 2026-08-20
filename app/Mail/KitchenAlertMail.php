<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class KitchenAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public $subjectStr;
    public $messageBody;
    public $viewName;

    /**
     * Create a new message instance.
     */
    public function __construct($subjectStr, $messageBody, $viewName = 'emails.alert')
    {
        $this->subjectStr = $subjectStr;
        $this->messageBody = $messageBody;
        $this->viewName = $viewName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectStr,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: $this->viewName,
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
