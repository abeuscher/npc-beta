<?php

namespace App\Services\ImportExport;

/**
 * SiteSettings export/import policy (session A001/3). The security boundary for
 * the site_settings payload lives here, shared by both halves of the bundle
 * subsystem: the exporter consults these lists pre-emptively at emit time so
 * secrets never enter the bundle, and the importer re-applies them at write
 * time so a tampered bundle still can't land a secret on a target install.
 * Belt + suspenders by design — neither side trusts the other.
 */
class SiteSettingsBundlePolicy
{
    /**
     * Explicit allow-list of keys the SiteSettings exporter will read out of
     * SiteSetting. Anything not in this list is dropped silently. New keys
     * land here only on review — additions don't auto-flow into bundles.
     *
     * @var array<int, string>
     */
    public const ALLOW_LIST = [
        // Site identity
        'site_name',
        'site_description',
        'site_default_og_image',
        'logo_path',
        'favicon_path',
        // Admin branding
        'admin_brand_name',
        'admin_logo_path',
        'admin_primary_color',
        'admin_secondary_color',
        // SEO + custom snippets
        'noindex_global',
        'site_head_snippet',
        'site_body_snippet',
        'site_body_open_snippet',
        // Routing prefixes
        'blog_prefix',
        'events_prefix',
        'donations_prefix',
        'portal_prefix',
        'system_prefix',
        // Publishing defaults
        'default_content_template_default',
        'default_content_template_event',
        'default_content_template_post',
        'auto_publish_pages',
        'auto_publish_posts',
        'event_auto_publish',
        // Brand-relevant auth-flow copy
        'system_page_content_email_verify',
        'system_page_content_reset_password',
        // Editor + display
        'editor_color_swatches',
        'image_breakpoints',
        'timezone',
        'dashboard_welcome',
    ];

    /**
     * Deny-list prefixes. Anything starting with these is hard-rejected even
     * if it somehow appears in a bundle's site_settings payload.
     *
     * @var array<int, string>
     */
    public const DENY_PREFIXES = [
        'stripe_',
        'qb_',
        'quickbooks_',
        'mailchimp_',
        'mail_',
        'resend_',
        'build_server_',
    ];

    /**
     * Defence-in-depth tail-guard pattern: any key whose substring matches
     * common secret-shaped suffixes is hard-rejected. Catches new keys we
     * haven't named explicitly.
     */
    public const DENY_REGEX = '/(_api_key|_secret|_token|_password|_webhook)/i';

    /**
     * Explicit per-key denies for install-state flags that aren't secrets
     * but aren't valid to carry across installs either.
     *
     * @var array<int, string>
     */
    public const DENY_NAMED = [
        'installation_completed_at',
        'horizon_enabled',
    ];

    /**
     * Returns true if a SiteSetting key should never enter or leave a bundle.
     * Checks named deny, prefix matches, and the regex tail-guard. Used by
     * both the exporter (pre-emptive filter) and the importer (defensive
     * re-check on inbound bundles).
     */
    public static function isDenied(string $key): bool
    {
        if (in_array($key, self::DENY_NAMED, true)) {
            return true;
        }
        foreach (self::DENY_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }
        if (preg_match(self::DENY_REGEX, $key)) {
            return true;
        }

        return false;
    }
}
