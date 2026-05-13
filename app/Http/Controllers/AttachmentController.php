<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function store(Request $request, Appointment $appointment)
    {
        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:10240'],
        ]);

        foreach ($validated['files'] as $file) {
            $path = $file->store("appointments/{$appointment->id}/documents", 'local');

            AppointmentAttachment::create([
                'appointment_id' => $appointment->id,
                'comment_id' => null,
                'user_id' => $request->user()->id,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        return back();
    }

    public function show(Request $request, AppointmentAttachment $attachment): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        $disposition = $request->boolean('download')
            ? ResponseHeaderBag::DISPOSITION_ATTACHMENT
            : ResponseHeaderBag::DISPOSITION_INLINE;

        $headers = [
            'Content-Type' => $attachment->mime_type ?? 'application/octet-stream',
            'Cache-Control' => 'private, max-age=300',
        ];

        return Storage::disk('local')->response($attachment->path, $attachment->original_name, $headers, $disposition);
    }

    public function destroy(Request $request, AppointmentAttachment $attachment)
    {
        $user = $request->user();
        abort_unless($attachment->user_id === $user->id || $user->role === 'owner', 403);

        $attachment->delete();

        return back();
    }
}
