@extends('layouts.portal')

@section('content')
<article style="max-width:720px;margin:3rem auto;">
    @if (session('verified'))
        <div role="status">
            <strong>Email verified.</strong> Your account is now active.
        </div>
    @endif

    <h1>Welcome, {{ auth('portal')->user()->contact->first_name }}</h1>
</article>
@endsection
