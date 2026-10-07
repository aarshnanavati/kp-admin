<?php

namespace App\Mail;

use App\Helpers\AddressHelper;
use App\Helpers\TimezoneHelper;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public Customer $customer;
    public string $firstName;
    public string $fullAddress;
    public string $deliverySuburb;
    public string $registeredTime;
    public string $loginUrl;
    public ?string $logoUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(Customer $customer)
    {
        $this->customer = $customer;

        // Resolve First Name for greeting
        $nameParts = explode(' ', trim($customer->name), 2);
        $this->firstName = !empty($nameParts[0]) ? $nameParts[0] : 'Food Lover';

        // Resolve Address & Suburb
        $addrInfo = AddressHelper::formatResponsePayload($customer);
        $formatted = $addrInfo['formatted_address'] ?: ($customer->address ?: 'Adelaide, South Australia');
        $this->fullAddress = trim(str_replace(["\r\n", "\r", "\n"], ', ', $formatted), ', ');

        $suburb = $addrInfo['suburbs'] ?: ($customer->city ?: '');
        $postcode = $addrInfo['postcode'] ?: ($customer->pincode ?: '');
        if ($suburb && $postcode) {
            $this->deliverySuburb = "{$suburb} ({$postcode})";
        } elseif ($suburb) {
            $this->deliverySuburb = $suburb;
        } elseif ($postcode) {
            $this->deliverySuburb = "Postcode {$postcode}";
        } else {
            $this->deliverySuburb = 'Adelaide (5000)';
        }

        // Registered Time in Adelaide Timezone
        $createdAt = $customer->created_at ? Carbon::parse($customer->created_at) : TimezoneHelper::getAdelaideNow();
        $this->registeredTime = $createdAt->timezone(TimezoneHelper::ADELAIDE_TIMEZONE)->format('d/m/Y h:i A') . ' (Adelaide Time)';

        // Login URL
        $this->loginUrl = url('/login');

        // Logo URL
        $this->logoUrl = asset('public/assets/images/logo.png');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Welcome to KP's Kitchen, {$this->firstName}! 🍛 Authentic Indian Flavours Await",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.customer_welcome',
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
