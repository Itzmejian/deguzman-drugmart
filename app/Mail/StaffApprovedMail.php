<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Ipinapadala kapag in-approve na ni Admin ang account ng staff. */
class StaffApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your De Guzman Drugmart account is approved');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.staff-approved');
    }
}
