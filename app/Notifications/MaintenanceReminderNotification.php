<?php

namespace App\Notifications;

use App\Models\MaintenanceTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MaintenanceReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public MaintenanceTask $task) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $property = $this->task->property;

        return (new MailMessage)
            ->subject("Maintenance Due: {$this->task->name} at {$property->address}")
            ->greeting("Hello {$notifiable->name},")
            ->line("This is a reminder that maintenance is due for your property.")
            ->line("**Task:** {$this->task->name}")
            ->line("**Property:** {$property->address}")
            ->line("**Type:** " . ucfirst(str_replace('_', ' ', $this->task->type)))
            ->line("**Frequency:** " . ucfirst(str_replace('_', ' ', $this->task->frequency)))
            ->line("**Due Date:** " . ($this->task->next_due_date?->format('M d, Y') ?? 'Not scheduled'))
            ->line("**Last Completed:** " . ($this->task->last_completed_date?->format('M d, Y') ?? 'Never'))
            ->line("**Description:** " . ($this->task->description ?? 'No description provided'))
            ->action('View in App', url('/'))
            ->line('Please schedule this maintenance at your earliest convenience.')
            ->salutation('Best regards,');
    }

    public function toArray(object $notifiable): array
    {
        $property = $this->task->property;

        return [
            'task_id' => $this->task->id,
            'property_id' => $property->id,
            'title' => "Maintenance Due: {$this->task->name}",
            'message' => "{$this->task->name} is due at {$property->address}",
            'due_date' => $this->task->next_due_date?->toDateString(),
            'url' => url('/'),
        ];
    }
}
