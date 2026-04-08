@if (!empty($config['form_handle']))
    <x-public-form :handle="$config['form_handle']" />
@else
    @include('widgets.components.widget-placeholder', ['title' => 'Web Form', 'message' => 'Select a form to embed.'])
@endif
