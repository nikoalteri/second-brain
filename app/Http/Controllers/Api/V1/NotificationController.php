<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\NotificationResource;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Notifications
 *
 * Owner-only in-app notifications. Always filtered by the authenticated user's id,
 * including for superadmins.
 */
class NotificationController extends Controller
{
    /** @group Notifications @authenticated */
    public function index(Request $request): AnonymousResourceCollection
    {
        $notifications = $this->ownedBy($request)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($this->perPage($request));

        return NotificationResource::collection($notifications)->additional([
            'unread_count' => $this->ownedBy($request)->whereNull('read_at')->count(),
        ]);
    }

    /** @group Notifications @authenticated */
    public function markRead(Request $request, int $notification): NotificationResource
    {
        $record = $this->ownedBy($request)->findOrFail($notification);

        if ($record->read_at === null) {
            $record->forceFill(['read_at' => now()])->save();
        }

        return new NotificationResource($record);
    }

    private function ownedBy(Request $request): Builder
    {
        return Notification::query()
            ->withoutUserScope()
            ->where('user_id', $request->user()->id)
            ->where('type', 'in_app');
    }
}
