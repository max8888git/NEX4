<?php

namespace App\Http\Controllers;

use App\Support\ProfilePhoto;
use App\Support\RegistrarSession;

/** Serves profile photos to signed-in users (GET /api/avatar/u-{id}). */
class ProfilePhotoController extends Controller
{
    public function show(string $id)
    {
        if (! RegistrarSession::user()) {
            abort(401);
        }
        if (! preg_match('/^u-(\d+)$/', $id, $m)) {
            abort(404);
        }
        $path = ProfilePhoto::path((int) $m[1]);
        if (! $path) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => ProfilePhoto::mime($path),
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
