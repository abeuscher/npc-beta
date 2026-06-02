<div class="portal-auth">
    <div class="portal-auth-card">
        <p class="portal-auth-card__brand">{{ \App\Models\SiteSetting::get('site_name', config('app.name')) }}</p>
        <h1 class="portal-auth-card__title">Reset your password</h1>
        <p class="text-muted portal-auth-card__intro">Enter your email and we'll send you a link to set a new password.</p>

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

            <button type="submit" class="btn btn--primary portal-auth-card__submit">Send reset link</button>
        </form>

        <div class="portal-auth-card__links">
            <p class="text-muted text-sm"><a href="{{ route('portal.login') }}">Back to log in</a></p>
        </div>
    </div>
</div>
