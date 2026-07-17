<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set your password &mdash; {{ config('app.name') }}</title>
    @vite(['resources/scss/admin/invitation.scss', 'resources/js/portal/password-mismatch.js'])
</head>
<body>
    <div class="card">
        <h1>Set your password</h1>
        <p class="subtitle">Choose a password to activate your account.</p>

        @if ($errors->any())
            <div class="errors-box" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ url('/admin/invitation/' . $token) }}">
            @csrf

            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required
                       autocomplete="new-password" minlength="12"
                       @error('password') aria-describedby="password-error" aria-invalid="true" @enderror>
                <span class="hint">Minimum 12 characters.</span>
                @error('password')<span id="password-error" class="error" role="alert">{{ $message }}</span>@enderror
            </div>

            <div class="field">
                <label for="password_confirmation">Confirm password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required
                       autocomplete="new-password" minlength="12" aria-describedby="mismatch-hint">
                <span id="mismatch-hint" class="error" style="display:none" role="alert">Passwords do not match.</span>
            </div>

            <button type="submit">Activate account</button>
        </form>
    </div>

    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    document.addEventListener('DOMContentLoaded', function () {
        window.NPPasswordMismatch({
            passwordEl: document.getElementById('password'),
            confirmEl:  document.getElementById('password_confirmation'),
            hintEl:     document.getElementById('mismatch-hint'),
        });
    });
    </script>
</body>
</html>
