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

    <form method="POST" action="{{ route('portal.account.update-password') }}" id="portal-pw-form" class="form-grid">
        @csrf
        @method('PATCH')

        <div class="col-{{ \App\Support\FormFieldConfig::width('current_password') }}">
            <label for="ppw_current" class="form-label">Current Password</label>
            <input type="password" id="ppw_current" name="current_password" required autocomplete="current-password">
        </div>

        <div class="col-{{ \App\Support\FormFieldConfig::width('password') }}">
            <label for="ppw_new" class="form-label">New Password <small class="form-hint" style="display: inline; margin: 0;">(minimum 12 characters)</small></label>
            <input type="password" id="ppw_new" name="password" required minlength="12" autocomplete="new-password" aria-describedby="ppw-match-error">
        </div>

        <div class="col-{{ \App\Support\FormFieldConfig::width('password_confirmation') }}">
            <label for="ppw_confirm" class="form-label">Confirm New Password</label>
            <input type="password" id="ppw_confirm" name="password_confirmation" required minlength="12" autocomplete="new-password" aria-describedby="ppw-match-error">
        </div>

        <div class="col-12 form-error" id="ppw-match-error" style="display:none;" role="alert">Passwords do not match.</div>

        <div class="col-12">
            <button type="submit" class="btn btn--primary">Update password</button>
        </div>
    </form>

@endif
