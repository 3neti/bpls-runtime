<?php

namespace Database\Factories;

use App\Enums\StoryboardExportFormat;
use App\Enums\StoryboardExportStatus;
use App\Models\Storyboard;
use App\Models\StoryboardExport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoryboardExport>
 */
class StoryboardExportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'storyboard_id' => Storyboard::factory(),
            'format' => StoryboardExportFormat::Pdf,
            'status' => StoryboardExportStatus::Pending,
            'path' => null,
            'failure_message' => null,
            'completed_at' => null,
        ];
    }
}
