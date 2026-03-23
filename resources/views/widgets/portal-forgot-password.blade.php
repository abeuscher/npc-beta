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
        <label for="fp_email">Email address</label>
        <input type="email" id="fp_email" name="email" required
               value="{{ old('email') }}" autocomplete="email">
    </div>

    <button type="submit">Send reset link</button>
</form>

<p><a href="{{ route('portal.login') }}">Back to log in</a></p>
