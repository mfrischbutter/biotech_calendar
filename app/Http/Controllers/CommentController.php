<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Comment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, Appointment $appointment)
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
        ]);

        $comment = $appointment->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        ActivityLog::create([
            'appointment_id' => $appointment->id,
            'comment_id' => $comment->id,
            'user_id' => $request->user()->id,
            'action' => 'comment_added',
            'description' => "Kommentar zu \"{$appointment->title}\" hinzugefügt",
        ]);

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
