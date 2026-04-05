@php
    $heading          = $config['heading'] ?? '';
    $platforms        = $config['platforms'] ?? [];
    $alignment        = $config['alignment'] ?? 'center';
    $iconSize         = $config['icon_size'] ?? 'small';
    $backgroundColor  = $config['background_color'] ?? '';
    $textColor        = $config['text_color'] ?? '';
    $fullWidth        = $config['full_width'] ?? false;
    $mastodonInstance = $config['mastodon_instance'] ?? 'mastodon.social';

    $iconPx = $iconSize === 'medium' ? '28px' : '20px';

    $pageUrl   = request()->url();
    $pageTitle = $heading ?: config('app.name', 'Check this out');
    $shareText = $pageTitle . ' ' . $pageUrl;

    $shareUrls = [
        'bluesky'   => 'https://bsky.app/intent/compose?text=' . urlencode($shareText),
        'mastodon'  => 'https://' . e($mastodonInstance) . '/share?text=' . urlencode($shareText),
        'email'     => 'mailto:?subject=' . rawurlencode($pageTitle) . '&body=' . rawurlencode($pageUrl),
        'linkedin'  => 'https://www.linkedin.com/sharing/share-offsite/?url=' . urlencode($pageUrl),
        'facebook'  => 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($pageUrl),
    ];

    $titles = [
        'bluesky'   => 'Share on Bluesky',
        'mastodon'  => 'Share on Mastodon',
        'email'     => 'Share via email',
        'copy_link' => 'Copy link',
        'linkedin'  => 'Share on LinkedIn',
        'facebook'  => 'Share on Facebook',
    ];

    // Platform display order
    $platformOrder = ['bluesky', 'mastodon', 'email', 'copy_link', 'linkedin', 'facebook'];
    $enabledPlatforms = array_intersect($platformOrder, $platforms);

    $containerStyle = '';
    if ($backgroundColor) {
        $containerStyle .= 'background-color:' . e($backgroundColor) . ';';
    }
    if ($textColor) {
        $containerStyle .= 'color:' . e($textColor) . ';';
    }
@endphp

@if (count($enabledPlatforms) > 0)
    <div class="widget-social-sharing" @if ($containerStyle) style="{{ $containerStyle }}" @endif>
        <div class="{{ $fullWidth ? 'site-container' : '' }}">
            @if ($heading)
                <h2 class="social-sharing__heading">{{ $heading }}</h2>
            @endif

            <div class="social-sharing__row social-sharing__row--{{ $alignment }}" style="--icon-size: {{ $iconPx }}">
                @foreach ($enabledPlatforms as $platform)
                    @if ($platform === 'copy_link')
                        <button
                            type="button"
                            class="social-sharing__link"
                            title="{{ $titles[$platform] }}"
                            x-data="{ copied: false }"
                            x-on:click="navigator.clipboard.writeText(window.location.href).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                        >
                            @include('widgets.partials.share-icons.copy_link')
                            <span class="social-sharing__copied" x-show="copied" x-cloak x-transition.opacity>Copied!</span>
                        </button>
                    @else
                        <a
                            href="{{ $shareUrls[$platform] }}"
                            class="social-sharing__link"
                            title="{{ $titles[$platform] }}"
                            target="_blank"
                            rel="noopener"
                        >
                            @include('widgets.partials.share-icons.' . $platform)
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
@endif
