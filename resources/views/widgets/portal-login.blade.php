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
        <label for="lw_email">Email address</label>
        <input type="email" id="lw_email" name="email" required
               value="{{ old('email') }}" autocomplete="email">
    </div>

    <div>
        <label for="lw_password">Password</label>
        <input type="password" id="lw_password" name="password" required
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
