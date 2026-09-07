<?php

namespace App\Actions;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

final class BuildPermitVerificationQrDataUrl
{
    public function handle(string $verificationUrl): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(180, 4),
            new SvgImageBackEnd,
        );
        $svg = (new Writer($renderer))->writeString($verificationUrl);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
