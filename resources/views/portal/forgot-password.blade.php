@extends('layouts.public')

@section('content')
<article style="max-width:480px;margin:3rem auto;">
    <h1>Reset your password</h1>

    @if (session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <div role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('portal.password.email') }}">
        @csrf

        <div>
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" required
                   value="{{ old('email') }}" autocomplete="email">
        </div>

        <button type="submit">Send reset link</button>
    </form>

    <p><a href="{{ route('portal.login') }}">Back to log in</a></p>
</article>
@endsection
