<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set your password &mdash; {{ config('app.name') }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
            font-size: 0.9375rem;
            background: #f1f5f9;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .card {
            background: #fff;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.12), 0 1px 2px rgba(0,0,0,.08);
            padding: 2.25rem 2rem;
            width: 100%;
            max-width: 24rem;
        }
        h1 { font-size: 1.25rem; font-weight: 600; margin-bottom: 0.25rem; }
        .subtitle { color: #64748b; font-size: 0.875rem; margin-bottom: 1.75rem; }
        label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.375rem; }
        input[type="password"] {
            display: block;
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.375rem;
            font-size: 0.9375rem;
            line-height: 1.5;
            color: #1e293b;
            background: #fff;
            outline: none;
        }
        input[type="password"]:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
        .field { margin-bottom: 1.125rem; }
        .hint { color: #64748b; font-size: 0.8125rem; margin-top: 0.25rem; }
        .error { color: #dc2626; font-size: 0.8125rem; margin-top: 0.25rem; }
        .errors-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 0.375rem;
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            font-size: 0.875rem;
            color: #991b1b;
        }
        .errors-box ul { padding-left: 1.25rem; }
        button[type="submit"] {
            display: block;
            width: 100%;
            padding: 0.625rem 1rem;
            margin-top: 1.5rem;
            background: #6366f1;
            color: #fff;
            font-size: 0.9375rem;
            font-weight: 600;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            line-height: 1.5;
        }
        button[type="submit"]:hover { background: #4f46e5; }
    </style>
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
                       autocomplete="new-password" minlength="12">
                <span class="hint">Minimum 12 characters.</span>
                @error('password')<span class="error" role="alert">{{ $message }}</span>@enderror
            </div>

            <div class="field">
                <label for="password_confirmation">Confirm password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required
                       autocomplete="new-password" minlength="12">
                <span id="mismatch-hint" class="error" style="display:none" role="alert">Passwords do not match.</span>
            </div>

            <button type="submit">Activate account</button>
        </form>
    </div>

    <script>
    (function () {
        var password     = document.getElementById('password');
        var confirmation = document.getElementById('password_confirmation');
        var hint         = document.getElementById('mismatch-hint');
        function check() {
            hint.style.display = (confirmation.value.length > 0 && password.value !== confirmation.value) ? '' : 'none';
        }
        password.addEventListener('input', check);
        confirmation.addEventListener('input', check);
    }());
    </script>
</body>
</html>
