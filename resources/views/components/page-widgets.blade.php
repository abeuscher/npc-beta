@foreach ($blocks as $block)
    <div class="widget widget--{{ $block['handle'] }}" id="widget-{{ $block['instance_id'] }}">
        {!! $block['html'] !!}
    </div>
@endforeach
