@extends('layouts.public')

@section('content')
<article style="max-width:480px;margin:3rem auto;">
    <h1>Set a new password</h1>

    @if ($errors->any())
        <div role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('portal.password.update') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" required
                   value="{{ old('email', request()->query('email')) }}" autocomplete="email">
            @error('email')<span role="alert">{{ $message }}</span>@enderror
        </div>

        <div>
            <label for="password">New password</label>
            <input type="password" id="password" name="password" required
                   autocomplete="new-password" minlength="12">
            <small>Minimum 12 characters.</small>
            @error('password')<span role="alert">{{ $message }}</span>@enderror
        </div>

        <div>
            <label for="password_confirmation">Confirm new password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required
                   autocomplete="new-password" minlength="12">
        </div>

        <button type="submit">Reset password</button>
    </form>
</article>
@endsection
