<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Ipinapadala kapag nag-request ng account ang staff: ang access code ang magiging password niya. */
class StaffAccessCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your De Guzman Drugmart verification code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.staff-access-code');
    }
}
