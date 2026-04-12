@php
    $members = $collectionData['members'] ?? [];
    $heading = $config['heading'] ?? '';
    $imageField = $config['image_field'] ?? '';
    $nameField = $config['name_field'] ?? '';
    $titleField = $config['title_field'] ?? '';
    $departmentField = $config['department_field'] ?? '';
    $descriptionField = $config['description_field'] ?? '';
    $linkedinField = $config['linkedin_field'] ?? '';
    $githubField = $config['github_field'] ?? '';
    $extraUrlField = $config['extra_url_field'] ?? '';
    $extraUrlLabelField = $config['extra_url_label_field'] ?? '';
    $itemsPerRow = max(1, min(6, (int) ($config['items_per_row'] ?? 3)));
    $rowAlignment = $config['row_alignment'] ?? 'center';
    $imageShape = $config['image_shape'] ?? 'circle';
    $imageAspectRatio = $config['image_aspect_ratio'] ?? '1 / 1';
    $bgColor = $config['grid_background_color'] ?? '#ffffff';
    $paneColor = $config['pane_color'] ?? '#ffffff';
    $borderColor = $config['border_color'] ?? '#cccccc';
    $borderRadius = (int) ($config['border_radius'] ?? 5);

    // Convert aspect ratio (e.g. "4 / 3") to padding-bottom percentage.
    // Circle always uses 100% (1:1).
    $paddingBottom = '100%';
    if ($imageShape !== 'circle') {
        $parts = array_map('trim', explode('/', $imageAspectRatio));
        if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1]) && (float) $parts[0] > 0) {
            $paddingBottom = round(((float) $parts[1] / (float) $parts[0]) * 100, 4) . '%';
        }
    }

    $classes = [
        'widget-board-members',
        'board-members--cols-' . $itemsPerRow,
        'board-members--align-' . $rowAlignment,
    ];
@endphp

@if (count($members) > 0 || $heading)
    <div
        class="{{ implode(' ', $classes) }}"
        style="--bm-grid-bg: {{ e($bgColor) }}; --bm-pane: {{ e($paneColor) }}; --bm-border: {{ e($borderColor) }}; --bm-radius: {{ $borderRadius }}px; --bm-padding: {{ $paddingBottom }};"
    >
        @if ($heading)
            <h2 class="board-members__heading">{{ $heading }}</h2>
        @endif

        <div class="board-members__grid">
            @foreach ($members as $member)
                @php
                    $media = $member['_media'] ?? [];
                    $memberImage = $imageField ? ($media[$imageField] ?? null) : null;
                    $name = $nameField ? ($member[$nameField] ?? '') : '';
                    $title = $titleField ? ($member[$titleField] ?? '') : '';
                    $department = $departmentField ? ($member[$departmentField] ?? '') : '';
                    $description = $descriptionField ? ($member[$descriptionField] ?? '') : '';
                    $linkedin = $linkedinField ? ($member[$linkedinField] ?? '') : '';
                    $github = $githubField ? ($member[$githubField] ?? '') : '';
                    $extraUrl = $extraUrlField ? ($member[$extraUrlField] ?? '') : '';
                    $extraUrlLabel = $extraUrlLabelField ? ($member[$extraUrlLabelField] ?? '') : '';

                    $imageUrl = '';
                    if ($memberImage) {
                        $imageUrl = !empty($memberImage->generated_conversions['webp'])
                            ? $memberImage->getUrl('webp')
                            : $memberImage->getUrl();
                    }
                @endphp

                <article class="board-member {{ $imageShape === 'circle' ? 'board-member--circle' : 'board-member--rectangle' }}">
                    @if ($imageUrl)
                        <div class="board-member__image">
                            <img src="{{ $imageUrl }}" alt="{{ e($name) }}" loading="lazy">
                        </div>
                    @endif

                    @if ($name)
                        <h3 class="board-member__name">{{ $name }}</h3>
                    @endif

                    @if ($title)
                        <p class="board-member__title">{{ $title }}</p>
                    @endif

                    @if ($department)
                        <p class="board-member__department">{{ $department }}</p>
                    @endif

                    @if ($description)
                        <div class="board-member__bio">{!! $description !!}</div>
                    @endif

                    @if ($linkedin || $github || ($extraUrl && $extraUrlLabel))
                        <div class="board-member__links">
                            @if ($linkedin)
                                <a href="{{ e($linkedin) }}" target="_blank" rel="noopener noreferrer" class="board-member__link board-member__link--linkedin">
                                    @include('widgets.components.icon-linkedin')
                                    <span class="sr-only">LinkedIn</span>
                                </a>
                            @endif

                            @if ($github)
                                <a href="{{ e($github) }}" target="_blank" rel="noopener noreferrer" class="board-member__link board-member__link--github">
                                    @include('widgets.components.icon-github')
                                    <span class="sr-only">GitHub</span>
                                </a>
                            @endif

                            @if ($extraUrl && $extraUrlLabel)
                                <a href="{{ e($extraUrl) }}" target="_blank" rel="noopener noreferrer" class="board-member__link board-member__link--extra">
                                    {{ $extraUrlLabel }}
                                </a>
                            @endif
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
@endif
