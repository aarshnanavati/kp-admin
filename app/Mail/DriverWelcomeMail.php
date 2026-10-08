<?php

namespace App\Mail;

use App\Helpers\AddressHelper;
use App\Helpers\TimezoneHelper;
use App\Models\Driver;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DriverWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public Driver $driver;
    public string $mode;
    public string $firstName;
    public string $fullAddress;
    public string $deliveryZone;
    public string $vehicleRegNo;
    public string $licenseNo;
    public string $registeredTime;
    public bool $isApproved;
    public ?string $logoUrl;

    /**
     * Create a new message instance.
     *
     * @param Driver $driver
     * @param string|null $mode 'registration' | 'approved'
     */
    public function __construct(Driver $driver, ?string $mode = null)
    {
        $this->driver = $driver;

        $approvalStatus = strtolower((string) ($driver->approval_status ?? 'Pending'));
        $this->isApproved = ($mode === 'approved') || ($approvalStatus === 'approved');
        $this->mode = $mode ?: ($this->isApproved ? 'approved' : 'registration');

        // Resolve First Name for greeting
        $nameParts = explode(' ', trim((string) $driver->name), 2);
        $this->firstName = !empty($nameParts[0]) ? $nameParts[0] : 'Driver Partner';

        // Resolve Address & Assigned Zone / Suburb
        $addrInfo = AddressHelper::formatResponsePayload($driver);
        $formatted = $addrInfo['formatted_address'] ?: ($driver->address ?: 'Adelaide, South Australia');
        $this->fullAddress = trim(str_replace(["\r\n", "\r", "\n"], ', ', $formatted), ', ');

        $suburb = $addrInfo['suburbs'] ?: ($driver->city ?: '');
        $postcode = $driver->assigned_zip ?: ($driver->area ?: ($addrInfo['postcode'] ?: ($driver->pincode ?: '')));
        if ($suburb && $postcode) {
            $this->deliveryZone = "{$suburb} ({$postcode})";
        } elseif ($postcode) {
            $this->deliveryZone = "Postcode {$postcode}";
        } elseif ($suburb) {
            $this->deliveryZone = $suburb;
        } else {
            $this->deliveryZone = 'Adelaide Metro';
        }

        $this->vehicleRegNo = !empty($driver->vehicle_reg_no) ? $driver->vehicle_reg_no : 'Not Provided';
        $this->licenseNo = !empty($driver->license_no) ? $driver->license_no : 'Not Provided';

        // Registered Time in Adelaide Timezone
        $createdAt = $driver->created_at ? Carbon::parse($driver->created_at) : TimezoneHelper::getAdelaideNow();
        $this->registeredTime = $createdAt->timezone(TimezoneHelper::ADELAIDE_TIMEZONE)->format('d/m/Y h:i A') . ' (Adelaide Time)';

        // Logo URL
        $this->logoUrl = asset('public/assets/images/logo.png');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->mode === 'approved'
            ? "Welcome Aboard, {$this->firstName}! 🚚 Your KP's Kitchen Driver Account is Approved"
            : "Welcome to KP's Kitchen, {$this->firstName}! 🚚 Driver Registration Received";

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.driver_welcome',
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
