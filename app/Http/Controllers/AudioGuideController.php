<?php

namespace App\Http\Controllers;

use App\Models\AudioGuide;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AudioGuideController extends Controller
{
    /**
     * Stream an audio guide to the museum's staff for a preview.
     */
    public function preview(AudioGuide $audioGuide): BinaryFileResponse
    {
        Gate::authorize('view', $audioGuide->artwork);

        return response()->file($audioGuide->absolutePath(), ['Content-Type' => $audioGuide->mime_type]);
    }
}
