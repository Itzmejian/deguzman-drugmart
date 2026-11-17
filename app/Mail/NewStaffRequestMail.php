<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Ipinapadala sa (mga) Admin kapag may bagong staff na nag-request ng account. */
class NewStaffRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $staff, public User $admin) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New staff account: ' . $this->staff->name);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.new-staff-request');
    }
}
