<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeService
{
    public function svg(string $value): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(320, 16),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString($value);
    }
}
