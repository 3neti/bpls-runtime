<?php

use App\Actions\RenderStoryboardPdf;
use App\Enums\StoryboardExportFormat;
use App\Enums\StoryboardExportStatus;
use App\Enums\UserPermission;
use App\Jobs\GenerateStoryboardVideo;
use App\Models\Storyboard;
use App\Models\StoryboardFrame;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('staff users can create a storyboard with ordered frames', function () {
    Storage::fake('public');

    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ManageStoryboards,
    ]);

    $image = UploadedFile::fake()->image('scene-one.png', 640, 360);

    $response = $this->actingAs($user)->post(route('staff.storyboards.store'), [
        'title' => 'Barangay outreach storyboard',
        'summary' => 'A quick public information sequence.',
        'frames' => [
            [
                'title' => 'Opening scene',
                'image' => $image,
                'description' => 'Show the municipal hall entrance.',
                'dialogue' => 'Welcome to Ipil BPLS.',
                'duration_seconds' => 4,
            ],
            [
                'title' => 'Service counter',
                'description' => 'Cut to the staff processing window.',
                'dialogue' => 'Submit complete business requirements.',
                'duration_seconds' => 6,
            ],
        ],
    ]);

    $storyboard = Storyboard::query()->with('frames')->firstOrFail();

    $response->assertRedirect(route('staff.storyboards.edit', $storyboard));

    expect($storyboard)
        ->title->toBe('Barangay outreach storyboard')
        ->summary->toBe('A quick public information sequence.')
        ->frames->toHaveCount(2)
        ->and($storyboard->frames[0])
        ->title->toBe('Opening scene')
        ->position->toBe(1)
        ->duration_seconds->toBe(4)
        ->image_path->not->toBeNull()
        ->and($storyboard->frames[1])
        ->title->toBe('Service counter')
        ->position->toBe(2)
        ->duration_seconds->toBe(6);

    Storage::disk('public')->assertExists($storyboard->frames[0]->image_path);
});

test('staff users can update remove and reorder storyboard frames', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ManageStoryboards,
    ]);
    $storyboard = Storyboard::factory()->create([
        'created_by_id' => $user->id,
        'title' => 'Initial title',
    ]);
    StoryboardFrame::factory()->create([
        'storyboard_id' => $storyboard->id,
        'position' => 1,
        'title' => 'First old frame',
        'duration_seconds' => 3,
    ]);
    StoryboardFrame::factory()->create([
        'storyboard_id' => $storyboard->id,
        'position' => 2,
        'title' => 'Second old frame',
        'duration_seconds' => 4,
    ]);

    $response = $this->actingAs($user)->post(route('staff.storyboards.update', $storyboard), [
        '_method' => 'PUT',
        'title' => 'Updated sequence',
        'summary' => 'Updated summary.',
        'frames' => [
            [
                'title' => 'Second frame moved first',
                'description' => 'Now appears first.',
                'dialogue' => 'Updated voiceover.',
                'duration_seconds' => 8,
            ],
        ],
    ]);

    $response->assertRedirect(route('staff.storyboards.edit', $storyboard));

    $storyboard->refresh()->load('frames');

    expect($storyboard)
        ->title->toBe('Updated sequence')
        ->frames->toHaveCount(1)
        ->and($storyboard->frames[0])
        ->title->toBe('Second frame moved first')
        ->position->toBe(1)
        ->duration_seconds->toBe(8);
});

test('storyboard validation rejects missing frame title and excessive duration', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ManageStoryboards,
    ]);

    $this->actingAs($user)
        ->from(route('staff.storyboards.create'))
        ->post(route('staff.storyboards.store'), [
            'title' => 'Invalid storyboard',
            'frames' => [
                [
                    'title' => '',
                    'duration_seconds' => 90,
                ],
            ],
        ])
        ->assertRedirect(route('staff.storyboards.create'))
        ->assertSessionHasErrors([
            'frames.0.title',
            'frames.0.duration_seconds',
        ]);
});

test('pdf export stores a downloadable storyboard pdf record', function () {
    Storage::fake('public');

    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ManageStoryboards,
    ]);
    $storyboard = storyboardWithFrames($user);

    $response = $this->actingAs($user)
        ->post(route('staff.storyboards.exports.pdf', $storyboard));

    $response->assertRedirect(route('staff.storyboards.edit', $storyboard));

    $export = $storyboard->exports()->firstOrFail();

    expect($export)
        ->format->toBe(StoryboardExportFormat::Pdf)
        ->status->toBe(StoryboardExportStatus::Completed)
        ->path->not->toBeNull()
        ->downloadUrl()->not->toBeNull();

    Storage::disk('public')->assertExists($export->path);

    $pdf = Storage::disk('public')->get($export->path);

    expect($pdf)
        ->toStartWith('%PDF-1.4')
        ->toContain('Storyboard')
        ->toContain('Public market opening')
        ->toContain('Welcome shoppers to the public market.')
        ->and(app(RenderStoryboardPdf::class)->handle($storyboard))->toContain('Storyboard');
});

test('video export creates a pending export and dispatches the video job', function () {
    Queue::fake();

    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ManageStoryboards,
    ]);
    $storyboard = storyboardWithFrames($user);

    $response = $this->actingAs($user)
        ->post(route('staff.storyboards.exports.video', $storyboard));

    $response->assertRedirect(route('staff.storyboards.edit', $storyboard));

    $export = $storyboard->exports()->firstOrFail();

    expect($export)
        ->format->toBe(StoryboardExportFormat::Video)
        ->status->toBe(StoryboardExportStatus::Pending)
        ->path->toBeNull();

    Queue::assertPushed(GenerateStoryboardVideo::class, fn (GenerateStoryboardVideo $job): bool => $job->storyboardExportId === $export->id);
});

test('staff users without storyboard permission cannot access storyboard workspace', function () {
    $user = userWithPermissions([
        UserPermission::AccessStaff,
        UserPermission::ViewPermitApplications,
    ]);

    $this->actingAs($user)
        ->get(route('staff.storyboards.index'))
        ->assertForbidden();
});

function storyboardWithFrames(User $user): Storyboard
{
    $storyboard = Storyboard::factory()->create([
        'created_by_id' => $user->id,
        'title' => 'Market advisory storyboard',
        'summary' => 'Public market information video.',
    ]);

    StoryboardFrame::factory()->create([
        'storyboard_id' => $storyboard->id,
        'position' => 1,
        'title' => 'Public market opening',
        'description' => 'Show the main public market entrance.',
        'dialogue' => 'Welcome shoppers to the public market.',
        'duration_seconds' => 5,
    ]);

    StoryboardFrame::factory()->create([
        'storyboard_id' => $storyboard->id,
        'position' => 2,
        'title' => 'Permit reminder',
        'description' => 'Show vendors preparing permit documents.',
        'dialogue' => 'Renew your business permit before the deadline.',
        'duration_seconds' => 7,
    ]);

    return $storyboard->load('frames');
}
