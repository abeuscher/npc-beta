@extends('layouts.public')

@section('content')
<article style="max-width:480px;margin:3rem auto;">
    <h1>Log in</h1>

    @if ($errors->any())
        <div role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('portal.login.post') }}">
        @csrf

        <div>
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" required
                   value="{{ old('email') }}" autocomplete="email">
        </div>

        <div>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required
                   autocomplete="current-password">
        </div>

        <div>
            <label>
                <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                Remember me
            </label>
        </div>

        <button type="submit">Log in</button>
    </form>

    <p><a href="{{ route('portal.signup') }}">Don't have an account? Sign up</a></p>
</article>
@endsection
