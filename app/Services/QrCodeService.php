<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeService
{
    /**
     * Generate a QR code for the given URL as an SVG document.
     */
    public function svg(string $url, int $size = 240): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, margin: 2), new SvgImageBackEnd));

        return $writer->writeString($url);
    }
}
