<?php

namespace App\Http\Controllers;

use App\Helpers\ApiTokenHelper;
use App\Helpers\ImageUploadHelper;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SecureMediaController extends Controller
{
    /**
     * Serve a private driver document (licence or vehicle registration).
     * Allowed with a valid signed link, an admin browser session, or an admin
     * bearer token.
     */
    public function show(Request $request, string $folder, string $file)
    {
        $allowed = $request->hasValidSignature()
            || Auth::check()
            || ApiTokenHelper::find(User::class, $request->bearerToken());

        if (!$allowed) {
            abort(403);
        }

        $base = realpath(ImageUploadHelper::privateFolderPath($folder));
        $full = $base ? realpath($base . DIRECTORY_SEPARATOR . $file) : false;

        if (!$full || !str_starts_with($full, $base . DIRECTORY_SEPARATOR) || !is_file($full)) {
            abort(404);
        }

        return response()->file($full, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
