@foreach ($widgets as $widget)
    @if ($widget['instance'])
        @include($widget['instance']->view(), [
            'config' => $widget['config'],
            'data'   => $widget['data'],
        ])
    @endif
@endforeach
