<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\AppointmentAttachment;
use App\Models\Comment;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function store(Request $request, Appointment $appointment)
    {
        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:1000'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:10240'],
        ]);

        $hasBody = ! empty(trim($validated['body'] ?? ''));
        $hasFiles = ! empty($validated['files'] ?? []);

        if (! $hasBody && ! $hasFiles) {
            return back()->withErrors(['body' => __('A message or attachment is required.')]);
        }

        $comment = $appointment->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'] ?? '',
        ]);

        foreach ($validated['files'] ?? [] as $file) {
            $path = $file->store("appointments/{$appointment->id}/comments", 'local');

            AppointmentAttachment::create([
                'appointment_id' => $appointment->id,
                'comment_id' => $comment->id,
                'user_id' => $request->user()->id,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        ActivityLog::create([
            'appointment_id' => $appointment->id,
            'comment_id' => $comment->id,
            'user_id' => $request->user()->id,
            'action' => 'comment_added',
            'description' => "Kommentar zu \"{$appointment->title}\" hinzugefügt",
        ]);

        // @mentions notify the named person; assigned workers and the creator
        // get the quieter "commented" notice.
        $this->notifications->commentPosted(
            $comment->setRelation('appointment', $appointment),
            $request->user(),
        );

        return back();
    }

    public function destroy(Request $request, Comment $comment)
    {
        $user = $request->user();
        abort_unless($comment->user_id === $user->id || $user->role === 'owner', 403);

        $comment->delete();

        return back();
    }
}
