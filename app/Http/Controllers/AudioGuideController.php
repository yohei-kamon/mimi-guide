<?php

namespace App\Http\Controllers;

use App\Models\Artwork;
use App\Models\AudioGuide;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AudioGuideController extends Controller
{
    /**
     * Stream the artwork's audio guide to a visitor. The file supports Range requests for seeking.
     */
    public function show(Artwork $artwork): BinaryFileResponse
    {
        $artwork->load('audioGuides', 'exhibition.museum');

        abort_unless($artwork->isVisibleToVisitors(), 404);

        // Until guide passes are implemented, the audio of a paid museum is never served.
        abort_unless($artwork->exhibition->museum->isFree(), 403);

        $audioGuide = $artwork->audioGuide();

        abort_if($audioGuide === null, 404);

        return response()->file($audioGuide->absolutePath(), ['Content-Type' => $audioGuide->mime_type]);
    }

    /**
     * Stream an audio guide to the museum's staff for a preview.
     */
    public function preview(AudioGuide $audioGuide): BinaryFileResponse
    {
        Gate::authorize('view', $audioGuide->artwork);

        return response()->file($audioGuide->absolutePath(), ['Content-Type' => $audioGuide->mime_type]);
    }
}
