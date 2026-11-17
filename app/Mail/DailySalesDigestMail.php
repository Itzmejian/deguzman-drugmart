<?php

namespace App\Mail;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Buod ng benta sa isang araw (Branded / Generic / Grocery), ipinapadala sa Admin tuwing 10:00 PM. */
class DailySalesDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array $d resulta ng Logbook::day() */
    public function __construct(public User $user, public Carbon $day, public array $d) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Daily sales — ' . $this->day->format('M j, Y') . ': ₱' . number_format($this->d['total'], 2));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.daily-sales-digest');
    }
}
