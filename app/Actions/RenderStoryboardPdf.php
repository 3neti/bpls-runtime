<?php

namespace App\Actions;

use App\Models\Storyboard;

final class RenderStoryboardPdf
{
    public function handle(Storyboard $storyboard): string
    {
        $storyboard->loadMissing('frames');

        $document = new SimplePdfDocument(
            'Storyboard',
            $this->documentCode($storyboard),
            'Storyboard workspace export',
            'Generated from persisted storyboard frames.',
        );

        $page = $document->addPage($this->storyboardLabel($storyboard));
        $y = SimplePdfDocument::ContentTop;

        $document->rectangle($page, 42, $y - 82, 511, 82, 0.94);
        $document->wrappedText($page, $storyboard->title, 54, $y - 24, 330, 17, 20, true);
        $document->text($page, $storyboard->frames->count().' frames', 541, $y - 28, 10, true, 'right');
        $document->text($page, $storyboard->frames->sum('duration_seconds').' seconds', 541, $y - 48, 9, false, 'right');
        if ($storyboard->summary !== null) {
            $document->wrappedText($page, $storyboard->summary, 54, $y - 58, 345, 8, 10);
        }
        $y -= 112;

        foreach ($storyboard->frames as $frame) {
            if ($y < SimplePdfDocument::ContentBottom + 175) {
                $page = $document->addPage($this->storyboardLabel($storyboard).' continued');
                $y = SimplePdfDocument::ContentTop;
            }

            $document->rectangle($page, 42, $y - 112, 148, 112, 0.90);
            $document->text($page, 'VISUAL', 58, $y - 22, 8, true);
            $visual = $frame->image_path === null ? 'Placeholder' : 'Image: '.$frame->image_path;
            $document->wrappedText($page, $visual, 58, $y - 44, 116, 8, 10);
            $document->text($page, 'Frame '.$frame->position, 207, $y - 4, 8, true);
            $document->wrappedText($page, $frame->title, 207, $y - 22, 330, 12, 15, true);
            $document->text($page, $frame->duration_seconds.' sec', 541, $y - 4, 8, true, 'right');

            $notesY = $y - 56;
            $document->text($page, 'Action notes', 207, $notesY, 7.5, true);
            $notesY = $document->wrappedText($page, $frame->description ?? 'No action notes recorded.', 207, $notesY - 14, 330, 8, 10);
            $document->text($page, 'Dialogue / voiceover', 207, $notesY - 6, 7.5, true);
            $document->wrappedText($page, $frame->dialogue ?? 'No dialogue recorded.', 207, $notesY - 20, 330, 8, 10);

            $y -= 146;
        }

        return $document->render();
    }

    private function documentCode(Storyboard $storyboard): string
    {
        return 'storyboard-'.$storyboard->id.'-'.substr(hash('sha256', $storyboard->title.'|'.$storyboard->updated_at?->toIso8601String()), 0, 16);
    }

    private function storyboardLabel(Storyboard $storyboard): string
    {
        return 'Storyboard #'.$storyboard->id;
    }
}
