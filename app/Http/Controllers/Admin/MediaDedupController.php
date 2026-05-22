<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Media\MediaDedupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaDedupController extends Controller
{
    /**
     * Owner-agnostic upload-time duplicate check. The caller hashes the file it
     * is about to upload (SHA-256 of the raw bytes) and posts the hex digest;
     * we return existing library assets the operator could reuse instead.
     */
    public function check(Request $request, MediaDedupService $dedup): JsonResponse
    {
        abort_unless(auth()->check(), 403);

        $validated = $request->validate([
            'hash'      => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'file_name' => ['nullable', 'string', 'max:255'],
        ]);

        $matches = $dedup->findMatches(
            $validated['hash'],
            $validated['file_name'] ?? null,
        );

        return response()->json(['matches' => $matches]);
    }
}
