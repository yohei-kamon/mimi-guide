<?php

namespace App\Http\Controllers;

use App\Models\Artwork;
use App\Models\AudioGuide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AudioGuideController extends Controller
{
    /**
     * Stream the artwork's audio guide to a visitor holding a valid pass in a paid museum.
     * The visitor's locale is used, falling back to the museum's default locale.
     * The file supports Range requests for seeking.
     */
    public function show(Request $request, Artwork $artwork): BinaryFileResponse
    {
        $artwork->load('audioGuides', 'exhibition.museum');

        abort_unless($artwork->isVisibleToVisitors(), 404);

        $museum = $artwork->exhibition->museum;

        abort_unless($museum->grantsAccessWith($museum->passTokenFrom($request)), 403);

        $audioGuide = $artwork->audioGuide($museum->visitorLocale($request));

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
