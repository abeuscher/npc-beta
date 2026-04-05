@if ($errors->any())
    <div role="alert" class="alert alert--error">
        <ul class="error-list">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('portal.password.email') }}" class="form-stack">
    @csrf

    <div>
        <label for="fp_email" class="form-label">Email address</label>
        <input type="email" id="fp_email" name="email" required
               value="{{ old('email') }}" autocomplete="email">
    </div>

    <button type="submit" class="btn btn--primary">Send reset link</button>
</form>

<p class="text-muted text-sm portal-alt-link"><a href="{{ route('portal.login') }}">Back to log in</a></p>
