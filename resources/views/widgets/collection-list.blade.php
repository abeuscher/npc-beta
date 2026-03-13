@if (!empty($config['heading']))
    <h2>{{ $config['heading'] }}</h2>
@endif

@if (empty($data))
    <p>No items to display.</p>
@else
    @foreach ($data as $item)
        <dl>
            @foreach ($item as $key => $value)
                <dt>{{ $key }}</dt>
                <dd>{{ is_array($value) ? implode(', ', $value) : $value }}</dd>
            @endforeach
        </dl>
    @endforeach
@endif
