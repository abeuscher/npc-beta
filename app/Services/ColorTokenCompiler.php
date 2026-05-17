<?php

namespace App\Services;

class ColorTokenCompiler
{
    /**
     * Compile the Theme colour tokens into a single custom-property block
     * scoped to the given containers, exactly mirroring
     * TypographyCompiler::compileScoped — multiple scopes produce a grouped
     * selector. Emitted into the public bundle via
     * AssetBuildService::collectSources(), so the tokens have no footprint in
     * the Filament admin (no .np-site there) and resolve faithfully in the
     * page-builder preview (which carries .np-site).
     *
     * Includes the legacy `--color-primary` alias so existing consumers
     * (template custom_scss, the `$color-primary` SCSS fallback chain, the
     * `--btn-*` brand refs) keep resolving — aliased to the brand token, not
     * broken.
     *
     * @param  array<int, string>  $scopes
     */
    public static function compileScoped(array $scopes, ?array $colors = null): string
    {
        $scopes = array_values(array_filter(array_map('trim', $scopes)));
        if (! $scopes) {
            return '';
        }

        $map = ColorTokenResolver::emitMap($colors);

        $decls = [];
        foreach ($map as $key => $value) {
            $decls[] = '--np-color-' . $key . ': ' . $value;
        }
        // Legacy alias — keep `var(--color-primary)` resolving (documented for
        // template custom_scss in docs/app-reference.md) by pointing it at the
        // brand token rather than dropping it.
        $decls[] = '--color-primary: var(--np-color-brand)';

        return implode(', ', $scopes) . ' { ' . implode('; ', $decls) . '; }';
    }
}
