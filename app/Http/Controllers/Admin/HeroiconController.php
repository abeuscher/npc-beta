<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class HeroiconController extends Controller
{
    public function index(): JsonResponse
    {
        $icons = Cache::remember('admin:heroicons:outline:v1', now()->addHours(24), function () {
            $svgPath = base_path('vendor/blade-ui-kit/blade-heroicons/resources/svg');
            $files = glob($svgPath . '/o-*.svg') ?: [];
            sort($files);

            $out = [];
            foreach ($files as $file) {
                $name = preg_replace('/^o-(.+)\.svg$/', '$1', basename($file));
                $svg = file_get_contents($file);
                if ($svg === false) {
                    continue;
                }
                $out[] = ['name' => $name, 'svg' => trim($svg)];
            }
            return $out;
        });

        return response()->json(['icons' => $icons]);
    }
}
