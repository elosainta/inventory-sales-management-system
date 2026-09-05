<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvited extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $tempPassword,
        public readonly string $role,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $roleLabel = ucwords(str_replace('_', ' ', $this->role));

        return (new MailMessage)
            ->subject('Welcome to Inventory, Sales and Management System — Your Login Details')
            ->greeting("Welcome, {$this->name}!")
            ->line("Your account has been set up on **Inventory, Sales and Management System** as **{$roleLabel}**.")
            ->line('Here are your login credentials:')
            ->line("**Email:** {$this->email}")
            ->line("**Temporary Password:** {$this->tempPassword}")
            ->action('Log In Now', route('login'))
            ->line('Please change your password after your first login — go to your Profile page once you are in.')
            ->line('If you have any trouble logging in, contact your kitchen manager.');
    }
}
