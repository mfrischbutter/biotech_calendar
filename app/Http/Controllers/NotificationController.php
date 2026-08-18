<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The bell in the top bar: mentions, assignments, conflicts and uploads
 * addressed to the signed-in user.
 */
class NotificationController extends Controller
{
    private const LIMIT = 30;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = Notification::forUser($user->id)
            ->with([
                'actor:id,first_name,last_name',
                'appointment:id,contract_id,start_at',
                'appointment.contract:id,title,contract_number',
            ])
            ->latest()
            ->limit(self::LIMIT)
            ->get();

        return response()->json([
            'notifications' => $notifications->map(fn (Notification $n) => $this->present($n))->all(),
            'unread' => Notification::forUser($user->id)->unread()->count(),
        ]);
    }

    public function markRead(Request $request, Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->forceFill(['read_at' => now()])->save();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        Notification::forUser($request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return back();
    }

    /** @return array<string, mixed> */
    private function present(Notification $n): array
    {
        return [
            'id' => $n->id,
            'type' => $n->type,
            'severity' => $n->severity(),
            'actor' => $n->actor?->name,
            'appointment_id' => $n->appointment_id,
            'appointment_title' => $n->appointment?->contract?->title,
            'appointment_start_at' => $n->appointment?->start_at?->toIso8601String(),
            'url' => $n->appointment_id
                ? route('calendar.index', ['appointment' => $n->appointment_id])
                : null,
            'data' => $n->data ?? [],
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at?->toIso8601String(),
        ];
    }
}
