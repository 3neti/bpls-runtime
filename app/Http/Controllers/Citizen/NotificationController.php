<?php

namespace App\Http\Controllers\Citizen;

use App\Enums\UserPermission;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(UserPermission::AccessCitizen->value);

        return Inertia::render('citizen/notifications/Index', [
            'notifications' => $request->user()
                ->notifications()
                ->latest()
                ->paginate(20)
                ->through(fn (DatabaseNotification $notification): array => [
                    'id' => $notification->id,
                    'kind' => $notification->data['kind'] ?? 'notice',
                    'title' => $notification->data['title'] ?? 'Notice',
                    'message' => $notification->data['message'] ?? '',
                    'business_name' => $notification->data['business_name'] ?? null,
                    'tracking_reference' => $notification->data['tracking_reference'] ?? null,
                    'received_at' => $notification->data['received_at'] ?? $notification->created_at?->toIso8601String(),
                    'read_at' => $notification->read_at?->toIso8601String(),
                    'permit_application_url' => isset($notification->data['permit_application_id'])
                        ? route('citizen.permit-applications.show', $notification->data['permit_application_id'], false)
                        : null,
                ]),
        ]);
    }

    public function update(Request $request, string $notification): RedirectResponse
    {
        Gate::authorize(UserPermission::AccessCitizen->value);

        $ownedNotification = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $ownedNotification->markAsRead();

        return back();
    }
}
