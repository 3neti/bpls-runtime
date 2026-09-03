<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class BploRoutingDefaulted extends Notification
{
    public function __construct(public readonly int $routingSuggestionId) {}

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
            'kind' => 'bplo_routing_system_defaulted',
            'routing_suggestion_id' => $this->routingSuggestionId,
            'title' => 'BPLO routing default applied',
            'message' => 'The laboratory sentinel applied an expired provisional routing suggestion. This dispatched office work only and created no approval or financial authority.',
        ];
    }
}
