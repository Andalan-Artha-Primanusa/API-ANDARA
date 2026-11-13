<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    public $body;
    public $recipientEmail;

    public function __construct($subject, $body, $recipientEmail)
    {
        $this->subject = $subject;
        $this->body = $body;
        $this->recipientEmail = $recipientEmail;
    }

    // Override build agar email selalu dikirim sebagai HTML
    public function build()
    {
        return $this->subject($this->subject)
            ->view('emails.notification')
            ->with([
                'body' => $this->body,
            ]);
    }
}
