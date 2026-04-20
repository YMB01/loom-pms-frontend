<?php

namespace App\Mail;

use App\Models\SystemMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminBroadcastMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SystemMessage $adminMessage) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Loom PMS] '.$this->adminMessage->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.admin-broadcast',
        );
    }
}
