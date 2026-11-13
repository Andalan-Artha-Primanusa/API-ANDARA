<?php

namespace App\Jobs;

use App\Modules\Administration\Models\EmailLog;
use App\Modules\Administration\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendEmailNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $emailLog;

    public function __construct(EmailLog $emailLog)
    {
        $this->emailLog = $emailLog;
    }

    public function handle(): void
    {
        try {
            // Build the email
            $mailable = new \App\Mail\NotificationMail(
                $this->emailLog->subject,
                $this->emailLog->body,
                $this->emailLog->recipient_email
            );

            // Send via mail
            Mail::to($this->emailLog->recipient_email)->send($mailable);

            // Mark as sent
            $this->emailLog->markAsSent();

        } catch (\Exception $e) {
            // Mark as failed and log error
            $this->emailLog->markAsFailed($e->getMessage());

            // Retry if allowed
            if ($this->emailLog->canRetry()) {
                $this->release(delay: 300); // Retry after 5 minutes
            }
        }
    }
}


