<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChecklistReminder extends Notification
{
    use Queueable;

    public function __construct(public string $type) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->type === 'morning'
            ? 'Morning Prep Reminder — ISMS'
            : 'Closing Check Reminder — ISMS';

        $line = $this->type === 'morning'
            ? 'Good morning! Please ensure all junior chefs have completed their morning section checks before service begins.'
            : 'End of service reminder. Please confirm all junior chefs have completed their closing section checks.';

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hello, Head Chef.')
            ->line($line)
            ->action('View Prep Checklist', route('prep.overview'))
            ->line('Inventory, Sales and Management System');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => $this->type,
            'message' => $this->type === 'morning'
                ? 'Morning prep reminder: check that all sections are started.'
                : 'Closing reminder: confirm all sections are completed.',
        ];
    }
}
