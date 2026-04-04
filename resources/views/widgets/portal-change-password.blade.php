@if (auth('portal')->check())

    @if (session('success'))
        <div role="status" class="alert alert--success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div role="alert" class="alert alert--error">
            <ul class="error-list">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('portal.account.update-password') }}" id="portal-pw-form" class="form-stack">
        @csrf
        @method('PATCH')

        <div>
            <label for="ppw_current" class="form-label">Current Password</label>
            <input type="password" id="ppw_current" name="current_password" required autocomplete="current-password">
        </div>

        <div>
            <label for="ppw_new" class="form-label">New Password <small class="form-hint" style="display: inline; margin: 0;">(minimum 12 characters)</small></label>
            <input type="password" id="ppw_new" name="password" required minlength="12" autocomplete="new-password">
        </div>

        <div>
            <label for="ppw_confirm" class="form-label">Confirm New Password</label>
            <input type="password" id="ppw_confirm" name="password_confirmation" required minlength="12" autocomplete="new-password">
        </div>

        <div id="ppw-match-error" style="display:none;" role="alert" class="form-error">Passwords do not match.</div>

        <button type="submit" class="btn btn--primary">Update password</button>
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
