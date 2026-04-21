<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\TypographyCompiler;
use App\Services\TypographyResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ThemeTypographyController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->can('manage_cms_settings'), 403);

        $data = $request->validate([
            'typography'                       => ['required', 'array'],
            'typography.buckets'               => ['required', 'array'],
            'typography.buckets.heading_family' => ['nullable', 'string', 'max:255'],
            'typography.buckets.body_family'    => ['nullable', 'string', 'max:255'],
            'typography.buckets.nav_family'     => ['nullable', 'string', 'max:255'],
            'typography.elements'              => ['required', 'array'],
            'typography.sample_text'           => ['nullable', 'string', 'max:500'],
        ]);

        $normalised = $this->normalise($data['typography']);

        SiteSetting::updateOrCreate(
            ['key' => 'typography'],
            ['value' => json_encode($normalised), 'type' => 'json', 'group' => 'design'],
        );
        Cache::forget('site_setting:typography');

        return response()->json(['ok' => true]);
    }

    public function export(Request $request): Response
    {
        // Gated on manage_cms_settings (same as update()). Theme SCSS export is a theme operation, but manage_cms_settings is the de-facto theme permission today; a separate edit_theme_scss permission would add role-matrix churn with no practical benefit.
        abort_unless(auth()->user()?->can('manage_cms_settings'), 403);

        $typography = TypographyResolver::load();
        $css = TypographyCompiler::compile($typography);

        $header = "// Theme typography\n// Exported " . now()->toIso8601String() . "\n\n";

        return response($header . $css . "\n")
            ->header('Content-Type', 'text/x-scss')
            ->header('Content-Disposition', 'attachment; filename="theme-typography.scss"');
    }

    private function normalise(array $typography): array
    {
        $defaults   = TypographyResolver::defaults();
        $merged     = array_replace_recursive($defaults, $typography);
        $merged['elements'] = array_intersect_key(
            $merged['elements'] ?? [],
            array_flip(TypographyResolver::ELEMENTS),
        );
        foreach (TypographyResolver::ELEMENTS as $el) {
            if (! isset($merged['elements'][$el])) {
                $merged['elements'][$el] = $defaults['elements'][$el];
            }
        }
        return $merged;
    }
}
