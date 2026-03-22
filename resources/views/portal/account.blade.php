@extends('layouts.public')

@section('content')
<article style="max-width:720px;margin:3rem auto;">
    @if (session('verified'))
        <div role="status">
            <strong>Email verified.</strong> Your account is now active.
        </div>
    @endif

    <h1>Welcome, {{ auth('portal')->user()->contact->first_name }}</h1>

    <form method="POST" action="{{ route('portal.logout') }}" style="margin-top:2rem;">
        @csrf
        <button type="submit" class="secondary outline">Log out</button>
    </form>
</article>
@endsection
