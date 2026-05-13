<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentAttachment;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommentAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_comment_with_attachment_is_created(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['role' => 'owner']);

        $contract = Contract::create([
            'company_id' => $user->company_id,
            'contract_number' => 'C-1',
            'title' => 'Test',
            'kind' => 'kundentermin',
        ]);

        $appointment = Appointment::create([
            'company_id' => $user->company_id,
            'contract_id' => $contract->id,
            'start_at' => now(),
            'end_at' => now()->addHour(),
            'created_by' => $user->id,
        ]);

        $file = UploadedFile::fake()->create('hello.txt', 5);
        $resp = $this->actingAs($user)->post("/appointments/{$appointment->id}/comments", [
            'body' => 'hello with file',
            'files' => [$file],
        ]);

        $resp->assertSessionHasNoErrors();
        $this->assertSame(1, AppointmentAttachment::count());
        $this->assertSame('hello.txt', AppointmentAttachment::first()->original_name);
    }
}
