@php
    $videoUrl       = $config['video_url'] ?? '';
    $showRelated    = (bool) ($config['show_related'] ?? false);
    $modestBranding = (bool) ($config['modest_branding'] ?? true);
    $showControls   = (bool) ($config['show_controls'] ?? true);

    $embedUrl = null;
    $provider = null;

    // YouTube: youtube.com/watch?v=ID, youtu.be/ID, youtube.com/embed/ID
    if (preg_match('/(?:youtube\.com\/watch\?.*v=|youtu\.be\/|youtube\.com\/embed\/)([\w-]{11})/', $videoUrl, $m)) {
        $provider = 'youtube';
        $params = http_build_query([
            'rel'             => $showRelated ? '1' : '0',
            'modestbranding'  => $modestBranding ? '1' : '0',
            'controls'        => $showControls ? '1' : '0',
            'autoplay'        => '0',
            'playsinline'     => '1',
        ]);
        $embedUrl = 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?' . $params;
    }
    // Vimeo: vimeo.com/ID
    elseif (preg_match('/vimeo\.com\/(\d+)/', $videoUrl, $m)) {
        $provider = 'vimeo';
        $params = http_build_query([
            'byline'   => '0',
            'portrait' => '0',
            'title'    => '0',
            'autoplay' => '0',
        ]);
        $embedUrl = 'https://player.vimeo.com/video/' . $m[1] . '?' . $params;
    }
@endphp

@if ($embedUrl)
    <div class="video-wrapper">
        <iframe
            src="{{ $embedUrl }}"
            allow="fullscreen; picture-in-picture"
            allowfullscreen
            loading="lazy"
        ></iframe>
    </div>
@elseif ($videoUrl)
    <p class="text-muted">Unsupported video URL</p>
@else
    @include('widgets.components.widget-placeholder', ['title' => 'Video Embed', 'message' => 'Enter a YouTube or Vimeo URL.'])
@endif
