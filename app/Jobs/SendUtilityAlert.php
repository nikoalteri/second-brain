<?php

namespace App\Jobs;

use App\Models\Utility;
use App\Models\User;
use App\Notifications\UtilityAlertNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendUtilityAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Utility $utility,
        public User $user,
        public array $alertData
    ) {}

    public function handle(): void
    {
        try {
            // Reload utility to ensure fresh data
            $utility = Utility::findOrFail($this->utility->id);

            // Verify user still has access
            if ($utility->user_id !== $this->user->id) {
                Log::warning('Utility alert unauthorized access attempt', [
                    'utility_id' => $utility->id,
                    'user_id' => $this->user->id,
                ]);

                return;
            }

            // Send notification with alert data
            $this->user->notify(new UtilityAlertNotification($utility, $this->alertData));

            Log::info('Utility alert sent', [
                'utility_id' => $utility->id,
                'user_id' => $this->user->id,
                'property_id' => $utility->property_id,
                'percentage_over' => $this->alertData['percentage_over'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send utility alert', [
                'utility_id' => $this->utility->id,
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
