<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otp;
    public string $subjectStr;
    public string $purpose;

    /**
     * Create a new message instance.
     *
     * @param string $otp
     * @param string|null $subjectStr
     * @param string|null $purpose
     */
    public function __construct(string $otp, ?string $subjectStr = null, ?string $purpose = 'verification')
    {
        $this->otp = $otp;
        $this->purpose = $purpose ?: 'verification';
        $this->subjectStr = $subjectStr ?: ($this->purpose === 'password_reset' 
            ? "KP's Kitchen - Password Reset Code: {$otp}" 
            : "KP's Kitchen - Account Verification Code: {$otp}");
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
            view: 'emails.otp',
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
