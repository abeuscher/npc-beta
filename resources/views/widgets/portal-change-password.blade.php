@if (auth('portal')->check())

    @if (session('success'))
        <div role="status">{{ session('success') }}</div>
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

    <form method="POST" action="{{ route('portal.account.update-password') }}" id="portal-pw-form">
        @csrf
        @method('PATCH')

        <label for="ppw_current">Current Password</label>
        <input type="password" id="ppw_current" name="current_password" required autocomplete="current-password">

        <label for="ppw_new">New Password <small>(minimum 12 characters)</small></label>
        <input type="password" id="ppw_new" name="password" required minlength="12" autocomplete="new-password">

        <label for="ppw_confirm">Confirm New Password</label>
        <input type="password" id="ppw_confirm" name="password_confirmation" required minlength="12" autocomplete="new-password">

        <div id="ppw-match-error" style="display:none;" role="alert">Passwords do not match.</div>

        <button type="submit">Update password</button>
    </form>

    <script>
    (function () {
        var form = document.getElementById('portal-pw-form');
        var pw   = document.getElementById('ppw_new');
        var conf = document.getElementById('ppw_confirm');
        var err  = document.getElementById('ppw-match-error');
        function check() { err.style.display = (conf.value && pw.value !== conf.value) ? '' : 'none'; }
        pw.addEventListener('input', check);
        conf.addEventListener('input', check);
        form.addEventListener('submit', function (e) { if (pw.value !== conf.value) { e.preventDefault(); err.style.display = ''; } });
    }());
    </script>

@endif
