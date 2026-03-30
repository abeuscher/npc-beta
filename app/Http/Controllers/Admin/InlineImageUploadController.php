<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\HasMedia;

class InlineImageUploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file'       => ['required', 'image', 'mimes:png,jpg,jpeg,gif,webp,svg', 'max:10240'],
            'model_type' => ['required', 'string'],
            'model_id'   => ['required', 'string'],
        ]);

        $allowed = [
            'page_widget'    => \App\Models\PageWidget::class,
            'event'          => \App\Models\Event::class,
            'email_template' => \App\Models\EmailTemplate::class,
        ];

        $modelClass = $allowed[$request->input('model_type')] ?? null;

        if (! $modelClass) {
            return response()->json(['error' => 'Invalid model type.'], 422);
        }

        $model = $modelClass::find($request->input('model_id'));

        if (! $model || ! $model instanceof HasMedia) {
            return response()->json(['error' => 'Model not found.'], 404);
        }

        $media = $model
            ->addMedia($request->file('file'))
            ->toMediaCollection('inline-images');

        return response()->json(['url' => $media->getUrl(), 'media_id' => $media->id]);
    }
}
