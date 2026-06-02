<div class="portal-auth" data-tour="portal.login">
    <div class="portal-auth-card">
        <p class="portal-auth-card__brand">{{ \App\Models\SiteSetting::get('site_name', config('app.name')) }}</p>
        <h1 class="portal-auth-card__title">Sign in to your account</h1>

        @if ($errors->any())
            <div role="alert" class="alert alert--error">
                <ul class="error-list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('portal.login.post') }}" class="form-stack">
            @csrf

            <div>
                <label for="lw_email" class="form-label">Email address</label>
                <input type="email" id="lw_email" name="email" required
                       value="{{ old('email') }}" autocomplete="email">
            </div>

            <div>
                <label for="lw_password" class="form-label">Password</label>
                <input type="password" id="lw_password" name="password" required
                       autocomplete="current-password">
            </div>

            <div>
                <label class="form-check-label">
                    <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                    Remember me
                </label>
            </div>

            <button type="submit" class="btn btn--primary portal-auth-card__submit">Log in</button>
        </form>

        <div class="portal-auth-card__links">
            <p class="text-muted text-sm"><a href="{{ route('portal.password.request') }}">Forgot your password?</a></p>
            <p class="text-muted text-sm"><a href="{{ route('portal.signup') }}">Don't have an account? Sign up</a></p>
        </div>
    </div>
</div>
