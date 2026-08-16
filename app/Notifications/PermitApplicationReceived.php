<?php

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Notifications\Notification;

class PermitApplicationReceived extends Notification
{
    public function __construct(
        public readonly int $permitApplicationId,
        public readonly string $trackingReference,
        public readonly string $businessName,
        public readonly CarbonInterface $receivedAt,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'permit_application_received',
            'permit_application_id' => $this->permitApplicationId,
            'tracking_reference' => $this->trackingReference,
            'business_name' => $this->businessName,
            'title' => 'Permit application received',
            'message' => 'The Municipality received your application for processing. This does not mean the application is complete, assessed, or approved.',
            'received_at' => $this->receivedAt->toIso8601String(),
        ];
    }
}
