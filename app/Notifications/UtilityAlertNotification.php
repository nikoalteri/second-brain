<?php

namespace App\Notifications;

use App\Models\Utility;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UtilityAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Utility $utility,
        public array $alertData
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $property = $this->utility->property;
        $currentCost = $this->alertData['current_cost'] ?? 0;
        $averageCost = $this->alertData['average_cost'] ?? 0;
        $percentageOver = $this->alertData['percentage_over'] ?? 0;

        return (new MailMessage)
            ->subject("Utility Alert: {$this->utility->type} usage high at {$property->address}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your utility usage is higher than normal this month.")
            ->line("**Utility:** " . ucfirst($this->utility->type))
            ->line("**Property:** {$property->address}")
            ->line("**Provider:** {$this->utility->provider}")
            ->line("**Current Month Cost:** \$" . number_format($currentCost, 2))
            ->line("**Historical Average:** \$" . number_format($averageCost, 2))
            ->line("**Percentage Over Average:** {$percentageOver}%")
            ->line("")
            ->line("**Suggested Actions:**")
            ->line("- Check for any unusual usage patterns")
            ->line("- Review meter readings for accuracy")
            ->line("- Consider an energy audit of your property")
            ->line("- Look for any equipment that may be running inefficiently")
            ->action('View Utility Trends', url('/'))
            ->line('Monitor your consumption and let us know if you notice any issues.')
            ->salutation('Best regards,');
    }

    public function toArray(object $notifiable): array
    {
        $property = $this->utility->property;

        return [
            'utility_id' => $this->utility->id,
            'property_id' => $property->id,
            'title' => "Utility Alert: {$this->utility->type} High Usage",
            'message' => "Your {$this->utility->type} usage is {$this->alertData['percentage_over']}% higher than average",
            'type' => $this->utility->type,
            'percentage_over' => $this->alertData['percentage_over'],
            'current_cost' => $this->alertData['current_cost'],
            'average_cost' => $this->alertData['average_cost'],
            'url' => url('/'),
        ];
    }
}
