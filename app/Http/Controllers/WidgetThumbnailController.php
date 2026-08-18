<?php

namespace App\Http\Controllers;

use App\Services\WidgetRegistry;

class WidgetThumbnailController extends Controller
{
    public function show(string $handle, string $file)
    {
        if (! preg_match('/^(static|preset-[a-z0-9-]+)\.png$/', $file)) {
            abort(404);
        }

        $def = app(WidgetRegistry::class)->find($handle);

        if (! $def) {
            abort(404);
        }

        $path = $def->thumbnailDir() . '/' . $file;

        if (! is_file($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type'  => 'image/png',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
